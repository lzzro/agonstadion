<?php
# =====================================================================
# Modelo: ReporteRepositorio   ->   los numeros de los reportes (RF-28)
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Lo que cuentan los reportes de cada torneo, ademas de lo que ya trae
# TorneoRepositorio (inscriptos, fechas, resultados): los partidos (sin
# los libres ni los anulados), los jugados, los walkovers, los empates y
# los tantos de los partidos con marcador. Una sola consulta, agrupada
# por torneo (GROUP BY), para todos los torneos del reporte.
#
# Nada de esto se guarda: sale cada vez de los partidos, igual que la
# tabla de posiciones.
#
# Siempre con sentencias preparadas.
# =====================================================================

class ReporteRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Los numeros de esos torneos: id de torneo => array('partidos',
    # 'jugados', 'walkovers', 'empates', 'con_marcador', 'tantos'), todos
    # enteros. Un torneo sin fixture no aparece (vale todo 0). null si la
    # consulta no se puede hacer.
    public function partidosDe($ids_torneo)
    {
        $ids = array();
        foreach ($ids_torneo as $id) {
            $ids[] = (int)$id;
        }
        if (empty($ids)) {
            return array();
        }
        $marcas = implode(', ', array_fill(0, count($ids), '?'));
        $sql = "SELECT r.id_torneo,
                       COUNT(*)                                                   AS partidos,
                       SUM(en.estado = 'jugado')                                  AS jugados,
                       SUM(en.estado = 'jugado' AND re.walkover = 1)              AS walkovers,
                       SUM(en.estado = 'jugado' AND re.walkover = 0
                           AND re.id_participante_ganador IS NULL)                AS empates,
                       SUM(en.estado = 'jugado' AND re.walkover = 0)              AS con_marcador,
                       SUM(CASE WHEN en.estado = 'jugado' AND re.walkover = 0
                                THEN re.puntaje_local + re.puntaje_visitante
                                ELSE 0 END)                                       AS tantos
                FROM enfrentamiento en
                    INNER JOIN ronda r      ON r.id_ronda = en.id_ronda
                    LEFT JOIN resultado re  ON re.id_enfrentamiento = en.id_enfrentamiento
                WHERE r.id_torneo IN ($marcas)
                  AND en.id_participante_visitante IS NOT NULL
                  AND en.estado <> 'anulado'
                GROUP BY r.id_torneo";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param(str_repeat('i', count($ids)), ...$ids);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $numeros = array();
        while ($fila = $resultado->fetch_assoc()) {
            $numeros[(int)$fila['id_torneo']] = array(
                'partidos'     => (int)$fila['partidos'],
                'jugados'      => (int)$fila['jugados'],
                'walkovers'    => (int)$fila['walkovers'],
                'empates'      => (int)$fila['empates'],
                'con_marcador' => (int)$fila['con_marcador'],
                'tantos'       => (int)$fila['tantos']
            );
        }
        $sentencia->close();
        return $numeros;
    }

    # Lo que vale para un torneo sin partidos todavia.
    public static function sinPartidos()
    {
        return array('partidos' => 0, 'jugados' => 0, 'walkovers' => 0, 'empates' => 0,
                     'con_marcador' => 0, 'tantos' => 0);
    }

    #endregion
}
