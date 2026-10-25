<?php
# =====================================================================
# Modelo: MedicionRepositorio   ->   tabla "medicion"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para el rendimiento fisico (RF-29):
#   - guardar una medicion de la cuenta de la sesion
#   - borrar una suya (solo de esa cuenta: el id de otra no borra nada)
#   - las mediciones de una cuenta, de la mas vieja a la mas nueva (la
#     evolucion en el tiempo)
#   - la comparacion entre participantes de una prueba: la mejor marca
#     de cada cuenta, ordenada (ver Medicion::esMejor)
#
# Se guardan solo los datos crudos; los indicadores los calcula Medicion.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Medicion.php';

class MedicionRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Guarda la medicion. Devuelve un arreglo de errores, vacio si quedo
    # guardada; el id queda en el objeto. La fecha no puede ser futura
    # (hoy, en Montevideo: ver apps/fechas.php).
    public function insertar(Medicion $medicion, $hoy)
    {
        $errores = $medicion->validar();
        if (empty($errores) && $medicion->getFecha() > $hoy) {
            $errores[] = 'La fecha de la medición va hasta hoy.';
        }
        if (!empty($errores)) {
            return $errores;
        }
        $sql = 'INSERT INTO medicion (id_usuario, prueba, distancia_cm, tiempo_s, masa_kg, fecha)
                VALUES (?, ?, ?, ?, ?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('La medición no se puede guardar por ahora.');
        }
        $id        = (int)$medicion->getUsuario()->getIdUsuario();
        $prueba    = $medicion->getPrueba();
        $distancia = $medicion->getDistanciaCm();
        $tiempo    = $medicion->getTiempoS();
        $masa      = $medicion->getMasaKg();
        $fecha     = $medicion->getFecha();
        $sentencia->bind_param('isddds', $id, $prueba, $distancia, $tiempo, $masa, $fecha);
        if (!$sentencia->execute()) {
            $sentencia->close();
            return array('La medición no se puede guardar por ahora.');
        }
        $medicion->setIdMedicion($this->conexion->insert_id);
        $sentencia->close();
        return array();
    }

    # Borra una medicion de esa cuenta. true si borro una.
    public function borrar($id_medicion, $id_usuario)
    {
        $sentencia = $this->conexion->prepare('DELETE FROM medicion WHERE id_medicion = ? AND id_usuario = ?');
        if ($sentencia === false) {
            return false;
        }
        $id  = (int)$id_medicion;
        $usu = (int)$id_usuario;
        $sentencia->bind_param('ii', $id, $usu);
        $sentencia->execute();
        $borradas = $sentencia->affected_rows;
        $sentencia->close();
        return $borradas === 1;
    }

    # Las de una cuenta, por fecha (y en el mismo dia, por orden de
    # carga). null si la consulta no se puede hacer.
    public function listarDeUsuario(Usuario $usuario)
    {
        $sql = 'SELECT id_medicion, prueba, distancia_cm, tiempo_s, masa_kg, fecha, fecha_carga
                FROM medicion WHERE id_usuario = ? ORDER BY fecha, id_medicion';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$usuario->getIdUsuario();
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $lista = array();
        while ($fila = $resultado->fetch_assoc()) {
            $lista[] = new Medicion($fila['id_medicion'], $usuario, $fila['prueba'], $fila['distancia_cm'],
                                    $fila['tiempo_s'], $fila['masa_kg'], $fila['fecha'], $fila['fecha_carga']);
        }
        $sentencia->close();
        return $lista;
    }

    # La comparacion de una prueba: la mejor marca de cada cuenta activa
    # que tiene alguna, de la mejor a la peor. Cada elemento:
    # array('usuario' => Usuario, 'mejor' => Medicion, 'cantidad' => N).
    # null si la consulta no se puede hacer.
    public function comparacion($prueba)
    {
        $sql = "SELECT m.id_medicion, m.prueba, m.distancia_cm, m.tiempo_s, m.masa_kg, m.fecha,
                       u.id_usuario, u.nombre, u.apellido, u.de_muestra
                FROM medicion m INNER JOIN usuario u ON u.id_usuario = m.id_usuario
                WHERE m.prueba = ? AND u.activo = 1
                ORDER BY m.id_usuario, m.id_medicion";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->bind_param('s', $prueba);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $por_cuenta = array();
        while ($fila = $resultado->fetch_assoc()) {
            $id = (int)$fila['id_usuario'];
            if (!isset($por_cuenta[$id])) {
                $usuario = new Usuario($id, null, null, $fila['nombre'], $fila['apellido'], null, null, 1,
                                       null, null, null, $fila['de_muestra']);
                $por_cuenta[$id] = array('usuario' => $usuario, 'mejor' => null, 'cantidad' => 0);
            }
            $medicion = new Medicion($fila['id_medicion'], $por_cuenta[$id]['usuario'], $fila['prueba'],
                                     $fila['distancia_cm'], $fila['tiempo_s'], $fila['masa_kg'], $fila['fecha']);
            $por_cuenta[$id]['cantidad']++;
            $mejor = $por_cuenta[$id]['mejor'];
            if ($mejor === null || Medicion::esMejor($prueba, $medicion->indicador(), $mejor->indicador())) {
                $por_cuenta[$id]['mejor'] = $medicion;
            }
        }
        $sentencia->close();

        # De la mejor a la peor (burbuja, como ordenarPosiciones).
        $lista = array_values($por_cuenta);
        for ($i = 0; $i < count($lista) - 1; $i++) {
            for ($j = 0; $j < count($lista) - 1 - $i; $j++) {
                if (Medicion::esMejor($prueba, $lista[$j + 1]['mejor']->indicador(), $lista[$j]['mejor']->indicador())) {
                    $tmp = $lista[$j];
                    $lista[$j] = $lista[$j + 1];
                    $lista[$j + 1] = $tmp;
                }
            }
        }
        return $lista;
    }

    #endregion
}
