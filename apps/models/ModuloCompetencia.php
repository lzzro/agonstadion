<?php
# =====================================================================
# Modelo: ModuloCompetencia   ->   tabla "modulo_competencia"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Define COMO se arma el fixture: Liga, Eliminacion directa, Suizo.
# La generacion del fixture en si es de la tercera entrega; aca solo
# vive el catalogo.
# =====================================================================

class ModuloCompetencia
{
    #region ATRIBUTOS
    private $id_modulo;
    private $nombre;
    private $descripcion;
    #endregion

    #region FUNCIONES

    public function __construct($id_modulo, $nombre, $descripcion = null)
    {
        $this->id_modulo   = $id_modulo;
        $this->nombre      = $nombre;
        $this->descripcion = $descripcion;
    }

    public function getIdModulo()   { return $this->id_modulo; }
    public function getNombre()     { return $this->nombre; }
    public function getDescripcion(){ return $this->descripcion; }

    public function validar()
    {
        $errores = array();
        if (empty($this->nombre)) {
            $errores[] = 'El modulo de competencia necesita un nombre.';
        }
        return $errores;
    }

    #endregion
}
