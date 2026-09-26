<?php
# =====================================================================
# Controlador: administracion
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La pagina de la administracion del sitio. Muestra los pedidos de rol
# pendientes (y los aprueba o los rechaza), las cuentas con sus roles y
# el registro de auditoria.
#
# Es solo para cuentas con el rol administrador, y eso se comprueba
# ACA, en el servidor, en cada pedido: al mostrar la pagina y al
# recibir cada formulario. No alcanza con no poner el enlace: la
# direccion se puede escribir a mano, y un POST se puede armar a mano.
#   sin sesion vigente   va a la pagina de acceso, como el perfil
#   sin el rol           403 y un aviso, sin mostrar ni hacer nada
#
# Quien resuelve es siempre la cuenta de la sesion, nunca un id que
# venga del formulario. Del formulario solo se leen el numero de
# pedido y si se aprueba o se rechaza. Las reglas del pedido (que siga
# pendiente, que no sea propio) las pone PedidoRol, y la base las
# repite con sus CHECK.
#
# Aprobar agrega el rol organizador a la cuenta, en la misma
# transaccion en que el pedido pasa a aprobado (ver
# PedidoRolRepositorio::resolver). Aprobar y rechazar quedan en la
# auditoria.
#
# Todo formulario trae el token de config/csrf.php: sin el, o con uno
# que no es el de esta sesion, no se resuelve nada.
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/PedidoRolRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Direcciones de la cabecera y de esta misma pagina. Si quien llama no
# las dejo preparadas, vale la instalacion local. Ver apps/index.php.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }

# Cuantas filas del registro de auditoria se muestran.
define('FILAS_AUDITORIA', 30);

$titulo  = 'Administracion';
$mensaje = '';
$errores = array();

# --- 1. Sin sesion vigente, al acceso --------------------------------
if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

# --- 2. Conexion, cuenta y rol ---------------------------------------
$conexion = conectarBD();
if ($conexion === null) {
    $errores[] = $error_bd;
    include __DIR__ . '/../index.php';
    exit;
}

$cuentas    = new UsuarioRepositorio($conexion);
$pedidos    = new PedidoRolRepositorio($conexion);
$auditorias = new AuditoriaRepositorio($conexion);

$administrador = $cuentas->buscarPorId($id_usuario);

if ($administrador === null || !$administrador->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$cuentas->cargarRoles($administrador);

# Sin el rol, nada: ni la pagina ni los formularios. El aviso no dice
# que hay adentro.
if (!$administrador->tieneRol('administrador')) {
    $conexion->close();
    http_response_code(403);
    $errores[] = 'Esta pagina es solo para la administracion.';
    $persona_cabecera = $administrador;
    include __DIR__ . '/../index.php';
    exit;
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

# --- 3. Aprobar o rechazar -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    # Solo textos: una accion mandada como arreglo (accion[]=x) queda
    # vacia, igual que en los demas controladores.
    $accion = (isset($_POST['accion']) && is_string($_POST['accion'])) ? $_POST['accion'] : '';
    $id_pedido = isset($_POST['id_pedido']) ? (int)$_POST['id_pedido'] : 0;

    if (!csrfValido()) {
        $errores[] = rechazarCsrf();

    } elseif ($accion !== 'aprobar' && $accion !== 'rechazar') {
        $errores[] = 'Solo se aprueba o se rechaza un pedido.';

    } else {
        $pedido = ($id_pedido > 0) ? $pedidos->buscarPorId($id_pedido) : null;

        if ($pedido === null) {
            $errores[] = 'Ese pedido no existe.';
        } else {
            $aprobar = ($accion === 'aprobar');
            $errores = $pedidos->resolver($pedido, $administrador, $aprobar);

            if (empty($errores)) {
                $quien = $pedido->getUsuario()->getNombreCompletoVisible();
                $rol   = $pedido->getRol()->getNombre();
                $mensaje = $aprobar
                         ? $quien . ' queda con el rol de ' . $rol . '.'
                         : 'El pedido de ' . $quien . ' queda rechazado.';

                $auditorias->registrar(new Auditoria(
                    null, $administrador, 'pedido_rol',
                    $aprobar ? 'aprobacion' : 'rechazo',
                    $pedido->getIdPedidoRol(),
                    mb_substr('Rol ' . $rol . ' para ' . $quien, 0, 255), $ip));
            }
        }
    }
}

# --- 4. Lo que muestra la pagina -------------------------------------
# null en las cuentas o en el registro quiere decir que no se pudo leer,
# para que la vista no lo confunda con "ninguna".
$pendientes = $pedidos->listarPendientes();
$lista      = $cuentas->listarConRoles();
$registro   = $auditorias->listarRecientes(FILAS_AUDITORIA);

$conexion->close();

include __DIR__ . '/../admin.php';
