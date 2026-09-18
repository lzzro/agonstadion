<?php
# =====================================================================
# Modelo: IntegranteEquipo   ->   tabla "integrante_equipo"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Tabla intermedia de la relacion N:M entre equipo y usuario. Tiene
# clase propia, y no un simple arreglo de usuarios, porque la relacion
# lleva atributos: dorsal, activo y fecha de alta.
#
# El id del equipo no es atributo de esta clase: el integrante vive
# dentro del objeto Equipo que lo contiene.
# =====================================================================

require_once __DIR__ . '/Usuario.php';

class IntegranteEquipo
{
    #region ATRIBUTOS
    private $usuario;        # objeto Usuario
    private $dorsal;
    private $activo;
    private $fecha_alta;
    #endregion

    #region FUNCIONES

    public function __construct(Usuario $usuario, $dorsal = null, $activo = 1, $fecha_alta = null)
    {
        $this->usuario    = $usuario;
        $this->dorsal     = ($dorsal === null || $dorsal === '') ? null : (int)$dorsal;
        $this->activo     = (int)$activo;
        $this->fecha_alta = $fecha_alta;
    }

    public function getUsuario()   { return $this->usuario; }
    public function getDorsal()    { return $this->dorsal; }
    public function getActivo()    { return $this->activo; }
    public function getFechaAlta() { return $this->fecha_alta; }

    public function estaActivo()
    {
        return $this->activo === 1;
    }

    public function darDeBaja()
    {
        $this->activo = 0;
    }

    public function validar()
    {
        $errores = array();
        if ($this->dorsal !== null && ($this->dorsal < 0 || $this->dorsal > 255)) {
            $errores[] = 'El dorsal tiene que estar entre 0 y 255.';
        }
        if ($this->activo !== 0 && $this->activo !== 1) {
            $errores[] = 'El estado de alta solo admite si o no.';
        }
        return $errores;
    }

    #endregion
}
