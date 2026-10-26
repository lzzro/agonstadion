<?php
# Modelo: EnfrentamientoRepositorio -> tabla "enfrentamiento"

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

    public function entreDias($desde, $hasta)
    {
        $sql = self::consulta() . "
                WHERE t.estado IN ('inscripcion', 'en_curso', 'finalizado')
                  AND en.estado <> 'anulado'
                  AND COALESCE(DATE(en.fecha_hora), r.fecha_fin) BETWEEN ? AND ?
                ORDER BY dia, en.fecha_hora IS NULL, en.fecha_hora, t.nombre, en.numero";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('ss', $desde, $hasta);
        return $this->leer($sentencia);
    }

    public function jugadosDe($ids_participante)
    {
        $ids = array();
        foreach ($ids_participante as $id) {
            $ids[] = (int)$id;
        }
        if (empty($ids)) {
            return array();
        }
        $marcas = implode(', ', array_fill(0, count($ids), '?'));
        $sql = self::consulta() . "
                WHERE t.estado <> 'borrador'
                  AND en.estado = 'jugado'
                  AND en.id_participante_visitante IS NOT NULL
                  AND (en.id_participante_local IN ($marcas) OR en.id_participante_visitante IN ($marcas))
                ORDER BY COALESCE(en.fecha_hora, re.fecha_carga) DESC, en.id_enfrentamiento DESC";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $valores = array_merge($ids, $ids);
        $sentencia->bind_param(str_repeat('i', count($valores)), ...$valores);
        $partidos = $this->leer($sentencia);
        if ($partidos === null) {
            return null;
        }
        foreach ($partidos as $i => $partido) {
            $local = $partido['enfrentamiento']->getLocal();
            $partidos[$i]['propio'] = in_array((int)$local->getIdParticipante(), $ids, true)
                                    ? $local : $partido['enfrentamiento']->getVisitante();
        }
        return $partidos;
    }

    private static function consulta()
    {
        return "SELECT " . TorneoRepositorio::columnas() . ",
                       r.id_ronda, r.numero AS ronda, r.nombre AS nombre_ronda,
                       r.fecha_inicio AS ronda_inicio, r.fecha_fin AS ronda_fin,
                       r.estado AS estado_ronda,
                       en.id_enfrentamiento, en.numero AS numero_cruce, en.fecha_hora, en.lugar,
                       en.estado AS estado_cruce,
                       en.id_participante_local, en.id_participante_visitante,
                       el.nombre AS equipo_local, ev.nombre AS equipo_visitante,
                       ul.nombre AS local_nombre, ul.apellido AS local_apellido,
                       uv.nombre AS visitante_nombre, uv.apellido AS visitante_apellido,
                       re.puntaje_local, re.puntaje_visitante, re.id_participante_ganador,
                       re.walkover, re.fecha_carga,
                       COALESCE(DATE(en.fecha_hora), r.fecha_fin) AS dia
                FROM enfrentamiento en
                    INNER JOIN ronda r         ON r.id_ronda = en.id_ronda
                    INNER JOIN torneo t        ON t.id_torneo = r.id_torneo
                    " . TorneoRepositorio::uniones() . "
                    INNER JOIN participante pl ON pl.id_participante = en.id_participante_local
                    LEFT JOIN equipo el        ON el.id_equipo = pl.id_equipo
                    LEFT JOIN usuario ul       ON ul.id_usuario = pl.id_usuario
                    LEFT JOIN participante pv  ON pv.id_participante = en.id_participante_visitante
                    LEFT JOIN equipo ev        ON ev.id_equipo = pv.id_equipo
                    LEFT JOIN usuario uv       ON uv.id_usuario = pv.id_usuario
                    LEFT JOIN resultado re     ON re.id_enfrentamiento = en.id_enfrentamiento";
    }

    # Corre la sentencia ya preparada y arma cada partido.
    private function leer($sentencia)
    {
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $partidos = array();
        while ($fila = $resultado->fetch_assoc()) {
            # Los de cada partido, solo con su nombre: es lo que muestra el calendario.
            $local = self::participanteDe($fila['id_participante_local'], $fila['equipo_local'],
                                          $fila['local_nombre'], $fila['local_apellido']);
            $visitante = null;
            if ($fila['id_participante_visitante'] !== null) {
                $visitante = self::participanteDe($fila['id_participante_visitante'], $fila['equipo_visitante'],
                                                  $fila['visitante_nombre'], $fila['visitante_apellido']);
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

    # Un participante con solo su nombre: el de su equipo, o el de la persona si compite sola.
    private static function participanteDe($id, $equipo, $nombre, $apellido)
    {
        if ($equipo === null && $nombre !== null) {
            return new Participante($id, new Usuario(null, null, null, $nombre, $apellido), null);
        }
        return new Participante($id, null, new Equipo(null, (string)$equipo));
    }

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
