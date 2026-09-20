<?php
# =====================================================================
# Modelo: PosicionTabla   ->   tabla "tabla_posiciones" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acumulado de un participante en su torneo. Composicion: contiene el
# objeto Participante al que pertenece la fila.
#
# Esta clase es la contracara de la decision de normalizacion del
# schema: la base guarda SOLO los contadores, y los datos derivados
# (partidos jugados, diferencia, puntos) se calculan aca. Los puntos
# necesitan la ConfiguracionTorneo, porque dependen de cuanto vale una
# victoria en ese torneo.
#
# El puesto no es atributo de esta clase ni columna de la tabla: no
# depende del participante sino de como les fue a todos los demas, asi
# que sale de ordenar la lista con
# ConfiguracionTorneo->ordenarPosiciones().
# =====================================================================

require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/ConfiguracionTorneo.php';

class PosicionTabla
{
    #region ATRIBUTOS
    private $participante;       # objeto Participante
    private $ganados;
    private $empatados;
    private $perdidos;
    private $favor;
    private $contra;
    private $fecha_actualizacion;
    #endregion

    #region FUNCIONES

    public function __construct(Participante $participante, $ganados = 0, $empatados = 0,
                                $perdidos = 0, $favor = 0, $contra = 0,
                                $fecha_actualizacion = null)
    {
        $this->participante        = $participante;
        $this->ganados             = (int)$ganados;
        $this->empatados           = (int)$empatados;
        $this->perdidos            = (int)$perdidos;
        $this->favor               = (int)$favor;
        $this->contra              = (int)$contra;
        $this->fecha_actualizacion = $fecha_actualizacion;
    }

    public function getParticipante()       { return $this->participante; }
    public function getGanados()            { return $this->ganados; }
    public function getEmpatados()          { return $this->empatados; }
    public function getPerdidos()           { return $this->perdidos; }
    public function getFavor()              { return $this->favor; }
    public function getContra()             { return $this->contra; }
    public function getFechaActualizacion() { return $this->fecha_actualizacion; }

    public function getNombreVisible()
    {
        return $this->participante->getNombreVisible();
    }

    # --- Datos derivados: no se guardan en la base ---

    public function getPartidosJugados()
    {
        return $this->ganados + $this->empatados + $this->perdidos;
    }

    public function getDiferencia()
    {
        return $this->favor - $this->contra;
    }

    public function getPuntos(ConfiguracionTorneo $configuracion)
    {
        return $configuracion->calcularPuntos($this->ganados, $this->empatados, $this->perdidos);
    }

    # --- Acumulacion ---

    # Suma un partido ya jugado. Es el unico momento en que los
    # contadores cambian, igual que en la base.
    public function sumarPartido($puntaje_propio, $puntaje_rival)
    {
        $propio = (int)$puntaje_propio;
        $rival  = (int)$puntaje_rival;

        if ($propio > $rival) {
            $this->ganados = $this->ganados + 1;
        } elseif ($propio === $rival) {
            $this->empatados = $this->empatados + 1;
        } else {
            $this->perdidos = $this->perdidos + 1;
        }

        $this->favor  = $this->favor + $propio;
        $this->contra = $this->contra + $rival;
    }

    # El orden de la tabla (y por lo tanto el puesto de cada uno) lo
    # resuelve ConfiguracionTorneo->ordenarPosiciones(), porque depende
    # de cuanto vale una victoria en ese torneo.

    public function validar()
    {
        $errores = array();

        # Misma regla que ck_pos_contadores.
        if ($this->ganados < 0 || $this->empatados < 0 || $this->perdidos < 0
            || $this->favor < 0 || $this->contra < 0) {
            $errores[] = 'Los contadores de la tabla de posiciones no pueden ser negativos.';
        }

        return $errores;
    }

    #endregion
}
