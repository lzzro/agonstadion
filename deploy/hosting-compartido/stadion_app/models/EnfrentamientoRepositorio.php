<?php
# =====================================================================
# Modelo: EnfrentamientoRepositorio   ->   tabla "enfrentamiento"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Los partidos de todo el sitio, para calendario.php y el inicio: los de
# una semana, y cual es la semana que se muestra primero.
#
# Un partido con dia y hora va en su dia. Uno sin hora todavia ("Horario
# a confirmar") va en el ultimo dia de su fecha, si la fecha tiene dias
# puestos; si no, todavia no esta en el calendario. Solo cuentan los
# torneos publicos (ver TorneoRepositorio), y nunca un partido anulado.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Enfrentamiento.php';
require_once __DIR__ . '/Resultado.php';
require_once __DIR__ . '/Ronda.php';
require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Equipo.php';
require_once __DIR__ . '/TorneoRepositorio.php';

class EnfrentamientoRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Los partidos entre dos dias (AAAA-MM-DD, los dos incluidos), en
    # orden: por dia, los que tienen hora primero y por hora. Cada uno:
    #   'dia'            el dia en que se muestra
    #   'torneo'         el Torneo
    #   'ronda'          la Ronda
    #   'enfrentamiento' el Enfrentamiento, con su Resultado si tiene
    # null si la consulta no se puede hacer.
    public function entreDias($desde, $hasta)
    {
        $sql = "SELECT " . TorneoRepositorio::columnas() . ",
                       r.id_ronda, r.numero AS ronda, r.nombre AS nombre_ronda,
                       r.fecha_inicio AS ronda_inicio, r.fecha_fin AS ronda_fin,
                       r.estado AS estado_ronda,
                       en.id_enfrentamiento, en.numero AS numero_cruce, en.fecha_hora, en.lugar,
                       en.estado AS estado_cruce,
                       en.id_participante_local, en.id_participante_visitante,
                       el.nombre AS equipo_local, ev.nombre AS equipo_visitante,
                       re.puntaje_local, re.puntaje_visitante, re.id_participante_ganador,
                       re.walkover, re.fecha_carga,
                       COALESCE(DATE(en.fecha_hora), r.fecha_fin) AS dia
                FROM enfrentamiento en
                    INNER JOIN ronda r         ON r.id_ronda = en.id_ronda
                    INNER JOIN torneo t        ON t.id_torneo = r.id_torneo
                    " . TorneoRepositorio::uniones() . "
                    INNER JOIN participante pl ON pl.id_participante = en.id_participante_local
                    LEFT JOIN equipo el        ON el.id_equipo = pl.id_equipo
                    LEFT JOIN participante pv  ON pv.id_participante = en.id_participante_visitante
                    LEFT JOIN equipo ev        ON ev.id_equipo = pv.id_equipo
                    LEFT JOIN resultado re     ON re.id_enfrentamiento = en.id_enfrentamiento
                WHERE t.estado IN ('inscripcion', 'en_curso', 'finalizado')
                  AND en.estado <> 'anulado'
                  AND COALESCE(DATE(en.fecha_hora), r.fecha_fin) BETWEEN ? AND ?
                ORDER BY dia, en.fecha_hora IS NULL, en.fecha_hora, t.nombre, en.numero";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('ss', $desde, $hasta);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $partidos = array();
        while ($fila = $resultado->fetch_assoc()) {
            # Los equipos de cada partido, solo con su nombre: es lo que
            # muestra el calendario.
            $local = new Participante($fila['id_participante_local'], null,
                                      new Equipo(null, (string)$fila['equipo_local']));
            $visitante = null;
            if ($fila['id_participante_visitante'] !== null) {
                $visitante = new Participante($fila['id_participante_visitante'], null,
                                              new Equipo(null, (string)$fila['equipo_visitante']));
            }
            $enfrentamiento = new Enfrentamiento($fila['id_enfrentamiento'], $fila['numero_cruce'],
                                                 $local, $visitante, $fila['fecha_hora'],
                                                 $fila['lugar'], $fila['estado_cruce']);
            if ($fila['puntaje_local'] !== null) {
                $ganador = null;
                if ($fila['id_participante_ganador'] !== null) {
                    $ganador = ((int)$fila['id_participante_ganador'] === (int)$fila['id_participante_local'])
                             ? $local : $visitante;
                }
                $enfrentamiento->cargarResultado(new Resultado($fila['puntaje_local'],
                    $fila['puntaje_visitante'], $ganador, $fila['walkover'], null, null,
                    $fila['fecha_carga']));
            }
            $partidos[] = array(
                'dia'            => $fila['dia'],
                'torneo'         => TorneoRepositorio::construirTorneo($fila),
                'ronda'          => new Ronda($fila['id_ronda'], $fila['ronda'], $fila['nombre_ronda'],
                                              $fila['ronda_inicio'], $fila['ronda_fin'], $fila['estado_ronda']),
                'enfrentamiento' => $enfrentamiento
            );
        }
        $sentencia->close();
        return $partidos;
    }

    # El dia que decide que semana muestra primero el calendario: el de
    # un partido en vivo, o si no hay, el del proximo partido desde
    # $ahora (AAAA-MM-DD HH:MM:SS). null si no hay ninguno de los dos.
    public function diaDestacado($ahora)
    {
        $sql = "SELECT DATE(en.fecha_hora) AS dia
                FROM enfrentamiento en
                    INNER JOIN ronda r  ON r.id_ronda = en.id_ronda
                    INNER JOIN torneo t ON t.id_torneo = r.id_torneo
                WHERE t.estado IN ('inscripcion', 'en_curso', 'finalizado')
                  AND en.fecha_hora IS NOT NULL
                  AND (en.estado = 'en_vivo' OR (en.estado = 'programado' AND en.fecha_hora >= ?))
                ORDER BY en.estado = 'en_vivo' DESC, en.fecha_hora
                LIMIT 1";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('s', $ahora);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return ($fila === null) ? null : $fila['dia'];
    }

    #endregion
}
