<?php
# =====================================================================
# Modelo: EquipoRepositorio   ->   tablas "equipo" e "integrante_equipo"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para los equipos:
#   - un jugador arma su equipo desde el perfil: queda de capitan y de
#     primer integrante, las dos filas en una transaccion;
#   - los equipos que capitanea una cuenta, para pedir lugar en una liga;
#   - buscar un equipo por su nombre, para el panel del organizador.
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
