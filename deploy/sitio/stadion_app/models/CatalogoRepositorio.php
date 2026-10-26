<?php
# Modelo: CatalogoRepositorio -> tablas "disciplina", "tipo_torneo" y "modulo_competencia"

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

    # Las disciplinas, por nombre, indexadas por id. null si la consulta no se puede hacer.
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
        $sentencia = $this->conexion->prepare('SELECT id_modulo, nombre, descripcion, habilitado
                                               FROM modulo_competencia WHERE nombre = ?');
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('s', $nombre);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return ($fila === null) ? null
             : new ModuloCompetencia($fila['id_modulo'], $fila['nombre'], $fila['descripcion'], $fila['habilitado']);
    }

    public function modulos()
    {
        $sentencia = $this->conexion->prepare('SELECT id_modulo, nombre, descripcion, habilitado
                                               FROM modulo_competencia ORDER BY id_modulo');
        if ($sentencia === false) {
            return null;
        }
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $lista = array();
        while ($fila = $resultado->fetch_assoc()) {
            $lista[$fila['nombre']] = new ModuloCompetencia($fila['id_modulo'], $fila['nombre'],
                                                           $fila['descripcion'], $fila['habilitado']);
        }
        $sentencia->close();
        return $lista;
    }

    # Habilita o deshabilita un modulo.
    public function cambiarModulo($id_modulo, $habilitar)
    {
        $id = (int)$id_modulo;
        $nuevo = $habilitar ? 1 : 0;
        $this->conexion->begin_transaction();
        $sentencia = $this->conexion->prepare('SELECT id_modulo, habilitado FROM modulo_competencia FOR UPDATE');
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('El módulo no se puede cambiar por ahora.');
        }
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $existe = false;
        $habilitados = 0;
        $actual = null;
        while ($fila = $resultado->fetch_assoc()) {
            if ((int)$fila['habilitado'] === 1) {
                $habilitados++;
            }
            if ((int)$fila['id_modulo'] === $id) {
                $existe = true;
                $actual = (int)$fila['habilitado'];
            }
        }
        $sentencia->close();
        if (!$existe) {
            $this->conexion->rollback();
            return array('Ese módulo no existe.');
        }
        if ($actual === $nuevo) {
            $this->conexion->rollback();
            return array($nuevo === 1 ? 'Ese módulo ya está habilitado.' : 'Ese módulo ya está deshabilitado.');
        }
        if ($nuevo === 0 && $habilitados <= 1) {
            $this->conexion->rollback();
            return array('Siempre queda al menos un módulo de competencia habilitado.');
        }
        $cambio = $this->conexion->prepare('UPDATE modulo_competencia SET habilitado = ? WHERE id_modulo = ?');
        if ($cambio === false || !$cambio->bind_param('ii', $nuevo, $id) || !$cambio->execute()) {
            $this->conexion->rollback();
            return array('El módulo no se puede cambiar por ahora.');
        }
        $cambio->close();
        $this->conexion->commit();
        return array();
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
