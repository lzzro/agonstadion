<?php
# Modelo: Resultado -> tabla "resultado" de sql/schema.sql

require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Usuario.php';

class Resultado
{
    #region ATRIBUTOS
    private $puntaje_local;
    private $puntaje_visitante;
    private $ganador;            # objeto Participante o null si hay empate
    private $walkover;
    private $observaciones;
    private $usuario_carga;      # objeto Usuario o null
    private $fecha_carga;
    #endregion

    #region FUNCIONES

    public function __construct($puntaje_local, $puntaje_visitante, $ganador = null,
                                $walkover = 0, $observaciones = null,
                                $usuario_carga = null, $fecha_carga = null)
    {
        $this->puntaje_local     = (int)$puntaje_local;
        $this->puntaje_visitante = (int)$puntaje_visitante;
        $this->ganador           = $ganador;
        $this->walkover          = (int)$walkover;
        $this->observaciones     = $observaciones;
        $this->usuario_carga     = $usuario_carga;
        $this->fecha_carga       = $fecha_carga;
    }

    public function getPuntajeLocal()     { return $this->puntaje_local; }
    public function getPuntajeVisitante() { return $this->puntaje_visitante; }
    public function getGanador()          { return $this->ganador; }
    public function getWalkover()         { return $this->walkover; }
    public function getObservaciones()    { return $this->observaciones; }
    public function getUsuarioCarga()     { return $this->usuario_carga; }
    public function getFechaCarga()       { return $this->fecha_carga; }

    public function esWalkover()
    {
        return $this->walkover === 1;
    }

    public function esEmpate()
    {
        return $this->puntaje_local === $this->puntaje_visitante;
    }

    # Diferencia del punto de vista del local.
    public function getDiferencia()
    {
        return $this->puntaje_local - $this->puntaje_visitante;
    }

    public function validar()
    {
        $errores = array();

        # Misma regla que ck_res_puntajes.
        if ($this->puntaje_local < 0 || $this->puntaje_visitante < 0) {
            $errores[] = 'Los puntajes no pueden ser negativos.';
        }
        if ($this->walkover !== 0 && $this->walkover !== 1) {
            $errores[] = 'El walkover es sí o no.';
        }
        if ($this->ganador !== null && !($this->ganador instanceof Participante)) {
            $errores[] = 'El ganador no es un participante válido.';
        }
        if ($this->usuario_carga !== null && !($this->usuario_carga instanceof Usuario)) {
            $errores[] = 'Quien carga el resultado no es un usuario válido.';
        }
        if (!empty($this->observaciones) && mb_strlen($this->observaciones) > 200) {
            $errores[] = 'Las observaciones no pueden pasar de 200 caracteres.';
        }

        if (!$this->esEmpate() && $this->ganador === null && !$this->esWalkover()) {
            $errores[] = 'Si el marcador no es empate hay que indicar el ganador.';
        }
        if ($this->esEmpate() && $this->ganador !== null && !$this->esWalkover()) {
            $errores[] = 'Un empate no puede tener ganador.';
        }
        if ($this->esWalkover() && $this->ganador === null) {
            $errores[] = 'Un walkover lleva ganador: el que se presenta.';
        }
        if ($this->esWalkover() && ($this->puntaje_local !== 0 || $this->puntaje_visitante !== 0)) {
            $errores[] = 'Un walkover no lleva marcador.';
        }

        return $errores;
    }

    #endregion
}
