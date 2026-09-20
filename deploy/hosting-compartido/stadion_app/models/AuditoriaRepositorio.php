<?php
# =====================================================================
# Modelo: AuditoriaRepositorio   ->   tabla "auditoria" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Solo sabe agregar filas, y es a proposito: el usuario de base de datos
# de la aplicacion tiene nada mas que INSERT sobre esta tabla, asi que
# ni modificar ni borrar el historial estaria permitido aunque esta
# clase lo intentara.
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

    #endregion
}
