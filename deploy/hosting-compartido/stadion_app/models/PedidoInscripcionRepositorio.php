<?php
# =====================================================================
# Modelo: PedidoInscripcionRepositorio   ->   tabla "pedido_inscripcion"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para los pedidos de lugar en una liga. Aceptar un
# pedido es, en una misma transaccion, marcarlo aceptado y agregar el
# equipo como participante: si una de las dos cosas falla, no queda
# ninguna. Antes se bloquea la fila del torneo (ver
# TorneoRepositorio::bloquearYContar) y se cuenta el cupo con el
# bloqueo puesto: dos aceptaciones a la vez no pasan del cupo.
#
# Un solo pendiente por equipo y liga lo garantiza la base
# (uq_pinsc_pendiente): si ya hay uno, el INSERT falla con 1062.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/PedidoInscripcion.php';
require_once __DIR__ . '/TorneoRepositorio.php';

class PedidoInscripcionRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Registra el pedido. Las reglas que no son de la base (que la liga
    # tenga la inscripcion abierta y lugar, que el equipo no este ya
    # adentro) se miran aca; que no haya dos pendientes lo cuida la base.
    # Devuelve un arreglo de errores, vacio si quedo registrado.
    public function crear(PedidoInscripcion $pedido)
    {
        $errores = $pedido->validar();
        if (!empty($errores)) {
            return $errores;
        }
        $torneo = $pedido->getTorneo();
        if (!$torneo->tieneInscripcionAbierta()) {
            return array('La inscripción de esa liga está cerrada.');
        }

        $torneos   = new TorneoRepositorio($this->conexion);
        $id_torneo = (int)$torneo->getIdTorneo();
        $id_equipo = (int)$pedido->getEquipo()->getIdEquipo();

        if ($torneos->contarParticipantes($id_torneo) >= $torneo->getMaxParticipantes()) {
            return array('La liga ya tiene su cupo completo.');
        }
        if ($this->equipoInscripto($id_torneo, $id_equipo)) {
            return array('Ese equipo ya juega esta liga.');
        }

        $sql = 'INSERT INTO pedido_inscripcion (id_torneo, id_equipo, id_usuario) VALUES (?, ?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('El pedido no se puede registrar por ahora.');
        }
        $id_usuario = (int)$pedido->getUsuario()->getIdUsuario();
        $sentencia->bind_param('iii', $id_torneo, $id_equipo, $id_usuario);
        if (!$sentencia->execute()) {
            $duplicado = ($sentencia->errno === 1062);
            $sentencia->close();
            return array($duplicado ? 'Ese equipo ya tiene un pedido en revisión en esta liga.'
                                    : 'El pedido no se puede registrar por ahora.');
        }
        $pedido->setIdPedidoInscripcion($this->conexion->insert_id);
        $sentencia->close();
        return array();
    }

    public function buscarPorId($id_pedido)
    {
        $pedidos = $this->buscar('WHERE pi.id_pedido_inscripcion = ?', 'i', array((int)$id_pedido));
        return empty($pedidos) ? null : $pedidos[0];
    }

    # Los pendientes de una liga, del mas viejo al mas nuevo.
    public function pendientesDe($id_torneo)
    {
        return $this->buscar("WHERE pi.id_torneo = ? AND pi.estado = 'pendiente'
                              ORDER BY pi.fecha_pedido, pi.id_pedido_inscripcion",
                             'i', array((int)$id_torneo));
    }

    # El ultimo pedido de cada equipo de un capitan en una liga, para que
    # torneo.php diga en que quedo. Arreglo indexado por id de equipo.
    public function ultimosDeCapitan($id_torneo, $id_usuario)
    {
        $pedidos = $this->buscar('WHERE pi.id_torneo = ? AND e.id_usuario_capitan = ?
                                  ORDER BY pi.fecha_pedido, pi.id_pedido_inscripcion',
                                 'ii', array((int)$id_torneo, (int)$id_usuario));
        $ultimos = array();
        foreach ($pedidos as $pedido) {
            # Ordenados del mas viejo al mas nuevo: queda el ultimo.
            $ultimos[(int)$pedido->getEquipo()->getIdEquipo()] = $pedido;
        }
        return $ultimos;
    }

    # Acepta ($aceptar = true) o rechaza el pedido. Devuelve un arreglo de
    # errores, vacio si quedo resuelto. Primero las reglas del dominio
    # (PedidoInscripcion), despues la base.
    public function resolver(PedidoInscripcion $pedido, Usuario $organizador, $aceptar)
    {
        $motivos = $pedido->motivosParaNoResolver($organizador);
        if (!empty($motivos)) {
            return $motivos;
        }

        $torneos   = new TorneoRepositorio($this->conexion);
        $id_torneo = (int)$pedido->getTorneo()->getIdTorneo();
        $id_equipo = (int)$pedido->getEquipo()->getIdEquipo();
        $id_org    = (int)$organizador->getIdUsuario();
        $id_ped    = (int)$pedido->getIdPedidoInscripcion();

        $this->conexion->begin_transaction();

        $cantidad = $torneos->bloquearYContar($id_torneo, $id_org);
        if ($cantidad === null) {
            $this->conexion->rollback();
            return array('Esa liga no está a cargo de esta cuenta.');
        }

        if ($aceptar) {
            # Con el bloqueo puesto: el estado y el cupo de este momento.
            $torneo = $torneos->buscarPorId($id_torneo);
            if ($torneo === null || !$torneo->tieneInscripcionAbierta()) {
                $this->conexion->rollback();
                return array('La inscripción de esa liga está cerrada.');
            }
            if ($cantidad >= $torneo->getMaxParticipantes()) {
                $this->conexion->rollback();
                return array('La liga ya tiene su cupo completo: el pedido sigue pendiente.');
            }
        }

        $estado = $aceptar ? 'aceptado' : 'rechazado';
        $sql = "UPDATE pedido_inscripcion
                   SET estado = ?, fecha_resolucion = NOW(), id_usuario_resuelve = ?
                 WHERE id_pedido_inscripcion = ? AND id_torneo = ? AND estado = 'pendiente'";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('El pedido no se puede resolver por ahora.');
        }
        $sentencia->bind_param('siii', $estado, $id_org, $id_ped, $id_torneo);
        $bien  = $sentencia->execute();
        $filas = $sentencia->affected_rows;
        $sentencia->close();
        if (!$bien || $filas !== 1) {
            $this->conexion->rollback();
            return array('Ese pedido ya no está pendiente.');
        }

        if ($aceptar) {
            $sql = "INSERT INTO participante (id_torneo, id_equipo, estado) VALUES (?, ?, 'inscripto')";
            $sentencia = $this->conexion->prepare($sql);
            if ($sentencia === false) {
                $this->conexion->rollback();
                return array('El pedido no se puede resolver por ahora.');
            }
            $sentencia->bind_param('ii', $id_torneo, $id_equipo);
            if (!$sentencia->execute()) {
                $duplicado = ($sentencia->errno === 1062);
                $sentencia->close();
                $this->conexion->rollback();
                return array($duplicado ? 'Ese equipo ya juega esta liga.'
                                        : 'El pedido no se puede resolver por ahora.');
            }
            $sentencia->close();
        }

        $this->conexion->commit();
        return array();
    }

    # Si el equipo ya es participante de la liga (sin contar las bajas).
    public function equipoInscripto($id_torneo, $id_equipo)
    {
        $sql = "SELECT 1 FROM participante
                WHERE id_torneo = ? AND id_equipo = ? AND estado <> 'baja' LIMIT 1";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return false;
        }
        $t = (int)$id_torneo;
        $e = (int)$id_equipo;
        $sentencia->bind_param('ii', $t, $e);
        $sentencia->execute();
        $hay = ($sentencia->get_result()->fetch_assoc() !== null);
        $sentencia->close();
        return $hay;
    }

    # --- Auxiliar ---------------------------------------------------
    # Una sola consulta para todas las busquedas: cambia el WHERE.
    private function buscar($condicion, $tipos, $valores)
    {
        $sql = 'SELECT pi.id_pedido_inscripcion, pi.estado AS estado_pedido, pi.fecha_pedido,
                       pi.fecha_resolucion,
                       e.id_equipo, e.nombre AS equipo, e.ciudad, e.id_usuario_capitan,
                       u.id_usuario, u.nombre AS pide_nombre, u.apellido AS pide_apellido,
                       ' . TorneoRepositorio::columnas() . '
                FROM pedido_inscripcion pi
                    INNER JOIN equipo e  ON e.id_equipo = pi.id_equipo
                    INNER JOIN usuario u ON u.id_usuario = pi.id_usuario
                    INNER JOIN torneo t  ON t.id_torneo = pi.id_torneo
                    ' . TorneoRepositorio::uniones() . ' ' . $condicion;
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array();
        }
        if ($tipos !== '') {
            $sentencia->bind_param($tipos, ...$valores);
        }
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $pedidos = array();
        while ($fila = $resultado->fetch_assoc()) {
            $pide = new Usuario($fila['id_usuario'], null, null, $fila['pide_nombre'], $fila['pide_apellido']);
            $capitan = ($fila['id_usuario_capitan'] !== null
                        && (int)$fila['id_usuario_capitan'] === (int)$fila['id_usuario']) ? $pide : null;
            $equipo = new Equipo($fila['id_equipo'], $fila['equipo'], $fila['ciudad'], $capitan);
            $pedidos[] = new PedidoInscripcion($fila['id_pedido_inscripcion'],
                                               TorneoRepositorio::construirTorneo($fila),
                                               $equipo, $pide, $fila['estado_pedido'],
                                               $fila['fecha_pedido'], $fila['fecha_resolucion']);
        }
        $sentencia->close();
        return $pedidos;
    }

    #endregion
}
