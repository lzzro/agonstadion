<?php
# =====================================================================
# Modelo: Disciplina   ->   tabla "disciplina" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Catalogo: Esports, Ajedrez, Tenis de mesa, Futbol, Cartas y Futbol 5
# (con sus tildes en la base desde la migracion 005).
#
# getUnidad() dice como se llaman los tantos de un marcador en esa
# disciplina, para las pestanas de la liga: "+9 de diferencia de mapas",
# "goles a favor".
# =====================================================================

class Disciplina
{
    #region ATRIBUTOS
    private $id_disciplina;
    private $nombre;
    #endregion

    #region FUNCIONES

    public function __construct($id_disciplina, $nombre)
    {
        $this->id_disciplina = $id_disciplina;
        $this->nombre        = $nombre;
    }

    public function getIdDisciplina() { return $this->id_disciplina; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdDisciplina($id_disciplina)
    {
        $this->id_disciplina = (int)$id_disciplina;
    }

    public function getNombre()       { return $this->nombre; }

    public function getUnidad()
    {
        if ($this->nombre === 'Esports') {
            return 'mapas';
        }
        if ($this->nombre === 'Fútbol' || $this->nombre === 'Fútbol 5') {
            return 'goles';
        }
        return 'tantos';
    }

    public function validar()
    {
        $errores = array();
        if (empty($this->nombre)) {
            $errores[] = 'La disciplina necesita un nombre.';
        } elseif (mb_strlen($this->nombre) > 40) {
            $errores[] = 'El nombre de la disciplina no puede pasar de 40 caracteres.';
        }
        return $errores;
    }

    #endregion
}
