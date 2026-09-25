<?php
# =====================================================================
# Modelo: TorneoRepositorio   ->   tablas "torneo" y "participante"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para los torneos. Por ahora sabe una sola cosa: en
# que torneos compite una persona, que es lo que muestra la pestana
# "Mis torneos" del perfil.
#
# Una persona compite en un torneo de dos maneras, segun el tipo:
#   - individual: hay un participante con su id_usuario
#   - por equipos: hay un participante con el id de un equipo del que
#     la persona es integrante activa (tabla integrante_equipo)
# La consulta junta los dos casos. Las inscripciones dadas de baja no
# cuentan: quien se bajo de un torneo ya no esta en el.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Torneo.php';
require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Equipo.php';
require_once __DIR__ . '/Usuario.php';

class TorneoRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Devuelve un arreglo de inscripciones, cada una con dos objetos:
    #   'torneo'       el Torneo, con su disciplina, tipo, modulo y
    #                  organizador
    #   'participante' como compite la persona en ese torneo (ella
    #                  sola, o su equipo)
    # No se usa agregarParticipante() de Torneo porque esa funcion es la
    # de una inscripcion nueva, y rechaza los torneos que ya empezaron.
    # Si la consulta no se puede hacer, devuelve null (distinto de un
    # arreglo vacio, que quiere decir "ningun torneo").
    public function buscarPorUsuario($id_usuario)
    {
        $sql = 'SELECT t.id_torneo, t.nombre, t.fecha_inicio, t.fecha_fin,
                       t.max_participantes, t.sede, t.estado, t.fecha_creacion,
                       d.id_disciplina, d.nombre AS disciplina,
                       tt.id_tipo_torneo, tt.nombre AS tipo, tt.compite_equipo,
                       m.id_modulo, m.nombre AS modulo,
                       o.id_usuario AS id_organizador, o.nombre AS org_nombre,
                       o.apellido AS org_apellido,
                       p.id_participante, p.estado AS estado_participante,
                       p.fecha_inscripcion,
                       e.id_equipo, e.nombre AS equipo
                FROM participante p
                    INNER JOIN torneo t              ON t.id_torneo = p.id_torneo
                    INNER JOIN disciplina d          ON d.id_disciplina = t.id_disciplina
                    INNER JOIN tipo_torneo tt        ON tt.id_tipo_torneo = t.id_tipo_torneo
                    INNER JOIN modulo_competencia m  ON m.id_modulo = t.id_modulo
                    INNER JOIN usuario o             ON o.id_usuario = t.id_usuario_organizador
                    LEFT JOIN equipo e               ON e.id_equipo = p.id_equipo
                WHERE p.estado <> \'baja\'
                  AND (p.id_usuario = ?
                       OR p.id_equipo IN (SELECT ie.id_equipo
                                          FROM integrante_equipo ie
                                          WHERE ie.id_usuario = ? AND ie.activo = 1))
                ORDER BY t.fecha_inicio DESC, t.nombre';

        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }

        $id = (int)$id_usuario;
        $sentencia->bind_param('ii', $id, $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $inscripciones = array();
        while ($fila = $resultado->fetch_assoc()) {
            $organizador = new Usuario($fila['id_organizador'], null, null,
                                       $fila['org_nombre'], $fila['org_apellido']);
            $torneo = new Torneo(
                $fila['id_torneo'],
                $fila['nombre'],
                new Disciplina($fila['id_disciplina'], $fila['disciplina']),
                new TipoTorneo($fila['id_tipo_torneo'], $fila['tipo'], $fila['compite_equipo']),
                new ModuloCompetencia($fila['id_modulo'], $fila['modulo']),
                $organizador,
                $fila['fecha_inicio'],
                $fila['fecha_fin'],
                $fila['max_participantes'],
                $fila['sede'],
                $fila['estado'],
                $fila['fecha_creacion']
            );

            # Compite su equipo, o compite la persona sola.
            if ($fila['id_equipo'] !== null) {
                $participante = new Participante($fila['id_participante'], null,
                    new Equipo($fila['id_equipo'], $fila['equipo']),
                    $fila['estado_participante'], $fila['fecha_inscripcion']);
            } else {
                $participante = new Participante($fila['id_participante'],
                    new Usuario($id, null, null, null, null), null,
                    $fila['estado_participante'], $fila['fecha_inscripcion']);
            }

            $inscripciones[] = array('torneo' => $torneo, 'participante' => $participante);
        }
        $sentencia->close();

        return $inscripciones;
    }

    #endregion
}
