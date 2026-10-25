<?php
# =====================================================================
# Modelo: PosicionRepositorio   ->   tabla "tabla_posiciones"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Lee la tabla de posiciones guardada de una liga. La tabla solo guarda
# los contadores (ganados, empatados, perdidos, a favor, en contra):
# los puntos, la diferencia y el puesto se calculan al mostrarla, con
# los puntajes y el desempate de la liga (ver schema.sql, seccion 8).
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/PosicionTabla.php';
require_once __DIR__ . '/ConfiguracionTorneo.php';

class PosicionRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Las filas de la liga, ya ordenadas. $participantes es el arreglo de
    # ParticipanteRepositorio::listarDeTorneo: las filas usan esos mismos
    # objetos. Solo cuentan los participantes que estan en el arreglo.
    # null si la consulta no se puede hacer.
    public function tablaDe($id_torneo, $participantes, ConfiguracionTorneo $configuracion)
    {
        $sql = 'SELECT tp.id_participante, tp.ganados, tp.empatados, tp.perdidos,
                       tp.favor, tp.contra, tp.fecha_actualizacion
                FROM tabla_posiciones tp
                    INNER JOIN participante p ON p.id_participante = tp.id_participante
                WHERE p.id_torneo = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$id_torneo;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $filas = array();
        while ($fila = $resultado->fetch_assoc()) {
            $id_participante = (int)$fila['id_participante'];
            if (!isset($participantes[$id_participante])) {
                continue;
            }
            $filas[] = new PosicionTabla($participantes[$id_participante], $fila['ganados'],
                                         $fila['empatados'], $fila['perdidos'], $fila['favor'],
                                         $fila['contra'], $fila['fecha_actualizacion']);
        }
        $sentencia->close();
        return $configuracion->ordenarPosiciones($filas);
    }

    #endregion
}
