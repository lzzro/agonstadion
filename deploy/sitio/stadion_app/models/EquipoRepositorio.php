<?php
# =====================================================================
# Modelo: EquipoRepositorio   ->   tablas "equipo" e "integrante_equipo"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para los equipos:
#   - un jugador arma su equipo desde el perfil: queda de capitan y de
#     primer integrante, las dos filas en una transaccion;
#   - los equipos que capitanea una cuenta, para pedir lugar en una liga;
#   - buscar un equipo por su nombre, para el panel del organizador;
#   - los integrantes con su dorsal (RF-12): el capitan suma una cuenta
#     por su correo, le cambia el dorsal o la da de baja (logica: la
#     fila queda, con activo = 0 y sin dorsal, asi el numero queda libre).
#     Solo el capitan, comprobado con la fila del equipo bloqueada.
#
# El nombre de un equipo es unico en toda la base (uq_equipo_nom), y el
# cotejo de la tabla no distingue mayusculas ni tildes: "Los Pumas" y
# "los pumas" son el mismo nombre.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Equipo.php';
require_once __DIR__ . '/Usuario.php';

class EquipoRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Guarda el equipo con su capitan, que queda ademas como integrante.
    # Devuelve un arreglo de errores, vacio si quedo creado; el id queda
    # en el objeto.
    public function crearConCapitan(Equipo $equipo)
    {
        $errores = $equipo->validar();
        $capitan = $equipo->getCapitan();
        if ($capitan === null) {
            $errores[] = 'El equipo necesita un capitán.';
        }
        if (!empty($errores)) {
            return $errores;
        }

        $this->conexion->begin_transaction();

        $sql = 'INSERT INTO equipo (nombre, ciudad, id_usuario_capitan) VALUES (?, ?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('El equipo no se puede crear por ahora.');
        }
        $nombre     = $equipo->getNombre();
        $ciudad     = ($equipo->getCiudad() === '') ? null : $equipo->getCiudad();
        $id_capitan = (int)$capitan->getIdUsuario();
        $sentencia->bind_param('ssi', $nombre, $ciudad, $id_capitan);
        if (!$sentencia->execute()) {
            $duplicado = ($sentencia->errno === 1062);
            $sentencia->close();
            $this->conexion->rollback();
            return array($duplicado ? 'Ya hay un equipo con ese nombre.'
                                    : 'El equipo no se puede crear por ahora.');
        }
        $equipo->setIdEquipo($this->conexion->insert_id);
        $sentencia->close();

        $sql = 'INSERT INTO integrante_equipo (id_equipo, id_usuario) VALUES (?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('El equipo no se puede crear por ahora.');
        }
        $id_equipo = (int)$equipo->getIdEquipo();
        $sentencia->bind_param('ii', $id_equipo, $id_capitan);
        if (!$sentencia->execute()) {
            $sentencia->close();
            $this->conexion->rollback();
            return array('El equipo no se puede crear por ahora.');
        }
        $sentencia->close();

        $this->conexion->commit();
        return array();
    }

    # Los equipos activos que capitanea una cuenta, por nombre. null si
    # la consulta no se puede hacer.
    public function listarDeCapitan(Usuario $capitan)
    {
        $sql = 'SELECT id_equipo, nombre, ciudad, activo, fecha_alta
                FROM equipo
                WHERE id_usuario_capitan = ? AND activo = 1
                ORDER BY nombre';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$capitan->getIdUsuario();
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $equipos = array();
        while ($fila = $resultado->fetch_assoc()) {
            $equipos[] = new Equipo($fila['id_equipo'], $fila['nombre'], $fila['ciudad'],
                                    $capitan, $fila['activo'], $fila['fecha_alta']);
        }
        $sentencia->close();
        return $equipos;
    }

    # --- Integrantes (RF-12) -------------------------------------------
    # Los integrantes activos de un equipo, cada uno con su cuenta (solo
    # el nombre) y su dorsal: primero el capitan, despues por dorsal y por
    # apellido. null si la consulta no se puede hacer.
    public function integrantesDe(Equipo $equipo)
    {
        $sql = 'SELECT ie.id_usuario, ie.dorsal, ie.activo, ie.fecha_alta, u.nombre, u.apellido
                FROM integrante_equipo ie
                    INNER JOIN usuario u ON u.id_usuario = ie.id_usuario
                    INNER JOIN equipo e  ON e.id_equipo = ie.id_equipo
                WHERE ie.id_equipo = ? AND ie.activo = 1
                ORDER BY ie.id_usuario = e.id_usuario_capitan DESC, ie.dorsal IS NULL, ie.dorsal, u.apellido, u.nombre';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$equipo->getIdEquipo();
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $lista = array();
        while ($fila = $resultado->fetch_assoc()) {
            $lista[] = new IntegranteEquipo(new Usuario($fila['id_usuario'], null, null, $fila['nombre'], $fila['apellido']),
                                            $fila['dorsal'], $fila['activo'], $fila['fecha_alta']);
        }
        $sentencia->close();
        return $lista;
    }

    # Los equipos activos que una cuenta integra sin ser su capitan, cada
    # uno como array('equipo' => Equipo, 'dorsal' => numero o null).
    # null si la consulta no se puede hacer.
    public function listarDeIntegrante(Usuario $usuario)
    {
        $sql = 'SELECT e.id_equipo, e.nombre, e.ciudad, e.activo, e.fecha_alta, ie.dorsal,
                       c.id_usuario AS id_capitan, c.nombre AS cap_nombre, c.apellido AS cap_apellido
                FROM integrante_equipo ie
                    INNER JOIN equipo e   ON e.id_equipo = ie.id_equipo
                    LEFT JOIN usuario c   ON c.id_usuario = e.id_usuario_capitan
                WHERE ie.id_usuario = ? AND ie.activo = 1 AND e.activo = 1
                  AND (e.id_usuario_capitan IS NULL OR e.id_usuario_capitan <> ie.id_usuario)
                ORDER BY e.nombre';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$usuario->getIdUsuario();
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $lista = array();
        while ($fila = $resultado->fetch_assoc()) {
            $capitan = ($fila['id_capitan'] === null) ? null
                     : new Usuario($fila['id_capitan'], null, null, $fila['cap_nombre'], $fila['cap_apellido']);
            $lista[] = array('equipo' => new Equipo($fila['id_equipo'], $fila['nombre'], $fila['ciudad'], $capitan,
                                                    $fila['activo'], $fila['fecha_alta']),
                             'dorsal' => ($fila['dorsal'] === null) ? null : (int)$fila['dorsal']);
        }
        $sentencia->close();
        return $lista;
    }

    # Suma a un equipo la cuenta de ese correo, con su dorsal (o sin
    # dorsal: null). Solo el capitan. Una cuenta que ya integro el equipo
    # y quedo de baja vuelve. Devuelve array('errores' => [...], 'nombre'
    # => el nombre de la persona sumada).
    public function sumarIntegrante($id_equipo, Usuario $capitan, $correo, $dorsal)
    {
        $nuevo = new IntegranteEquipo(new Usuario(null, $correo, null, null, null), $dorsal);
        $errores = $nuevo->validar();
        if (!empty($errores)) {
            return array('errores' => $errores, 'nombre' => null);
        }
        $this->conexion->begin_transaction();
        $error = $this->bloquearComoCapitan($id_equipo, $capitan);
        if ($error !== null) {
            $this->conexion->rollback();
            return array('errores' => array($error), 'nombre' => null);
        }
        # La cuenta: activa y que no sea de muestra. Un correo que no
        # es de nadie y uno de una cuenta de baja dan el mismo aviso.
        $sentencia = $this->conexion->prepare('SELECT id_usuario, nombre, apellido FROM usuario
                                               WHERE correo = ? AND activo = 1 AND de_muestra = 0');
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('errores' => array('El integrante no se puede sumar por ahora.'), 'nombre' => null);
        }
        $sentencia->bind_param('s', $correo);
        $sentencia->execute();
        $cuenta = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        if ($cuenta === null) {
            $this->conexion->rollback();
            return array('errores' => array('Ninguna cuenta activa con ese correo.'), 'nombre' => null);
        }
        $id_usuario = (int)$cuenta['id_usuario'];
        $nombre = (new Usuario($id_usuario, null, null, $cuenta['nombre'], $cuenta['apellido']))->getNombreCompletoVisible();

        $fila = $this->filaIntegrante($id_equipo, $id_usuario);
        if ($fila !== null && (int)$fila['activo'] === 1) {
            $this->conexion->rollback();
            return array('errores' => array($nombre . ' ya integra el equipo.'), 'nombre' => null);
        }
        if ($dorsal !== null && $this->dorsalEnUso($id_equipo, $dorsal, $id_usuario)) {
            $this->conexion->rollback();
            return array('errores' => array('El dorsal ' . (int)$dorsal . ' ya está ocupado en este equipo.'), 'nombre' => null);
        }
        if ($fila === null) {
            $sql = 'INSERT INTO integrante_equipo (id_equipo, id_usuario, dorsal) VALUES (?, ?, ?)';
        } else {
            $sql = 'UPDATE integrante_equipo SET activo = 1, fecha_alta = NOW()
                    WHERE id_equipo = ? AND id_usuario = ?';
        }
        $sentencia = $this->conexion->prepare($sql);
        $equipo = (int)$id_equipo;
        $bien = ($sentencia !== false);
        if ($bien && $fila === null) {
            $bien = $sentencia->bind_param('iii', $equipo, $id_usuario, $dorsal) && $sentencia->execute();
        } elseif ($bien) {
            $bien = $sentencia->bind_param('ii', $equipo, $id_usuario) && $sentencia->execute()
                    && $this->ponerDorsal($equipo, $id_usuario, $dorsal);
        }
        if ($sentencia !== false) {
            $sentencia->close();
        }
        if (!$bien) {
            $this->conexion->rollback();
            return array('errores' => array('El integrante no se puede sumar por ahora.'), 'nombre' => null);
        }
        $this->conexion->commit();
        return array('errores' => array(), 'nombre' => $nombre);
    }

    # Cambia el dorsal de un integrante activo (tambien el del capitan).
    # null lo deja sin dorsal. Devuelve un arreglo de errores.
    public function cambiarDorsal($id_equipo, Usuario $capitan, $id_usuario, $dorsal)
    {
        $nuevo = new IntegranteEquipo(new Usuario($id_usuario, null, null, null, null), $dorsal);
        $errores = $nuevo->validar();
        if (!empty($errores)) {
            return $errores;
        }
        $this->conexion->begin_transaction();
        $error = $this->bloquearComoCapitan($id_equipo, $capitan);
        if ($error === null) {
            $fila = $this->filaIntegrante($id_equipo, $id_usuario);
            if ($fila === null || (int)$fila['activo'] !== 1) {
                $error = 'Esa cuenta no integra el equipo.';
            } elseif ($dorsal !== null && $this->dorsalEnUso($id_equipo, $dorsal, $id_usuario)) {
                $error = 'El dorsal ' . (int)$dorsal . ' ya está ocupado en este equipo.';
            } elseif (!$this->ponerDorsal($id_equipo, $id_usuario, $dorsal)) {
                $error = 'El dorsal no se puede cambiar por ahora.';
            }
        }
        if ($error !== null) {
            $this->conexion->rollback();
            return array($error);
        }
        $this->conexion->commit();
        return array();
    }

    # La baja de un integrante: queda la fila, con activo = 0 y sin
    # dorsal. El capitan no se da de baja de su propio equipo.
    public function bajaIntegrante($id_equipo, Usuario $capitan, $id_usuario)
    {
        $this->conexion->begin_transaction();
        $error = $this->bloquearComoCapitan($id_equipo, $capitan);
        if ($error === null) {
            $fila = $this->filaIntegrante($id_equipo, $id_usuario);
            if ((int)$id_usuario === (int)$capitan->getIdUsuario()) {
                $error = 'El capitán no se da de baja de su equipo.';
            } elseif ($fila === null || (int)$fila['activo'] !== 1) {
                $error = 'Esa cuenta no integra el equipo.';
            } else {
                $sentencia = $this->conexion->prepare('UPDATE integrante_equipo SET activo = 0, dorsal = NULL
                                                       WHERE id_equipo = ? AND id_usuario = ?');
                $equipo = (int)$id_equipo;
                $usuario = (int)$id_usuario;
                if ($sentencia === false || !$sentencia->bind_param('ii', $equipo, $usuario) || !$sentencia->execute()) {
                    $error = 'La baja no se puede hacer por ahora.';
                }
                if ($sentencia !== false) {
                    $sentencia->close();
                }
            }
        }
        if ($error !== null) {
            $this->conexion->rollback();
            return array($error);
        }
        $this->conexion->commit();
        return array();
    }

    # Bloquea la fila del equipo (FOR UPDATE) y mira que sea un equipo
    # activo capitaneado por esa cuenta. null si lo es; si no, el aviso.
    private function bloquearComoCapitan($id_equipo, Usuario $capitan)
    {
        $sentencia = $this->conexion->prepare('SELECT id_usuario_capitan, activo FROM equipo WHERE id_equipo = ? FOR UPDATE');
        if ($sentencia === false) {
            return 'El equipo no se puede leer por ahora.';
        }
        $id = (int)$id_equipo;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        if ($fila === null || (int)$fila['activo'] !== 1 || $fila['id_usuario_capitan'] === null
            || (int)$fila['id_usuario_capitan'] !== (int)$capitan->getIdUsuario()) {
            return 'Ese equipo no lo capitanea esta cuenta.';
        }
        return null;
    }

    # La fila de esa cuenta en ese equipo (activa o de baja), o null.
    private function filaIntegrante($id_equipo, $id_usuario)
    {
        $sentencia = $this->conexion->prepare('SELECT activo, dorsal FROM integrante_equipo WHERE id_equipo = ? AND id_usuario = ?');
        if ($sentencia === false) {
            return null;
        }
        $equipo = (int)$id_equipo;
        $usuario = (int)$id_usuario;
        $sentencia->bind_param('ii', $equipo, $usuario);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return $fila;
    }

    # Si otro integrante del equipo ya tiene ese dorsal.
    private function dorsalEnUso($id_equipo, $dorsal, $id_usuario)
    {
        $sentencia = $this->conexion->prepare('SELECT COUNT(*) AS cuantos FROM integrante_equipo
                                               WHERE id_equipo = ? AND dorsal = ? AND id_usuario <> ?');
        if ($sentencia === false) {
            return true;
        }
        $equipo = (int)$id_equipo;
        $numero = (int)$dorsal;
        $usuario = (int)$id_usuario;
        $sentencia->bind_param('iii', $equipo, $numero, $usuario);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return (int)$fila['cuantos'] > 0;
    }

    private function ponerDorsal($id_equipo, $id_usuario, $dorsal)
    {
        $sentencia = $this->conexion->prepare('UPDATE integrante_equipo SET dorsal = ? WHERE id_equipo = ? AND id_usuario = ?');
        if ($sentencia === false) {
            return false;
        }
        $equipo = (int)$id_equipo;
        $usuario = (int)$id_usuario;
        $bien = $sentencia->bind_param('iii', $dorsal, $equipo, $usuario) && $sentencia->execute();
        $sentencia->close();
        return $bien;
    }

    # El equipo con ese nombre (sin mirar mayusculas ni tildes), con su
    # capitan si tiene, o null.
    public function buscarPorNombre($nombre)
    {
        $sql = 'SELECT e.id_equipo, e.nombre, e.ciudad, e.activo, e.fecha_alta,
                       u.id_usuario, u.nombre AS cap_nombre, u.apellido AS cap_apellido
                FROM equipo e
                    LEFT JOIN usuario u ON u.id_usuario = e.id_usuario_capitan
                WHERE e.nombre = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('s', $nombre);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        if ($fila === null) {
            return null;
        }
        $capitan = ($fila['id_usuario'] === null) ? null
                 : new Usuario($fila['id_usuario'], null, null, $fila['cap_nombre'], $fila['cap_apellido']);
        return new Equipo($fila['id_equipo'], $fila['nombre'], $fila['ciudad'], $capitan,
                          $fila['activo'], $fila['fecha_alta']);
    }

    #endregion
}
