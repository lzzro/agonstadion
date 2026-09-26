<?php
# =====================================================================
# Modelo: TablaPosiciones   ->   la tabla calculada desde los partidos
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La tabla de posiciones de una liga, calculada a partir de los partidos
# jugados: cada resultado suma un partido al local y otro al visitante
# (PosicionTabla::sumarPartido). Es lo mismo que guarda la tabla
# tabla_posiciones, pero sacado de la fuente: sirve para comprobar que
# lo guardado coincide con lo jugado (tests/php/muestra.php) y,
# en la fase 3, para volver a calcularla al cargar un resultado.
#
# El orden lo pone ConfiguracionTorneo::ordenarPosiciones, que conoce
# los puntajes y el criterio de desempate de la liga.
# =====================================================================

require_once __DIR__ . '/PosicionTabla.php';
require_once __DIR__ . '/ConfiguracionTorneo.php';
require_once __DIR__ . '/Ronda.php';

class TablaPosiciones
{
    #region ATRIBUTOS
    private $configuracion;      # objeto ConfiguracionTorneo
    private $posiciones;         # arreglo de PosicionTabla, indexado por id de participante
    #endregion

    #region FUNCIONES

    # Una fila en cero por participante.
    public function __construct(ConfiguracionTorneo $configuracion, $participantes)
    {
        $this->configuracion = $configuracion;
        $this->posiciones    = array();
        foreach ($participantes as $participante) {
            $this->posiciones[(int)$participante->getIdParticipante()] = new PosicionTabla($participante);
        }
    }

    # Suma los partidos jugados de las rondas (objetos Ronda con sus
    # enfrentamientos y resultados). Un libre no suma nada.
    public function sumarRondas($rondas)
    {
        foreach ($rondas as $ronda) {
            foreach ($ronda->getEnfrentamientos() as $enfrentamiento) {
                $resultado = $enfrentamiento->getResultado();
                if ($resultado === null || $enfrentamiento->esLibre()) {
                    continue;
                }
                $id_local     = (int)$enfrentamiento->getLocal()->getIdParticipante();
                $id_visitante = (int)$enfrentamiento->getVisitante()->getIdParticipante();
                if (isset($this->posiciones[$id_local])) {
                    $this->posiciones[$id_local]->sumarPartido(
                        $resultado->getPuntajeLocal(), $resultado->getPuntajeVisitante());
                }
                if (isset($this->posiciones[$id_visitante])) {
                    $this->posiciones[$id_visitante]->sumarPartido(
                        $resultado->getPuntajeVisitante(), $resultado->getPuntajeLocal());
                }
            }
        }
    }

    # Las filas en el orden de la tabla.
    public function getOrdenadas()
    {
        return $this->configuracion->ordenarPosiciones(array_values($this->posiciones));
    }

    #endregion
}
