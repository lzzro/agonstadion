<?php
# =====================================================================
# Modelo: ConfiguracionTorneo   ->   tabla "configuracion_torneo"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Relacion 1:1 con torneo: los parametros de puntaje y las reglas.
#
# Aca vive calcularPuntos(), que es la contracara de la decision del
# schema de NO guardar los puntos en tabla_posiciones. Los puntos se
# calculan siempre a partir de estos parametros, asi que si el
# organizador cambia el puntaje, la tabla de posiciones se acomoda sola.
# =====================================================================

class ConfiguracionTorneo
{
    #region ATRIBUTOS
    private $id_torneo;
    private $puntos_victoria;
    private $puntos_empate;
    private $puntos_derrota;
    private $admite_empate;
    private $clasifican_playoffs;
    private $ida_y_vuelta;
    private $rondas_previstas;
    private $reglas;
    #endregion

    #region FUNCIONES

    public function __construct($id_torneo, $puntos_victoria = 3, $puntos_empate = 1,
                                $puntos_derrota = 0, $admite_empate = 1,
                                $clasifican_playoffs = 0, $ida_y_vuelta = 0,
                                $rondas_previstas = null, $reglas = null)
    {
        $this->id_torneo           = $id_torneo;
        $this->puntos_victoria     = (int)$puntos_victoria;
        $this->puntos_empate       = (int)$puntos_empate;
        $this->puntos_derrota      = (int)$puntos_derrota;
        $this->admite_empate       = (int)$admite_empate;
        $this->clasifican_playoffs = (int)$clasifican_playoffs;
        $this->ida_y_vuelta        = (int)$ida_y_vuelta;
        $this->rondas_previstas    = ($rondas_previstas === null || $rondas_previstas === '')
                                     ? null : (int)$rondas_previstas;
        $this->reglas              = $reglas;
    }

    public function getIdTorneo()          { return $this->id_torneo; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdTorneo($id_torneo)
    {
        $this->id_torneo = (int)$id_torneo;
    }

    public function getPuntosVictoria()    { return $this->puntos_victoria; }
    public function getPuntosEmpate()      { return $this->puntos_empate; }
    public function getPuntosDerrota()     { return $this->puntos_derrota; }
    public function getAdmiteEmpate()      { return $this->admite_empate; }
    public function getClasificanPlayoffs(){ return $this->clasifican_playoffs; }
    public function getIdaYVuelta()        { return $this->ida_y_vuelta; }
    public function getRondasPrevistas()   { return $this->rondas_previstas; }
    public function getReglas()            { return $this->reglas; }

    public function admiteEmpate()
    {
        return $this->admite_empate === 1;
    }

    public function esIdaYVuelta()
    {
        return $this->ida_y_vuelta === 1;
    }

    # Dato derivado: no se guarda en la base, se calcula.
    public function calcularPuntos($ganados, $empatados, $perdidos)
    {
        return ((int)$ganados   * $this->puntos_victoria)
             + ((int)$empatados * $this->puntos_empate)
             + ((int)$perdidos  * $this->puntos_derrota);
    }

    # Cuantos enfrentamientos genera una liga con esta configuracion.
    # Es el numero que muestra la pantalla de creacion de torneo.
    public function enfrentamientosDeLiga($cantidad_participantes)
    {
        $cantidad = (int)$cantidad_participantes;
        if ($cantidad < 2) {
            return 0;
        }
        $partidos = ($cantidad * ($cantidad - 1)) / 2;
        if ($this->esIdaYVuelta()) {
            $partidos = $partidos * 2;
        }
        return (int)$partidos;
    }

    # Ordena un arreglo de objetos PosicionTabla como la tabla de la
    # pantalla: por puntos, despues diferencia, despues favor. Es el
    # mismo ORDER BY de la consulta de ejemplo del schema, y vive aca
    # porque el orden depende de estos puntajes. El puesto de cada uno
    # es su lugar en el arreglo devuelto.
    public function ordenarPosiciones($posiciones)
    {
        $ordenadas = $posiciones;
        $cantidad  = count($ordenadas);

        for ($i = 0; $i < $cantidad - 1; $i++) {
            for ($j = 0; $j < $cantidad - 1 - $i; $j++) {
                $actual    = $ordenadas[$j];
                $siguiente = $ordenadas[$j + 1];

                $puntos_actual    = $this->calcularPuntos($actual->getGanados(),
                                        $actual->getEmpatados(), $actual->getPerdidos());
                $puntos_siguiente = $this->calcularPuntos($siguiente->getGanados(),
                                        $siguiente->getEmpatados(), $siguiente->getPerdidos());

                $va_despues = false;
                if ($puntos_actual < $puntos_siguiente) {
                    $va_despues = true;
                } elseif ($puntos_actual === $puntos_siguiente) {
                    if ($actual->getDiferencia() < $siguiente->getDiferencia()) {
                        $va_despues = true;
                    } elseif ($actual->getDiferencia() === $siguiente->getDiferencia()
                              && $actual->getFavor() < $siguiente->getFavor()) {
                        $va_despues = true;
                    }
                }

                if ($va_despues) {
                    $ordenadas[$j]     = $siguiente;
                    $ordenadas[$j + 1] = $actual;
                }
            }
        }

        return $ordenadas;
    }

    public function validar()
    {
        $errores = array();

        # Misma regla que la restriccion ck_config_pts de la tabla.
        if ($this->puntos_victoria < $this->puntos_empate
            || $this->puntos_empate < $this->puntos_derrota) {
            $errores[] = 'Los puntos por victoria no pueden ser menores que los de '
                       . 'empate, ni los de empate menores que los de derrota.';
        }
        if ($this->puntos_victoria < 0 || $this->puntos_victoria > 10) {
            $errores[] = 'Los puntos por victoria tienen que estar entre 0 y 10.';
        }
        if ($this->puntos_empate < 0 || $this->puntos_empate > 10) {
            $errores[] = 'Los puntos por empate tienen que estar entre 0 y 10.';
        }
        if ($this->admite_empate !== 0 && $this->admite_empate !== 1) {
            $errores[] = 'El campo admite_empate solo admite 0 o 1.';
        }
        if ($this->ida_y_vuelta !== 0 && $this->ida_y_vuelta !== 1) {
            $errores[] = 'El campo ida_y_vuelta solo admite 0 o 1.';
        }
        if ($this->clasifican_playoffs < 0) {
            $errores[] = 'La cantidad que clasifica a playoffs no puede ser negativa.';
        }
        return $errores;
    }

    #endregion
}
