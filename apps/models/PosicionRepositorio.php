<?php
# Modelo: PosicionRepositorio -> tabla "tabla_posiciones"

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
