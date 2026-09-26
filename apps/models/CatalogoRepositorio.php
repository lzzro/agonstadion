<?php
# =====================================================================
# Modelo: CatalogoRepositorio   ->   tablas "disciplina", "tipo_torneo"
#                                   y "modulo_competencia"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Lee los catalogos que usa el formulario de una liga nueva. Los ids no
# van escritos en el codigo: salen del catalogo por su nombre, igual
# que el rol en UsuarioRepositorio::asignarRolPorNombre.
#
# Solo lectura: sgdm_app no puede cambiar los catalogos (los mantiene
# sgdm_admin, ver el DCL de schema.sql).
# =====================================================================

require_once __DIR__ . '/Disciplina.php';
require_once __DIR__ . '/TipoTorneo.php';
require_once __DIR__ . '/ModuloCompetencia.php';

class CatalogoRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Las disciplinas, por nombre, indexadas por id. null si la consulta
    # no se puede hacer.
    public function disciplinas()
    {
        $sentencia = $this->conexion->prepare('SELECT id_disciplina, nombre FROM disciplina ORDER BY nombre');
        if ($sentencia === false) {
            return null;
        }
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $lista = array();
        while ($fila = $resultado->fetch_assoc()) {
            $lista[(int)$fila['id_disciplina']] = new Disciplina($fila['id_disciplina'], $fila['nombre']);
        }
        $sentencia->close();
        return $lista;
    }

    public function tipoPorNombre($nombre)
    {
        $sentencia = $this->conexion->prepare('SELECT id_tipo_torneo, nombre, compite_equipo, descripcion
                                               FROM tipo_torneo WHERE nombre = ?');
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('s', $nombre);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return ($fila === null) ? null
             : new TipoTorneo($fila['id_tipo_torneo'], $fila['nombre'], $fila['compite_equipo'], $fila['descripcion']);
    }

    public function moduloPorNombre($nombre)
    {
        $sentencia = $this->conexion->prepare('SELECT id_modulo, nombre, descripcion
                                               FROM modulo_competencia WHERE nombre = ?');
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('s', $nombre);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return ($fila === null) ? null
             : new ModuloCompetencia($fila['id_modulo'], $fila['nombre'], $fila['descripcion']);
    }

    # Cuantos formatos de competencia tiene el catalogo.
    public function cantidadModulos()
    {
        $sentencia = $this->conexion->prepare('SELECT COUNT(*) AS cantidad FROM modulo_competencia');
        if ($sentencia === false) {
            return null;
        }
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return (int)$fila['cantidad'];
    }

    #endregion
}
