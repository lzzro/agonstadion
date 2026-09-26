<?php
# =====================================================================
# Modelo: ParticipanteRepositorio   ->   tabla "participante"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para los participantes de una liga:
#   - la lista de una liga, con su equipo (y el capitan, si tiene);
#   - agregar un equipo a mano desde el panel del organizador.
#
# Agregar a mano, por nombre. Si ya hay un equipo con ese nombre:
#   - con capitan: no se agrega; ese equipo entra con su propio pedido
#     (lo decide su capitan, no el organizador);
#   - sin capitan, y solo en ligas de este mismo organizador (o en
#     ninguna): se reusa, asi un organizador anota el mismo equipo en
#     varias ligas suyas;
#   - sin capitan, pero en la liga de otro organizador: no se agrega, el
#     nombre ya lo usa otro equipo.
# Si no hay ninguno, se crea el equipo sin capitan y se lo anota. Todo
# en una transaccion, con la fila del torneo bloqueada (ver
# TorneoRepositorio::bloquearYContar) para respetar el cupo.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Equipo.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/TorneoRepositorio.php';

class ParticipanteRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Los participantes de un torneo, en el orden en que se inscribieron
    # (el orden que usa el fixture). Arreglo indexado por id de
    # participante. Con $con_bajas tambien los dados de baja, que siguen
    # figurando en los partidos que jugaron. null si la consulta no se
    # puede hacer.
    public function listarDeTorneo($id_torneo, $con_bajas = false)
    {
        $sql = "SELECT p.id_participante, p.estado, p.fecha_inscripcion,
                       e.id_equipo, e.nombre AS equipo, e.ciudad,
                       c.id_usuario AS id_capitan, c.nombre AS cap_nombre, c.apellido AS cap_apellido,
                       u.id_usuario, u.nombre AS u_nombre, u.apellido AS u_apellido
                FROM participante p
                    LEFT JOIN equipo e  ON e.id_equipo = p.id_equipo
                    LEFT JOIN usuario c ON c.id_usuario = e.id_usuario_capitan
                    LEFT JOIN usuario u ON u.id_usuario = p.id_usuario
                WHERE p.id_torneo = ?" . ($con_bajas ? '' : " AND p.estado IN ('inscripto', 'confirmado')") . "
                ORDER BY p.id_participante";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$id_torneo;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $participantes = array();
        while ($fila = $resultado->fetch_assoc()) {
            if ($fila['id_equipo'] !== null) {
                $capitan = ($fila['id_capitan'] === null) ? null
                         : new Usuario($fila['id_capitan'], null, null, $fila['cap_nombre'], $fila['cap_apellido']);
                $participante = new Participante($fila['id_participante'], null,
                    new Equipo($fila['id_equipo'], $fila['equipo'], $fila['ciudad'], $capitan),
                    $fila['estado'], $fila['fecha_inscripcion']);
            } else {
                $participante = new Participante($fila['id_participante'],
                    new Usuario($fila['id_usuario'], null, null, $fila['u_nombre'], $fila['u_apellido']),
                    null, $fila['estado'], $fila['fecha_inscripcion']);
            }
            $participantes[(int)$fila['id_participante']] = $participante;
        }
        $sentencia->close();
        return $participantes;
    }

    # El organizador anota un equipo a mano (ver la cabecera). Devuelve
    # un arreglo de errores, vacio si quedo anotado.
    public function agregarEquipo(Torneo $torneo, Usuario $organizador, $nombre)
    {
        $nombre = trim((string)$nombre);
        $equipo_nuevo = new Equipo(null, $nombre);
        $errores = $equipo_nuevo->validar();
        if (!empty($errores)) {
            return $errores;
        }

        $torneos   = new TorneoRepositorio($this->conexion);
        $id_torneo = (int)$torneo->getIdTorneo();
        $id_org    = (int)$organizador->getIdUsuario();

        $this->conexion->begin_transaction();

        $cantidad = $torneos->bloquearYContar($id_torneo, $id_org);
        if ($cantidad === null) {
            $this->conexion->rollback();
            return array('Esa liga no está a cargo de esta cuenta.');
        }
        $actual = $torneos->buscarPorId($id_torneo);
        if ($actual === null || !$actual->tieneInscripcionAbierta()) {
            $this->conexion->rollback();
            return array('La inscripción de esa liga está cerrada.');
        }
        if ($cantidad >= $actual->getMaxParticipantes()) {
            $this->conexion->rollback();
            return array('La liga ya tiene su cupo completo.');
        }

        # ¿Ya hay un equipo con ese nombre?
        $sql = 'SELECT e.id_equipo, e.id_usuario_capitan,
                       (SELECT COUNT(*) FROM participante p
                            INNER JOIN torneo t ON t.id_torneo = p.id_torneo
                        WHERE p.id_equipo = e.id_equipo
                          AND t.id_usuario_organizador <> ?) AS en_ligas_ajenas
                FROM equipo e WHERE e.nombre = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('El equipo no se puede anotar por ahora.');
        }
        $sentencia->bind_param('is', $id_org, $nombre);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();

        if ($fila !== null) {
            if ($fila['id_usuario_capitan'] !== null) {
                $this->conexion->rollback();
                return array('Ese equipo tiene capitán: entra con su propio pedido.');
            }
            if ((int)$fila['en_ligas_ajenas'] > 0) {
                $this->conexion->rollback();
                return array('Ese nombre ya lo usa un equipo de otra liga.');
            }
            $id_equipo = (int)$fila['id_equipo'];
        } else {
            $sql = 'INSERT INTO equipo (nombre) VALUES (?)';
            $sentencia = $this->conexion->prepare($sql);
            if ($sentencia === false) {
                $this->conexion->rollback();
                return array('El equipo no se puede anotar por ahora.');
            }
            $sentencia->bind_param('s', $nombre);
            if (!$sentencia->execute()) {
                $sentencia->close();
                $this->conexion->rollback();
                return array('El equipo no se puede anotar por ahora.');
            }
            $id_equipo = (int)$this->conexion->insert_id;
            $sentencia->close();
        }

        $sql = "INSERT INTO participante (id_torneo, id_equipo, estado) VALUES (?, ?, 'inscripto')";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('El equipo no se puede anotar por ahora.');
        }
        $sentencia->bind_param('ii', $id_torneo, $id_equipo);
        if (!$sentencia->execute()) {
            $duplicado = ($sentencia->errno === 1062);
            $sentencia->close();
            $this->conexion->rollback();
            return array($duplicado ? 'Ese equipo ya juega esta liga.'
                                    : 'El equipo no se puede anotar por ahora.');
        }
        $sentencia->close();

        $this->conexion->commit();
        return array();
    }

    #endregion
}
