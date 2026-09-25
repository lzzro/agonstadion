<?php
# =====================================================================
# Modelo: AuditoriaRepositorio   ->   tabla "auditoria" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Sabe agregar filas y leer las ultimas, y nada mas. Es a proposito: el
# usuario de base de datos de la aplicacion tiene sobre esta tabla solo
# SELECT (como sobre todas) e INSERT, asi que ni modificar ni borrar el
# historial estaria permitido aunque esta clase lo intentara.
#
# La lectura es para la pagina de administracion (adminController.php),
# que muestra el registro. Nadie mas lo ve.
#
# El id del usuario queda en NULL cuando la accion es anonima, que es el
# caso de un intento de inicio de sesion con un correo que no existe.
# =====================================================================

require_once __DIR__ . '/Auditoria.php';

class AuditoriaRepositorio
{
    #region ATRIBUTOS
    private $conexion;
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Devuelve un arreglo de errores, vacio si quedo registrado.
    public function registrar(Auditoria $auditoria)
    {
        $errores = $auditoria->validar();
        if (!empty($errores)) {
            return $errores;
        }

        $sql = 'INSERT INTO auditoria (id_usuario, tabla_afectada, id_registro, accion, detalle, direccion_ip)
                VALUES (?, ?, ?, ?, ?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('El registro de auditoria no se completa.');
        }

        # Si no hay usuario, el id viaja en NULL.
        $usuario     = $auditoria->getUsuario();
        $id_usuario  = ($usuario === null) ? null : $usuario->getIdUsuario();
        $tabla       = $auditoria->getTablaAfectada();
        $id_registro = $auditoria->getIdRegistro();
        $accion      = $auditoria->getAccion();
        $detalle     = $auditoria->getDetalle();
        $ip          = $auditoria->getDireccionIp();

        $sentencia->bind_param('isisss', $id_usuario, $tabla, $id_registro,
                               $accion, $detalle, $ip);

        if (!$sentencia->execute()) {
            $sentencia->close();
            return array('El registro de auditoria no queda guardado.');
        }

        $auditoria->setIdAuditoria($this->conexion->insert_id);
        $sentencia->close();
        return array();
    }

    # Las ultimas $cantidad filas, de la mas nueva a la mas vieja, cada
    # una con la cuenta que la produjo (o null si fue anonima). Devuelve
    # null si la consulta no se puede hacer.
    public function listarRecientes($cantidad)
    {
        $sql = 'SELECT a.id_auditoria, a.tabla_afectada, a.id_registro, a.accion,
                       a.detalle, a.direccion_ip, a.fecha_hora,
                       u.id_usuario, u.nombre, u.apellido
                FROM auditoria a
                    LEFT JOIN usuario u ON u.id_usuario = a.id_usuario
                ORDER BY a.fecha_hora DESC, a.id_auditoria DESC
                LIMIT ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $limite = (int)$cantidad;
        $sentencia->bind_param('i', $limite);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $filas = array();
        while ($fila = $resultado->fetch_assoc()) {
            $usuario = ($fila['id_usuario'] === null) ? null
                     : new Usuario($fila['id_usuario'], null, null, $fila['nombre'], $fila['apellido']);
            $filas[] = new Auditoria($fila['id_auditoria'], $usuario, $fila['tabla_afectada'],
                                     $fila['accion'], $fila['id_registro'], $fila['detalle'],
                                     $fila['direccion_ip'], $fila['fecha_hora']);
        }
        $sentencia->close();
        return $filas;
    }

    #endregion
}
