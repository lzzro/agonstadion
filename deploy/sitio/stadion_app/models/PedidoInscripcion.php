<?php
# =====================================================================
# Modelo: PedidoInscripcion   ->   tabla "pedido_inscripcion"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# El capitan de un equipo pide lugar en una liga con la inscripcion
# abierta, y quien la organiza lo acepta o lo rechaza. Es el mismo
# esquema que PedidoRol: el pedido es la historia, y aceptarlo es
# agregar la fila de participante (ver PedidoInscripcionRepositorio).
#
# Composicion: contiene el Torneo, el Equipo, el Usuario que pide y el
# Usuario que lo resolvio (o null mientras esta pendiente).
#
# Las reglas del dominio:
#   - solo se resuelve un pedido pendiente
#   - solo lo resuelve quien organiza la liga
#   - un solo pendiente por equipo y liga (lo garantiza la base con
#     uq_pinsc_pendiente; aca solo se da el aviso claro)
# =====================================================================

require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Equipo.php';
require_once __DIR__ . '/Torneo.php';

class PedidoInscripcion
{
    #region ATRIBUTOS
    private $id_pedido_inscripcion;
    private $torneo;             # Torneo en el que se pide lugar
    private $equipo;             # Equipo que pide
    private $usuario;            # Usuario que pide: el capitan
    private $estado;             # 'pendiente', 'aceptado' o 'rechazado'
    private $fecha_pedido;
    private $fecha_resolucion;
    private $resuelve;           # Usuario que lo resolvio, o null
    #endregion

    #region FUNCIONES

    public function __construct($id_pedido_inscripcion, Torneo $torneo, Equipo $equipo,
                                Usuario $usuario, $estado = 'pendiente', $fecha_pedido = null,
                                $fecha_resolucion = null, $resuelve = null)
    {
        $this->id_pedido_inscripcion = $id_pedido_inscripcion;
        $this->torneo                = $torneo;
        $this->equipo                = $equipo;
        $this->usuario               = $usuario;
        $this->estado                = $estado;
        $this->fecha_pedido          = $fecha_pedido;
        $this->fecha_resolucion      = $fecha_resolucion;
        $this->resuelve              = $resuelve;
    }

    public function getIdPedidoInscripcion() { return $this->id_pedido_inscripcion; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdPedidoInscripcion($id_pedido_inscripcion)
    {
        $this->id_pedido_inscripcion = (int)$id_pedido_inscripcion;
    }

    public function getTorneo()          { return $this->torneo; }
    public function getEquipo()          { return $this->equipo; }
    public function getUsuario()         { return $this->usuario; }
    public function getEstado()          { return $this->estado; }
    public function getFechaPedido()     { return $this->fecha_pedido; }
    public function getFechaResolucion() { return $this->fecha_resolucion; }
    public function getResuelve()        { return $this->resuelve; }

    public function estaPendiente() { return $this->estado === 'pendiente'; }
    public function estaAceptado()  { return $this->estado === 'aceptado'; }
    public function estaRechazado() { return $this->estado === 'rechazado'; }

    # Si $organizador puede resolver este pedido. Devuelve un arreglo de
    # motivos por los que no; vacio quiere decir que si.
    public function motivosParaNoResolver($organizador)
    {
        $motivos = array();
        $id_org  = (int)$this->torneo->getOrganizador()->getIdUsuario();
        if (!($organizador instanceof Usuario) || (int)$organizador->getIdUsuario() !== $id_org) {
            $motivos[] = 'Los pedidos de una liga los resuelve quien la organiza.';
        }
        if (!$this->estaPendiente()) {
            $motivos[] = 'Ese pedido ya no está pendiente.';
        }
        return $motivos;
    }

    public function validar()
    {
        $errores = array();
        if (!in_array($this->estado, array('pendiente', 'aceptado', 'rechazado'))) {
            $errores[] = 'El estado del pedido no es uno de los previstos.';
        }
        # El que pide es el capitan del equipo.
        $capitan = $this->equipo->getCapitan();
        if ($capitan === null || (int)$capitan->getIdUsuario() !== (int)$this->usuario->getIdUsuario()) {
            $errores[] = 'El lugar en una liga lo pide el capitán del equipo.';
        }
        return $errores;
    }

    #endregion
}
