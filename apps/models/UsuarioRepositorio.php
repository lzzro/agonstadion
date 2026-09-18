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
            return array('Falta saber a que cuenta asignarle el rol.');
        }

        $sql = 'SELECT id_rol, nombre, descripcion FROM rol WHERE nombre = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('La asignacion del rol no se completa.');
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
            return array('La asignacion del rol no se completa.');
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
                       alias, presentacion, activo, fecha_alta
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
                       alias, presentacion, activo, fecha_alta
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
            return array('Falta saber que cuenta se actualiza.');
        }

        $sql = 'UPDATE usuario
                SET nombre = ?, apellido = ?, alias = ?, presentacion = ?, activo = ?
                WHERE id_usuario = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('La modificacion no se completa.');
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
                return array('Ese alias ya esta en uso.');
            }
            $sentencia->close();
            return array('El perfil no se guarda.');
        }

        $sentencia->close();
        return array();
    }

    # --- Baja logica ------------------------------------------------
    # La cuenta no se borra: se marca. El usuario de base de datos de la
    # aplicacion ni siquiera tiene permiso de DELETE sobre esta tabla.
    public function darDeBaja(Usuario $usuario)
    {
        $usuario->darDeBaja();
        return $this->actualizarPerfil($usuario);
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
            $fila['fecha_alta']
        );
    }

    #endregion
}
