<?php
# Modelo: TablaPosiciones -> la tabla calculada desde los partidos

require_once __DIR__ . '/PosicionTabla.php';
require_once __DIR__ . '/ConfiguracionTorneo.php';
require_once __DIR__ . '/Ronda.php';

class TablaPosiciones
{
    #region ATRIBUTOS
    private $configuracion;      # objeto ConfiguracionTorneo
    private $posiciones;
    private $libre_suma;         # true en el suizo
    #endregion

    #region FUNCIONES

    # Una fila en cero por participante.
    public function __construct(ConfiguracionTorneo $configuracion, $participantes, $libre_suma = false)
    {
        $this->configuracion = $configuracion;
        $this->libre_suma    = (bool)$libre_suma;
        $this->posiciones    = array();
        foreach ($participantes as $participante) {
            $this->posiciones[(int)$participante->getIdParticipante()] = new PosicionTabla($participante);
        }
    }

    # Suma los partidos jugados de las rondas (objetos Ronda con sus enfrentamientos y resultados).
    public function sumarRondas($rondas)
    {
        foreach ($rondas as $ronda) {
            foreach ($ronda->getEnfrentamientos() as $enfrentamiento) {
                $resultado = $enfrentamiento->getResultado();
                if ($enfrentamiento->esLibre()) {
                    $id_libre = (int)$enfrentamiento->getLocal()->getIdParticipante();
                    if ($this->libre_suma && isset($this->posiciones[$id_libre])) {
                        $this->posiciones[$id_libre]->sumarResultado(0, 0, 'G');
                    }
                    continue;
                }
                if ($resultado === null || !$enfrentamiento->estaJugado()) {
                    continue;
                }
                $local        = $enfrentamiento->getLocal();
                $visitante    = $enfrentamiento->getVisitante();
                $id_local     = (int)$local->getIdParticipante();
                $id_visitante = (int)$visitante->getIdParticipante();

                # El desenlace de cada lado, por el ganador.
                $ganador = $resultado->getGanador();
                if ($ganador === null) {
                    $del_local = 'E';
                    $del_visitante = 'E';
                } elseif ((int)$ganador->getIdParticipante() === $id_local) {
                    $del_local = 'G';
                    $del_visitante = 'P';
                } else {
                    $del_local = 'P';
                    $del_visitante = 'G';
                }

                if (isset($this->posiciones[$id_local])) {
                    $this->posiciones[$id_local]->sumarResultado(
                        $resultado->getPuntajeLocal(), $resultado->getPuntajeVisitante(), $del_local);
                }
                if (isset($this->posiciones[$id_visitante])) {
                    $this->posiciones[$id_visitante]->sumarResultado(
                        $resultado->getPuntajeVisitante(), $resultado->getPuntajeLocal(), $del_visitante);
                }
            }
        }
    }

    # Las filas por id de participante, sin ordenar: lo que se guarda en tabla_posiciones.
    public function getPosiciones()
    {
        return $this->posiciones;
    }

    # Las filas en el orden de la tabla.
    public function getOrdenadas()
    {
        return $this->configuracion->ordenarPosiciones(array_values($this->posiciones));
    }

    #endregion
}
