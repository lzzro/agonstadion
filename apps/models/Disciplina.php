<?php
# =====================================================================
# Modelo: Disciplina   ->   tabla "disciplina" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Catalogo: Esports, Ajedrez, Tenis de mesa, Futbol, Cartas.
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
    public function getNombre()       { return $this->nombre; }

    public function validar()
    {
        $errores = array();
        if (empty($this->nombre)) {
            $errores[] = 'La disciplina necesita un nombre.';
        } elseif (strlen($this->nombre) > 40) {
            $errores[] = 'El nombre de la disciplina no puede pasar de 40 caracteres.';
        }
        return $errores;
    }

    #endregion
}
