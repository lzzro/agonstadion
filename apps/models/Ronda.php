<?php
# =====================================================================
# Modelo: Ronda   ->   tabla "ronda" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Composicion: contiene un arreglo de objetos Enfrentamiento. El id del
# torneo no es atributo de la clase, porque la ronda vive dentro del
# objeto Torneo que la contiene.
# =====================================================================

require_once __DIR__ . '/Enfrentamiento.php';

class Ronda
{
    #region ATRIBUTOS
    private $id_ronda;
    private $numero;
    private $nombre;
    private $fecha_inicio;
    private $fecha_fin;
    private $estado;
    private $enfrentamientos;    # arreglo de objetos Enfrentamiento
    #endregion

    #region FUNCIONES

    public function __construct($id_ronda, $numero, $nombre = null, $fecha_inicio = null,
                                $fecha_fin = null, $estado = 'pendiente')
    {
        $this->id_ronda        = $id_ronda;
        $this->numero          = (int)$numero;
        $this->nombre          = $nombre;
        $this->fecha_inicio    = $fecha_inicio;
        $this->fecha_fin       = $fecha_fin;
        $this->estado          = $estado;
        $this->enfrentamientos = array();
    }

    public function getIdRonda()         { return $this->id_ronda; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdRonda($id_ronda)
    {
        $this->id_ronda = (int)$id_ronda;
    }

    public function getNumero()          { return $this->numero; }
    public function getNombre()          { return $this->nombre; }
    public function getFechaInicio()     { return $this->fecha_inicio; }
    public function getFechaFin()        { return $this->fecha_fin; }
    public function getEstado()          { return $this->estado; }
    public function getEnfrentamientos() { return $this->enfrentamientos; }

    public function getCantidadEnfrentamientos()
    {
        return count($this->enfrentamientos);
    }

    # Nombre para mostrar: el propio si lo tiene, o "Ronda 7".
    public function getNombreVisible()
    {
        if (!empty($this->nombre)) {
            return $this->nombre;
        }
        return 'Ronda ' . $this->numero;
    }

    public function estaCerrada()
    {
        return $this->estado === 'cerrada';
    }

    # La ronda se puede cerrar cuando no queda nada por jugar.
    public function puedeCerrarse()
    {
        if ($this->getCantidadEnfrentamientos() === 0) {
            return false;
        }
        foreach ($this->enfrentamientos as $enfrentamiento) {
            if (!$enfrentamiento->estaJugado() && !$enfrentamiento->esLibre()) {
                return false;
            }
        }
        return true;
    }

    public function cerrar()
    {
        if ($this->puedeCerrarse()) {
            $this->estado = 'cerrada';
            return array();
        }
        return array('Todavia hay enfrentamientos sin resultado en esta ronda.');
    }

    public function agregarEnfrentamiento(Enfrentamiento $enfrentamiento)
    {
        $errores = $enfrentamiento->validar();

        # Misma regla que la restriccion uq_enfr_numero de la tabla.
        foreach ($this->enfrentamientos as $existente) {
            if ($existente->getNumero() === $enfrentamiento->getNumero()) {
                $errores[] = 'Ya existe el enfrentamiento numero '
                           . $enfrentamiento->getNumero() . ' en esta ronda.';
            }
        }

        if (empty($errores)) {
            $this->enfrentamientos[] = $enfrentamiento;
        }
        return $errores;
    }

    public function validar()
    {
        $errores = array();

        if ($this->numero < 1) {
            $errores[] = 'El numero de ronda arranca en 1.';
        }
        if (!empty($this->nombre) && mb_strlen($this->nombre) > 40) {
            $errores[] = 'El nombre de la ronda no puede pasar de 40 caracteres.';
        }
        if (!empty($this->fecha_inicio) && !empty($this->fecha_fin)
            && $this->fecha_fin < $this->fecha_inicio) {
            $errores[] = 'La fecha de fin de la ronda no puede ser anterior a la de inicio.';
        }

        $estados = array('pendiente', 'en_curso', 'cerrada');
        if (!in_array($this->estado, $estados)) {
            $errores[] = 'El estado de la ronda no es uno de los previstos.';
        }

        return $errores;
    }

    #endregion
}
