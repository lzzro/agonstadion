<?php
# =====================================================================
# Modelo: UsuarioRepositorio   ->   tabla "usuario" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Esta clase es la unica que habla con la base. Usuario sigue siendo la
# clase del dominio, sin saber que existe una base de datos: el
# repositorio la recibe para guardar, y la construye al leer.
#
# Todas las consultas van con sentencias preparadas (prepare + bind).
# Nunca se arma el SQL pegando texto: si el correo trajera una comilla
# o un fragmento de SQL, con la concatenacion se ejecutaria; con
# prepare viaja siempre como dato.
# =====================================================================

require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Rol.php';

class UsuarioRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # --- Alta -------------------------------------------------------
    # Inserta el usuario y le guarda el id que genera el AUTO_INCREMENT.
    # Devuelve un arreglo de errores, vacio si se pudo guardar.
    public function insertar(Usuario $usuario)
    {
        $errores = $usuario->validar();
        if (!empty($errores)) {
            return $errores;
        }

        # Aviso temprano, para dar un mensaje claro en vez del error
        # crudo de la restriccion UNIQUE.
        if ($this->existeCorreo($usuario->getCorreo())) {
            return array('Ya hay una cuenta con ese correo.');
        }

        $sql = 'INSERT INTO usuario (correo, hash_password, nombre, apellido, alias, presentacion, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('El alta no se completa.');
        }

        $correo       = $usuario->getCorreo();
        $hash         = $usuario->getHashPassword();
        $nombre       = $usuario->getNombre();
        $apellido     = $usuario->getApellido();
        $alias        = $usuario->getAlias();
        $presentacion = $usuario->getPresentacion();
        $activo       = $usuario->getActivo();

        # 6 cadenas y un entero
        $sentencia->bind_param('ssssssi', $correo, $hash, $nombre, $apellido,
                               $alias, $presentacion, $activo);

        if (!$sentencia->execute()) {
            # 1062 es la violacion de una clave unica. Puede pasar aunque
            # existeCorreo() haya dicho que no, si alguien se registro con
            # el mismo correo en el medio.
            if ($sentencia->errno === 1062) {
                $sentencia->close();
                return array('Ya hay una cuenta con ese correo.');
            }
            $sentencia->close();
            return array('El alta no queda guardada.');
        }

        $usuario->setIdUsuario($this->conexion->insert_id);
        $sentencia->close();

        # Toda cuenta nueva nace como jugador. Los demas roles
        # (organizador, arbitro, administrador) se asignan aparte.
        return $this->asignarRolPorNombre($usuario, 'jugador');
    }

    # Agrega una fila en usuario_rol y tambien el Rol al objeto, para que
    # el usuario en memoria quede igual que el de la base. El id del rol
    # sale del catalogo por su nombre: no hay numeros fijos en el codigo.
    public function asignarRolPorNombre(Usuario $usuario, $nombre_rol)
    {
        if ($usuario->getIdUsuario() === null) {
            return array('Falta saber a qué cuenta asignarle el rol.');
        }

        $sql = 'SELECT id_rol, nombre, descripcion FROM rol WHERE nombre = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('La asignación del rol no se completa.');
        }
        $sentencia->bind_param('s', $nombre_rol);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();

        if ($fila === null) {
            return array('El rol ' . $nombre_rol . ' no figura en el catalogo.');
        }

        $sql = 'INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('La asignación del rol no se completa.');
        }
        $id_usuario = $usuario->getIdUsuario();
        $id_rol     = (int)$fila['id_rol'];
        $sentencia->bind_param('ii', $id_usuario, $id_rol);

        if (!$sentencia->execute()) {
            # 1062: el usuario ya tenia ese rol. No es un error.
            if ($sentencia->errno !== 1062) {
                $sentencia->close();
                return array('El rol no queda asignado.');
            }
        }
        $sentencia->close();

        $usuario->agregarRol(new Rol($fila['id_rol'], $fila['nombre'], $fila['descripcion']));
        return array();
    }

    # --- Busqueda ---------------------------------------------------
    # Devuelve el objeto Usuario o null si no hay ninguno con ese correo.
    public function buscarPorCorreo($correo)
    {
        $sql = 'SELECT id_usuario, correo, hash_password, nombre, apellido,
                       alias, presentacion, activo, fecha_alta,
                       foto_perfil, foto_portada, de_muestra
                FROM usuario
                WHERE correo = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }

        $sentencia->bind_param('s', $correo);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $fila = $resultado->fetch_assoc();
        $sentencia->close();

        if ($fila === null) {
            return null;
        }
        return $this->construirUsuario($fila);
    }

    public function buscarPorId($id_usuario)
    {
        $sql = 'SELECT id_usuario, correo, hash_password, nombre, apellido,
                       alias, presentacion, activo, fecha_alta,
                       foto_perfil, foto_portada, de_muestra
                FROM usuario
                WHERE id_usuario = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }

        $id = (int)$id_usuario;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $fila = $resultado->fetch_assoc();
        $sentencia->close();

        if ($fila === null) {
            return null;
        }
        return $this->construirUsuario($fila);
    }

    # Carga en el objeto los roles que la cuenta tiene asignados.
    # Se hace aparte de buscarPorId porque no siempre hacen falta: el
    # inicio de sesion no los necesita, el perfil si.
    public function cargarRoles(Usuario $usuario)
    {
        if ($usuario->getIdUsuario() === null) {
            return false;
        }

        $sql = 'SELECT r.id_rol, r.nombre, r.descripcion
                FROM rol r
                    INNER JOIN usuario_rol ur ON ur.id_rol = r.id_rol
                WHERE ur.id_usuario = ?
                ORDER BY r.nombre';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return false;
        }

        $id = $usuario->getIdUsuario();
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        while ($fila = $resultado->fetch_assoc()) {
            $usuario->agregarRol(new Rol(
                $fila['id_rol'], $fila['nombre'], $fila['descripcion']));
        }
        $sentencia->close();
        return true;
    }

    # --- Listado para la administracion -------------------------------
    # Todas las cuentas, con sus roles y la fecha de su ultimo inicio de
    # sesion (la ultima fila login_ok de la auditoria, o null si nunca
    # entro). Devuelve un arreglo de arreglos:
    #   array('usuario' => Usuario con roles, 'ultimo_acceso' => fecha)
    # Sin el hash de la clave: la administracion no lo necesita.
    public function listarConRoles()
    {
        $sql = "SELECT u.id_usuario, u.correo, u.nombre, u.apellido, u.alias,
                       u.activo, u.fecha_alta, u.foto_perfil, u.de_muestra,
                       (SELECT MAX(a.fecha_hora) FROM auditoria a
                         WHERE a.id_usuario = u.id_usuario AND a.accion = 'login_ok') AS ultimo_acceso
                FROM usuario u
                ORDER BY u.apellido, u.nombre, u.id_usuario";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $cuentas = array();
        while ($fila = $resultado->fetch_assoc()) {
            $usuario = new Usuario($fila['id_usuario'], $fila['correo'], null,
                                   $fila['nombre'], $fila['apellido'], $fila['alias'],
                                   null, $fila['activo'], $fila['fecha_alta'],
                                   $fila['foto_perfil'], null, $fila['de_muestra']);
            $cuentas[(int)$fila['id_usuario']] = array('usuario' => $usuario,
                                                       'ultimo_acceso' => $fila['ultimo_acceso']);
        }
        $sentencia->close();

        # Los roles de todas, en una sola consulta.
        $sql = 'SELECT ur.id_usuario, r.id_rol, r.nombre, r.descripcion
                FROM usuario_rol ur
                    INNER JOIN rol r ON r.id_rol = ur.id_rol
                ORDER BY r.nombre';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        while ($fila = $resultado->fetch_assoc()) {
            $id = (int)$fila['id_usuario'];
            if (isset($cuentas[$id])) {
                $cuentas[$id]['usuario']->agregarRol(new Rol(
                    $fila['id_rol'], $fila['nombre'], $fila['descripcion']));
            }
        }
        $sentencia->close();

        return array_values($cuentas);
    }

    public function existeCorreo($correo)
    {
        $sql = 'SELECT id_usuario FROM usuario WHERE correo = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return false;
        }
        $sentencia->bind_param('s', $correo);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $hay = ($resultado->fetch_assoc() !== null);
        $sentencia->close();
        return $hay;
    }

    # --- Modificacion -----------------------------------------------
    # Actualiza los datos del perfil. No toca el correo ni la
    # contrasena: el correo identifica la cuenta y la clave se cambia
    # por su propio camino.
    public function actualizarPerfil(Usuario $usuario)
    {
        $errores = $usuario->validar();
        if (!empty($errores)) {
            return $errores;
        }
        if ($usuario->getIdUsuario() === null) {
            return array('Falta saber qué cuenta se actualiza.');
        }

        $sql = 'UPDATE usuario
                SET nombre = ?, apellido = ?, alias = ?, presentacion = ?, activo = ?
                WHERE id_usuario = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('La modificación no se completa.');
        }

        $nombre       = $usuario->getNombre();
        $apellido     = $usuario->getApellido();
        $alias        = $usuario->getAlias();
        $presentacion = $usuario->getPresentacion();
        $activo       = $usuario->getActivo();
        $id           = $usuario->getIdUsuario();

        $sentencia->bind_param('ssssii', $nombre, $apellido, $alias,
                               $presentacion, $activo, $id);

        if (!$sentencia->execute()) {
            if ($sentencia->errno === 1062) {
                $sentencia->close();
                return array('Ese alias ya está en uso.');
            }
            $sentencia->close();
            return array('El perfil no se guarda.');
        }

        $sentencia->close();
        return array();
    }

    # --- Contrasena (tercera entrega) --------------------------------
    # Guarda el hash nuevo de la cuenta. La clave ya paso por
    # Usuario::asignarClave(), que la valido y la convirtio en hash: aca
    # no llega nunca en claro. Solo una cuenta activa.
    public function actualizarClave(Usuario $usuario)
    {
        if ($usuario->getIdUsuario() === null || empty($usuario->getHashPassword())) {
            return array('Falta saber qué cuenta se actualiza.');
        }
        $sql = 'UPDATE usuario SET hash_password = ? WHERE id_usuario = ? AND activo = 1';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('La contraseña no se cambia.');
        }
        $hash = $usuario->getHashPassword();
        $id   = $usuario->getIdUsuario();
        $sentencia->bind_param('si', $hash, $id);
        if (!$sentencia->execute() || $sentencia->affected_rows !== 1) {
            $sentencia->close();
            return array('La contraseña no se cambia.');
        }
        $sentencia->close();
        return array();
    }

    # --- Imagenes -----------------------------------------------------
    # Guarda el nombre de la foto de perfil o de la portada. $tipo es
    # 'foto' o 'portada'. El nombre de la columna no puede viajar como
    # dato de una sentencia preparada, asi que sale de esta lista fija y
    # nunca de lo que mande el formulario.
    # Devuelve true si la fila quedo actualizada.
    public function actualizarImagen(Usuario $usuario, $tipo, $archivo)
    {
        $columnas = array('foto' => 'foto_perfil', 'portada' => 'foto_portada');
        if (!isset($columnas[$tipo]) || $usuario->getIdUsuario() === null) {
            return false;
        }

        $sql = 'UPDATE usuario SET ' . $columnas[$tipo] . ' = ? WHERE id_usuario = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return false;
        }

        $id = $usuario->getIdUsuario();
        $sentencia->bind_param('si', $archivo, $id);
        $bien = $sentencia->execute();
        $sentencia->close();
        return $bien;
    }

    # --- Baja logica ------------------------------------------------
    # La cuenta no se borra: se marca. El usuario de base de datos de la
    # aplicacion ni siquiera tiene permiso de DELETE sobre esta tabla.
    public function darDeBaja(Usuario $usuario)
    {
        $usuario->darDeBaja();
        return $this->actualizarPerfil($usuario);
    }

    # --- Administracion (RF-11 y RF-24) -------------------------------
    # La baja logica de una cuenta, o su vuelta, desde la administracion.
    # Las reglas: la cuenta existe, no es la propia (una administradora
    # no se da de baja a si misma: lo hace otra) y no es de muestra (esas
    # no inician sesion nunca). Con la fila de la cuenta bloqueada. Una
    # cuenta de baja no inicia sesion (RF-07) y, si tenia una abierta, se
    # cierra en el pedido siguiente (cada controlador mira estaActivo()).
    # Su historial queda: torneos, partidos, mediciones y auditoria.
    # Devuelve array('errores' => [...], 'cuenta' => Usuario o null).
    public function cambiarActivo(Usuario $administrador, $id_cuenta, $activar)
    {
        $this->conexion->begin_transaction();
        $cuenta = $this->bloquearCuenta($id_cuenta);
        $error = null;
        if ($cuenta === null) {
            $error = 'Esa cuenta no existe.';
        } elseif ((int)$cuenta->getIdUsuario() === (int)$administrador->getIdUsuario()) {
            $error = 'La cuenta propia no cambia de estado desde acá: lo hace otra cuenta de la administración.';
        } elseif ($cuenta->esDeMuestra()) {
            $error = 'Una cuenta de muestra no cambia de estado.';
        } elseif ($cuenta->estaActivo() === (bool)$activar) {
            $error = $activar ? 'Esa cuenta ya está activa.' : 'Esa cuenta ya está de baja.';
        } else {
            $sentencia = $this->conexion->prepare('UPDATE usuario SET activo = ? WHERE id_usuario = ?');
            $activo = $activar ? 1 : 0;
            $id = (int)$id_cuenta;
            if ($sentencia === false || !$sentencia->bind_param('ii', $activo, $id) || !$sentencia->execute()) {
                $error = 'El estado de la cuenta no se puede cambiar por ahora.';
            }
            if ($sentencia !== false) {
                $sentencia->close();
            }
        }
        if ($error !== null) {
            $this->conexion->rollback();
            return array('errores' => array($error), 'cuenta' => $cuenta);
        }
        $this->conexion->commit();
        return array('errores' => array(), 'cuenta' => $cuenta);
    }

    # Da o quita el rol administrador (las cuentas administrativas,
    # RF-24). Darlo: a una cuenta activa, que no sea de muestra. Quitarlo:
    # nunca a la propia; asi siempre queda al menos una administradora,
    # la que lo quita. Devuelve array('errores' => [...], 'cuenta' =>
    # Usuario o null).
    public function cambiarAdministrador(Usuario $administrador, $id_cuenta, $dar)
    {
        $this->conexion->begin_transaction();
        $cuenta = $this->bloquearCuenta($id_cuenta);
        $error = null;
        if ($cuenta !== null) {
            $this->cargarRoles($cuenta);
        }
        if ($cuenta === null) {
            $error = 'Esa cuenta no existe.';
        } elseif ($dar && (!$cuenta->estaActivo() || $cuenta->esDeMuestra())) {
            $error = 'El rol administrador va solo a una cuenta activa.';
        } elseif ($dar && $cuenta->tieneRol('administrador')) {
            $error = 'Esa cuenta ya es de la administración.';
        } elseif (!$dar && (int)$cuenta->getIdUsuario() === (int)$administrador->getIdUsuario()) {
            $error = 'El rol propio no se quita desde acá: lo hace otra cuenta de la administración.';
        } elseif (!$dar && !$cuenta->tieneRol('administrador')) {
            $error = 'Esa cuenta no es de la administración.';
        } else {
            $sql = $dar ? "INSERT INTO usuario_rol (id_usuario, id_rol) SELECT ?, id_rol FROM rol WHERE nombre = 'administrador'"
                        : "DELETE FROM usuario_rol WHERE id_usuario = ? AND id_rol = (SELECT id_rol FROM rol WHERE nombre = 'administrador')";
            $sentencia = $this->conexion->prepare($sql);
            $id = (int)$id_cuenta;
            if ($sentencia === false || !$sentencia->bind_param('i', $id) || !$sentencia->execute()
                || $sentencia->affected_rows !== 1) {
                $error = 'El rol no se puede cambiar por ahora.';
            }
            if ($sentencia !== false) {
                $sentencia->close();
            }
        }
        if ($error !== null) {
            $this->conexion->rollback();
            return array('errores' => array($error), 'cuenta' => $cuenta);
        }
        $this->conexion->commit();
        return array('errores' => array(), 'cuenta' => $cuenta);
    }

    # La cuenta con ese id, con su fila bloqueada (FOR UPDATE), o null.
    private function bloquearCuenta($id_cuenta)
    {
        $sentencia = $this->conexion->prepare('SELECT id_usuario, correo, hash_password, nombre, apellido,
                                                      alias, presentacion, activo, fecha_alta,
                                                      foto_perfil, foto_portada, de_muestra
                                               FROM usuario WHERE id_usuario = ? FOR UPDATE');
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$id_cuenta;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return ($fila === null) ? null : $this->construirUsuario($fila);
    }

    # --- Auxiliar ---------------------------------------------------
    # Arma el objeto del dominio a partir de una fila de la tabla.
    private function construirUsuario($fila)
    {
        return new Usuario(
            $fila['id_usuario'],
            $fila['correo'],
            $fila['hash_password'],
            $fila['nombre'],
            $fila['apellido'],
            $fila['alias'],
            $fila['presentacion'],
            $fila['activo'],
            $fila['fecha_alta'],
            $fila['foto_perfil'],
            $fila['foto_portada'],
            $fila['de_muestra']
        );
    }

    #endregion
}
