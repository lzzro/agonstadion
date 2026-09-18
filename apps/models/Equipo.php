<?php
# =====================================================================
# Modelo: Equipo   ->   tabla "equipo" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Composicion: contiene un Usuario (el capitan, que puede quedar en
# null porque la clave foranea de la tabla admite NULL) y un arreglo de
# objetos IntegranteEquipo.
#
# La baja es logica, igual que en usuario.
# =====================================================================

require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/IntegranteEquipo.php';

class Equipo
{
    #region ATRIBUTOS
    private $id_equipo;
    private $nombre;
    private $ciudad;
    private $capitan;        # objeto Usuario o null
    private $activo;
    private $fecha_alta;
    private $integrantes;    # arreglo de objetos IntegranteEquipo
    #endregion

    #region FUNCIONES

    public function __construct($id_equipo, $nombre, $ciudad = null, $capitan = null,
                                $activo = 1, $fecha_alta = null)
    {
        $this->id_equipo   = $id_equipo;
        $this->nombre      = $nombre;
        $this->ciudad      = $ciudad;
        $this->capitan     = $capitan;
        $this->activo      = (int)$activo;
        $this->fecha_alta  = $fecha_alta;
        $this->integrantes = array();
    }

    public function getIdEquipo()    { return $this->id_equipo; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdEquipo($id_equipo)
    {
        $this->id_equipo = (int)$id_equipo;
    }

    public function getNombre()      { return $this->nombre; }
    public function getCiudad()      { return $this->ciudad; }
    public function getCapitan()     { return $this->capitan; }
    public function getActivo()      { return $this->activo; }
    public function getFechaAlta()   { return $this->fecha_alta; }
    public function getIntegrantes() { return $this->integrantes; }

    public function getCantidadIntegrantes()
    {
        return count($this->integrantes);
    }

    public function estaActivo()
    {
        return $this->activo === 1;
    }

    public function darDeBaja()
    {
        $this->activo = 0;
    }

    public function agregarIntegrante(IntegranteEquipo $integrante)
    {
        $errores = $integrante->validar();

        # El dorsal no se puede repetir dentro del mismo equipo, igual
        # que la restriccion uq_integ_dorsal de la tabla.
        if ($integrante->getDorsal() !== null && $this->dorsalOcupado($integrante->getDorsal())) {
            $errores[] = 'El dorsal ' . $integrante->getDorsal() . ' ya esta ocupado en este equipo.';
        }

        if (empty($errores)) {
            $this->integrantes[] = $integrante;
        }
        return $errores;
    }

    public function dorsalOcupado($dorsal)
    {
        foreach ($this->integrantes as $integrante) {
            if ($integrante->getDorsal() === (int)$dorsal) {
                return true;
            }
        }
        return false;
    }

    public function validar()
    {
        $errores = array();
        if (empty($this->nombre)) {
            $errores[] = 'El equipo necesita un nombre.';
        } elseif (mb_strlen($this->nombre) > 40) {
            $errores[] = 'El nombre del equipo no puede pasar de 40 caracteres.';
        }
        if (!empty($this->ciudad) && mb_strlen($this->ciudad) > 40) {
            $errores[] = 'La ciudad no puede pasar de 40 caracteres.';
        }
        if ($this->capitan !== null && !($this->capitan instanceof Usuario)) {
            $errores[] = 'El capitan tiene que ser un objeto Usuario o quedar vacio.';
        }
        if ($this->activo !== 0 && $this->activo !== 1) {
            $errores[] = 'El campo activo solo admite 0 o 1.';
        }
        return $errores;
    }

    #endregion
}
