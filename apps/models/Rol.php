<?php
# =====================================================================
# Modelo: Rol   ->   tabla "rol" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Catalogo de roles: administrador, organizador, jugador, arbitro.
# Los modelos todavia no acceden a la base de datos: son las clases del
# dominio. La conexion es el siguiente item de la entrega.
# =====================================================================

class Rol
{
    #region ATRIBUTOS
    private $id_rol;
    private $nombre;
    private $descripcion;
    #endregion

    #region FUNCIONES

    # En un objeto nuevo, todavia sin guardar, $id_rol viaja en null.
    public function __construct($id_rol, $nombre, $descripcion = null)
    {
        $this->id_rol      = $id_rol;
        $this->nombre      = $nombre;
        $this->descripcion = $descripcion;
    }

    public function getIdRol()      { return $this->id_rol; }
    public function getNombre()     { return $this->nombre; }
    public function getDescripcion(){ return $this->descripcion; }

    # Devuelve un arreglo de mensajes. Vacio significa que esta bien.
    public function validar()
    {
        $errores = array();
        if (empty($this->nombre)) {
            $errores[] = 'El rol necesita un nombre.';
        } elseif (strlen($this->nombre) > 30) {
            $errores[] = 'El nombre del rol no puede pasar de 30 caracteres.';
        }
        return $errores;
    }

    #endregion
}
