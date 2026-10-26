<?php
# Modelo: PedidoRol -> tabla "pedido_rol" de sql/schema.sql

require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Rol.php';

class PedidoRol
{
    #region ATRIBUTOS
    private $id_pedido_rol;
    private $usuario;            # Usuario que pide
    private $rol;                # Rol pedido
    private $estado;             # 'pendiente', 'aprobado' o 'rechazado'
    private $fecha_pedido;
    private $fecha_resolucion;
    private $resuelve;           # Usuario que lo resolvio, o null
    #endregion

    #region FUNCIONES

    public function __construct($id_pedido_rol, Usuario $usuario, Rol $rol,
                                $estado = 'pendiente', $fecha_pedido = null,
                                $fecha_resolucion = null, $resuelve = null)
    {
        $this->id_pedido_rol    = $id_pedido_rol;
        $this->usuario          = $usuario;
        $this->rol              = $rol;
        $this->estado           = $estado;
        $this->fecha_pedido     = $fecha_pedido;
        $this->fecha_resolucion = $fecha_resolucion;
        $this->resuelve         = $resuelve;
    }

    public function getIdPedidoRol()    { return $this->id_pedido_rol; }

    public function setIdPedidoRol($id_pedido_rol)
    {
        $this->id_pedido_rol = (int)$id_pedido_rol;
    }

    public function getUsuario()         { return $this->usuario; }
    public function getRol()             { return $this->rol; }
    public function getEstado()          { return $this->estado; }
    public function getFechaPedido()     { return $this->fecha_pedido; }
    public function getFechaResolucion() { return $this->fecha_resolucion; }
    public function getResuelve()        { return $this->resuelve; }

    public function estaPendiente() { return $this->estado === 'pendiente'; }
    public function estaAprobado()  { return $this->estado === 'aprobado'; }
    public function estaRechazado() { return $this->estado === 'rechazado'; }

    # Si $administrador puede resolver este pedido.
    public function motivosParaNoResolver($administrador)
    {
        $motivos = array();
        if (!($administrador instanceof Usuario) || !$administrador->tieneRol('administrador')) {
            $motivos[] = 'Los pedidos de rol los resuelve la administración.';
        } elseif ((int)$administrador->getIdUsuario() === (int)$this->usuario->getIdUsuario()) {
            $motivos[] = 'Un pedido propio lo resuelve otra cuenta de la administración.';
        }
        if (!$this->estaPendiente()) {
            $motivos[] = 'Ese pedido ya no está pendiente.';
        }
        return $motivos;
    }

    public function validar()
    {
        $errores = array();
        if (!in_array($this->estado, array('pendiente', 'aprobado', 'rechazado'))) {
            $errores[] = 'El estado del pedido no es uno de los previstos.';
        }
        if ($this->resuelve !== null
            && (int)$this->resuelve->getIdUsuario() === (int)$this->usuario->getIdUsuario()) {
            $errores[] = 'Un pedido propio lo resuelve otra cuenta de la administración.';
        }
        return $errores;
    }

    #endregion
}
