<?php
# =====================================================================
# Modelo: Auditoria   ->   tabla "auditoria" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Registro de las operaciones sensibles. Composicion: contiene el
# objeto Usuario que hizo la accion, que puede quedar en null si la
# accion es anonima (por ejemplo un login fallido con un correo que no
# existe) o si despues se borra el usuario.
#
# En la base, el usuario de la aplicacion solo tiene permiso de INSERT
# sobre esta tabla: el historial no se modifica ni se borra. Por eso
# esta clase no tiene ningun metodo que cambie una fila ya registrada.
# =====================================================================

require_once __DIR__ . '/Usuario.php';

class Auditoria
{
    #region ATRIBUTOS
    private $id_auditoria;
    private $usuario;            # objeto Usuario o null
    private $tabla_afectada;
    private $id_registro;
    private $accion;
    private $detalle;
    private $direccion_ip;
    private $fecha_hora;
    #endregion

    #region FUNCIONES

    public function __construct($id_auditoria, $usuario, $tabla_afectada, $accion,
                                $id_registro = null, $detalle = null,
                                $direccion_ip = null, $fecha_hora = null)
    {
        $this->id_auditoria   = $id_auditoria;
        $this->usuario        = $usuario;
        $this->tabla_afectada = $tabla_afectada;
        $this->accion         = $accion;
        $this->id_registro    = ($id_registro === null || $id_registro === '')
                                ? null : (int)$id_registro;
        $this->detalle        = $detalle;
        $this->direccion_ip   = $direccion_ip;
        $this->fecha_hora     = $fecha_hora;
    }

    public function getIdAuditoria()  { return $this->id_auditoria; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdAuditoria($id_auditoria)
    {
        $this->id_auditoria = (int)$id_auditoria;
    }

    public function getUsuario()      { return $this->usuario; }
    public function getTablaAfectada(){ return $this->tabla_afectada; }
    public function getIdRegistro()   { return $this->id_registro; }
    public function getAccion()       { return $this->accion; }
    public function getDetalle()      { return $this->detalle; }
    public function getDireccionIp()  { return $this->direccion_ip; }
    public function getFechaHora()    { return $this->fecha_hora; }

    public function esAnonima()
    {
        return $this->usuario === null;
    }

    # Quien figura en el listado de auditoria.
    public function getResponsable()
    {
        if ($this->esAnonima()) {
            return 'anonimo';
        }
        return $this->usuario->getNombreCompleto();
    }

    public function validar()
    {
        $errores = array();

        if (empty($this->tabla_afectada)) {
            $errores[] = 'Hay que indicar la tabla afectada.';
        } elseif (mb_strlen($this->tabla_afectada) > 40) {
            $errores[] = 'El nombre de la tabla afectada no puede pasar de 40 caracteres.';
        }

        # Misma lista que la restriccion ck_audit_accion.
        $acciones = array('alta', 'baja', 'modificacion', 'login_ok', 'login_error', 'logout');
        if (!in_array($this->accion, $acciones)) {
            $errores[] = 'La accion registrada no es una de las previstas.';
        }

        if ($this->usuario !== null && !($this->usuario instanceof Usuario)) {
            $errores[] = 'El responsable del registro no es un usuario valido.';
        }

        if (!empty($this->detalle) && mb_strlen($this->detalle) > 255) {
            $errores[] = 'El detalle no puede pasar de 255 caracteres.';
        }

        # 45 caracteres es el largo maximo de una IPv6.
        if (!empty($this->direccion_ip) && mb_strlen($this->direccion_ip) > 45) {
            $errores[] = 'La direccion IP no tiene un largo valido.';
        }

        return $errores;
    }

    #endregion
}
