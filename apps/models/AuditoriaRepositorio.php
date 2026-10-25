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
            return array('El registro de auditoría no se completa.');
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
            return array('El registro de auditoría no queda guardado.');
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
        return $this->listar('', 1, $cantidad);
    }

    # El registro de a paginas (la administracion, RF-24): las filas de
    # una accion ('' son todas), de la mas nueva a la mas vieja, desde la
    # pagina $pagina (la 1 es la mas nueva). null si la consulta no se
    # puede hacer.
    public function listar($accion, $pagina, $por_pagina)
    {
        $donde = ($accion === '') ? '' : 'WHERE a.accion = ?';
        $sql = 'SELECT a.id_auditoria, a.tabla_afectada, a.id_registro, a.accion,
                       a.detalle, a.direccion_ip, a.fecha_hora,
                       u.id_usuario, u.nombre, u.apellido
                FROM auditoria a
                    LEFT JOIN usuario u ON u.id_usuario = a.id_usuario
                ' . $donde . '
                ORDER BY a.fecha_hora DESC, a.id_auditoria DESC
                LIMIT ? OFFSET ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $limite = (int)$por_pagina;
        $desde  = (max(1, (int)$pagina) - 1) * $limite;
        if ($accion === '') {
            $sentencia->bind_param('ii', $limite, $desde);
        } else {
            $sentencia->bind_param('sii', $accion, $limite, $desde);
        }
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

    # Cuantos inicios de sesion fallidos con ese correo hubo en los
    # ultimos $minutos (el bloqueo de la politica de contrasenas: 5 en 15
    # minutos). Cuentan las filas login_error cuyo detalle es el correo
    # intentado, exista o no la cuenta: asi el bloqueo no revela que
    # correos tienen cuenta. Los intentos rechazados por el propio
    # bloqueo quedan con otro detalle ("Bloqueo: ...") y no cuentan, para
    # que la pausa dure 15 minutos desde el quinto fallo. La hora es la
    # de la base, la misma que guarda fecha_hora. null si la consulta no
    # se puede hacer.
    public function intentosFallidos($correo, $minutos)
    {
        $sql = 'SELECT COUNT(*) AS cuantos FROM auditoria
                WHERE accion = \'login_error\' AND detalle = ?
                  AND fecha_hora >= NOW() - INTERVAL ? MINUTE';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $minutos = (int)$minutos;
        $sentencia->bind_param('si', $correo, $minutos);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return (int)$fila['cuantos'];
    }

    # Cuantas filas tiene el registro de una accion ('' son todas). null
    # si la consulta no se puede hacer.
    public function contar($accion)
    {
        $sql = 'SELECT COUNT(*) AS cuantas FROM auditoria' . (($accion === '') ? '' : ' WHERE accion = ?');
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        if ($accion !== '') {
            $sentencia->bind_param('s', $accion);
        }
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return (int)$fila['cuantas'];
    }

    #endregion
}
