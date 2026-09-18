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

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdDisciplina($id_disciplina)
    {
        $this->id_disciplina = (int)$id_disciplina;
    }

    public function getNombre()       { return $this->nombre; }

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
