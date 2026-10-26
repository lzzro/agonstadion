<?php
# Modelo: ModuloCompetencia -> tabla "modulo_competencia"

class ModuloCompetencia
{
    #region ATRIBUTOS
    private $id_modulo;
    private $nombre;
    private $descripcion;
    private $habilitado;     # 1 o 0
    #endregion

    #region FUNCIONES

    public function __construct($id_modulo, $nombre, $descripcion = null, $habilitado = 1)
    {
        $this->id_modulo   = $id_modulo;
        $this->nombre      = $nombre;
        $this->descripcion = $descripcion;
        $this->habilitado  = (int)$habilitado;
    }

    public function getIdModulo()   { return $this->id_modulo; }

    public function setIdModulo($id_modulo)
    {
        $this->id_modulo = (int)$id_modulo;
    }

    public function getNombre()     { return $this->nombre; }
    public function getDescripcion(){ return $this->descripcion; }
    public function estaHabilitado() { return $this->habilitado === 1; }

    public function validar()
    {
        $errores = array();
        if (empty($this->nombre)) {
            $errores[] = 'El módulo de competencia necesita un nombre.';
        }
        return $errores;
    }

    #endregion
}
