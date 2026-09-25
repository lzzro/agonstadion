<?php
# =====================================================================
# Modelo: PedidoRolRepositorio   ->   tabla "pedido_rol" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para los pedidos de rol. Siempre con sentencias
# preparadas.
#
# No borra nunca: el usuario de la aplicacion no tiene DELETE sobre la
# tabla (ver el DCL de schema.sql). Un pedido se crea y se resuelve.
#
# Resolver un pedido son dos cambios que van juntos: el pedido pasa a
# aprobado (o rechazado) y, si se aprueba, la cuenta recibe el rol en
# usuario_rol. Van en una transaccion: o quedan los dos, o ninguno.
# El UPDATE ademas solo toca el pedido si sigue pendiente y no es de
# quien lo resuelve; si dos administradores lo aprueban a la vez, el
# segundo no encuentra nada que cambiar.
# =====================================================================

require_once __DIR__ . '/PedidoRol.php';

class PedidoRolRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Crea un pedido pendiente de $usuario por el rol $nombre_rol.
    # Devuelve un arreglo de errores, vacio si quedo registrado.
    # Que no haya dos pendientes lo garantiza la base (uq_pedido_pendiente):
    # si ya hay uno, el INSERT falla con 1062 y se avisa.
    public function crear(Usuario $usuario, $nombre_rol)
    {
        $sql = 'INSERT INTO pedido_rol (id_usuario, id_rol)
                SELECT ?, id_rol FROM rol WHERE nombre = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('El pedido no se puede registrar por ahora.');
        }
        $id = (int)$usuario->getIdUsuario();
        $sentencia->bind_param('is', $id, $nombre_rol);

        if (!$sentencia->execute()) {
            $duplicado = ($sentencia->errno === 1062);
            $sentencia->close();
            return array($duplicado ? 'Ya hay un pedido en revision.'
                                    : 'El pedido no se puede registrar por ahora.');
        }
        $filas = $sentencia->affected_rows;
        $sentencia->close();
        if ($filas !== 1) {
            return array('El pedido no se puede registrar por ahora.');
        }
        return array();
    }

    # El ultimo pedido de una cuenta por un rol, o null si nunca pidio.
    public function ultimoDe($id_usuario, $nombre_rol)
    {
        $pedidos = $this->buscar('WHERE p.id_usuario = ? AND r.nombre = ?
                                  ORDER BY p.fecha_pedido DESC, p.id_pedido_rol DESC LIMIT 1',
                                 'is', array((int)$id_usuario, $nombre_rol));
        return empty($pedidos) ? null : $pedidos[0];
    }

    public function buscarPorId($id_pedido_rol)
    {
        $pedidos = $this->buscar('WHERE p.id_pedido_rol = ?', 'i', array((int)$id_pedido_rol));
        return empty($pedidos) ? null : $pedidos[0];
    }

    # Los pendientes, del mas viejo al mas nuevo: se atienden en orden.
    public function listarPendientes()
    {
        return $this->buscar("WHERE p.estado = 'pendiente'
                              ORDER BY p.fecha_pedido, p.id_pedido_rol", '', array());
    }

    # Aprueba ($aprobar = true) o rechaza el pedido. Devuelve un arreglo de
    # errores, vacio si quedo resuelto. Primero las reglas del dominio
    # (PedidoRol), despues la base, que repite las que puede.
    public function resolver(PedidoRol $pedido, Usuario $administrador, $aprobar)
    {
        $motivos = $pedido->motivosParaNoResolver($administrador);
        if (!empty($motivos)) {
            return $motivos;
        }

        $estado   = $aprobar ? 'aprobado' : 'rechazado';
        $id_admin = (int)$administrador->getIdUsuario();
        $id_ped   = (int)$pedido->getIdPedidoRol();

        $this->conexion->begin_transaction();

        $sql = "UPDATE pedido_rol
                   SET estado = ?, fecha_resolucion = NOW(), id_usuario_resuelve = ?
                 WHERE id_pedido_rol = ? AND estado = 'pendiente' AND id_usuario <> ?";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('El pedido no se puede resolver por ahora.');
        }
        $sentencia->bind_param('siii', $estado, $id_admin, $id_ped, $id_admin);
        $bien  = $sentencia->execute();
        $filas = $sentencia->affected_rows;
        $sentencia->close();

        if (!$bien || $filas !== 1) {
            $this->conexion->rollback();
            return array('Ese pedido ya no esta pendiente.');
        }

        if ($aprobar) {
            $sql = 'INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)';
            $sentencia = $this->conexion->prepare($sql);
            if ($sentencia === false) {
                $this->conexion->rollback();
                return array('El pedido no se puede resolver por ahora.');
            }
            $id_usuario = (int)$pedido->getUsuario()->getIdUsuario();
            $id_rol     = (int)$pedido->getRol()->getIdRol();
            $sentencia->bind_param('ii', $id_usuario, $id_rol);
            # 1062: la cuenta ya tenia el rol. No es un error: queda igual.
            if (!$sentencia->execute() && $sentencia->errno !== 1062) {
                $sentencia->close();
                $this->conexion->rollback();
                return array('El pedido no se puede resolver por ahora.');
            }
            $sentencia->close();
        }

        $this->conexion->commit();
        return array();
    }

    # --- Auxiliar ---------------------------------------------------
    # Una sola consulta para todas las busquedas: cambia el WHERE.
    private function buscar($condicion, $tipos, $valores)
    {
        $sql = 'SELECT p.id_pedido_rol, p.estado, p.fecha_pedido, p.fecha_resolucion,
                       u.id_usuario, u.correo, u.nombre, u.apellido, u.alias, u.activo,
                       u.fecha_alta, u.foto_perfil,
                       r.id_rol, r.nombre AS rol, r.descripcion,
                       a.id_usuario AS id_resuelve, a.nombre AS res_nombre, a.apellido AS res_apellido
                FROM pedido_rol p
                    INNER JOIN usuario u ON u.id_usuario = p.id_usuario
                    INNER JOIN rol r     ON r.id_rol = p.id_rol
                    LEFT JOIN usuario a  ON a.id_usuario = p.id_usuario_resuelve ' . $condicion;
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
            $usuario = new Usuario($fila['id_usuario'], $fila['correo'], null, $fila['nombre'],
                                   $fila['apellido'], $fila['alias'], null, $fila['activo'],
                                   $fila['fecha_alta'], $fila['foto_perfil']);
            $resuelve = ($fila['id_resuelve'] === null) ? null
                      : new Usuario($fila['id_resuelve'], null, null, $fila['res_nombre'], $fila['res_apellido']);
            $pedidos[] = new PedidoRol($fila['id_pedido_rol'], $usuario,
                                       new Rol($fila['id_rol'], $fila['rol'], $fila['descripcion']),
                                       $fila['estado'], $fila['fecha_pedido'],
                                       $fila['fecha_resolucion'], $resuelve);
        }
        $sentencia->close();
        return $pedidos;
    }

    #endregion
}
