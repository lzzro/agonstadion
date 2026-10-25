<?php
# =====================================================================
# Modelo: ModuloCompetencia   ->   tabla "modulo_competencia"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Define COMO se arma el fixture: Liga, Eliminacion directa, Suizo (el
# fixture lo arma FixtureRepositorio). Desde la migracion 008, cada uno
# se habilita o se deshabilita desde la administracion (RF-24): uno
# deshabilitado no se ofrece en "Nuevo torneo", y los torneos que ya lo
# usan siguen igual.
# =====================================================================

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

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
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
