<?php
# =====================================================================
# Modelo: TipoTorneo   ->   tabla "tipo_torneo" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Define QUIEN compite. compite_equipo = 1 -> los participantes son
# equipos; 0 -> son usuarios. Torneo usa este dato para aceptar o
# rechazar una inscripcion.
# =====================================================================

class TipoTorneo
{
    #region ATRIBUTOS
    private $id_tipo_torneo;
    private $nombre;
    private $compite_equipo;
    private $descripcion;
    #endregion

    #region FUNCIONES

    public function __construct($id_tipo_torneo, $nombre, $compite_equipo, $descripcion = null)
    {
        $this->id_tipo_torneo = $id_tipo_torneo;
        $this->nombre         = $nombre;
        $this->compite_equipo = (int)$compite_equipo;
        $this->descripcion    = $descripcion;
    }

    public function getIdTipoTorneo() { return $this->id_tipo_torneo; }
    public function getNombre()       { return $this->nombre; }
    public function getCompiteEquipo(){ return $this->compite_equipo; }
    public function getDescripcion()  { return $this->descripcion; }

    # Pregunta comoda para los controladores y para Torneo.
    public function competeEquipo()
    {
        return $this->compite_equipo === 1;
    }

    public function validar()
    {
        $errores = array();
        if (empty($this->nombre)) {
            $errores[] = 'El tipo de torneo necesita un nombre.';
        }
        if ($this->compite_equipo !== 0 && $this->compite_equipo !== 1) {
            $errores[] = 'El campo compite_equipo solo admite 0 o 1.';
        }
        return $errores;
    }

    #endregion
}
