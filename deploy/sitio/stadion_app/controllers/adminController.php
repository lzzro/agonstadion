<?php
# =====================================================================
# Controlador: administracion
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La pagina de la administracion del sitio (RF-24). Muestra los pedidos
# de rol pendientes (y los aprueba o los rechaza), las cuentas con sus
# roles (con la baja logica y la vuelta, RF-11, y el rol administrador
# para dar o quitar: las cuentas administrativas), los modulos de
# competencia (habilitar o deshabilitar) y el registro de auditoria, de
# a paginas y con un filtro por accion (por GET: no cambia nada).
#
# Es solo para cuentas con el rol administrador, y eso se comprueba
# ACA, en el servidor, en cada pedido: al mostrar la pagina y al
# recibir cada formulario. No alcanza con no poner el enlace: la
# direccion se puede escribir a mano, y un POST se puede armar a mano.
#   sin sesion vigente   va a la pagina de acceso, como el perfil
#   sin el rol           403 y un aviso, sin mostrar ni hacer nada
#
# Quien resuelve es siempre la cuenta de la sesion, nunca un id que
# venga del formulario. Del formulario solo se leen la accion y un
# numero (de pedido, de cuenta o de modulo), y la casilla de la baja.
# Las reglas del pedido (que siga pendiente, que no sea propio) las pone
# PedidoRol, y la base las repite con sus CHECK. Las de las cuentas
# (nunca la propia, nunca una de muestra) y las de los modulos (siempre
# queda uno habilitado), los repositorios, con la fila bloqueada.
#
# Acciones (campo "accion"): aprobar, rechazar, baja_cuenta (con la
# casilla confirmo_baja), reactivar_cuenta, dar_admin, quitar_admin,
# habilitar_modulo, deshabilitar_modulo. Todas quedan en la auditoria.
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
require_once __DIR__ . '/../models/CatalogoRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Direcciones de la cabecera y de esta misma pagina. Si quien llama no
# las dejo preparadas, vale la instalacion local. Ver apps/index.php.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }
if (!isset($ruta_reportes)) { $ruta_reportes = 'reportesController.php'; }

# Cuantas filas del registro de auditoria se muestran por pagina.
define('FILAS_AUDITORIA', 30);

$titulo  = 'Administración';
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
    $errores[] = 'Esta página es solo para la administración.';
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
    $id_pedido = (isset($_POST['id_pedido']) && is_string($_POST['id_pedido'])) ? (int)$_POST['id_pedido'] : 0;
    $id_cuenta = (isset($_POST['id_usuario']) && is_string($_POST['id_usuario'])) ? (int)$_POST['id_usuario'] : 0;
    $id_modulo = (isset($_POST['id_modulo']) && is_string($_POST['id_modulo'])) ? (int)$_POST['id_modulo'] : 0;

    if (!csrfValido()) {
        $errores[] = rechazarCsrf();

    } elseif ($accion === 'baja_cuenta' || $accion === 'reactivar_cuenta') {

        # --- La baja logica de una cuenta, o su vuelta (RF-11) ---------
        $activar = ($accion === 'reactivar_cuenta');
        if (!$activar && !isset($_POST['confirmo_baja'])) {
            $errores[] = 'Dar de baja una cuenta pide marcar la confirmación.';
        } else {
            $hecho = $cuentas->cambiarActivo($administrador, $id_cuenta, $activar);
            $errores = $hecho['errores'];
            if (empty($errores)) {
                $quien = $hecho['cuenta']->getNombreCompletoVisible();
                $mensaje = $activar ? $quien . ' vuelve a estar activa: puede iniciar sesión.'
                                    : $quien . ' queda de baja: no inicia sesión, y su historial queda.';
                $auditorias->registrar(new Auditoria(null, $administrador, 'usuario',
                    $activar ? 'modificacion' : 'baja', $id_cuenta,
                    mb_substr(($activar ? 'Reactivación de cuenta · ' : '') . $quien, 0, 255), $ip));
            }
        }

    } elseif ($accion === 'dar_admin' || $accion === 'quitar_admin') {

        # --- Las cuentas administrativas (RF-24) ------------------------
        $dar = ($accion === 'dar_admin');
        $hecho = $cuentas->cambiarAdministrador($administrador, $id_cuenta, $dar);
        $errores = $hecho['errores'];
        if (empty($errores)) {
            $quien = $hecho['cuenta']->getNombreCompletoVisible();
            $mensaje = $dar ? $quien . ' queda en la administración.' : $quien . ' deja la administración.';
            $auditorias->registrar(new Auditoria(null, $administrador, 'usuario_rol',
                $dar ? 'alta' : 'baja', $id_cuenta, mb_substr('Rol administrador · ' . $quien, 0, 255), $ip));
        }

    } elseif ($accion === 'habilitar_modulo' || $accion === 'deshabilitar_modulo') {

        # --- Los modulos de competencia (RF-24) --------------------------
        $habilitar = ($accion === 'habilitar_modulo');
        $catalogo = new CatalogoRepositorio($conexion);
        $errores = $catalogo->cambiarModulo($id_modulo, $habilitar);
        if (empty($errores)) {
            $nombre_modulo = '';
            foreach ((array)$catalogo->modulos() as $m) {
                if ((int)$m->getIdModulo() === $id_modulo) { $nombre_modulo = $m->getNombre(); }
            }
            $mensaje = $habilitar ? 'El módulo ' . $nombre_modulo . ' queda habilitado.'
                                  : 'El módulo ' . $nombre_modulo . ' queda deshabilitado: los torneos que ya lo usan siguen igual.';
            $auditorias->registrar(new Auditoria(null, $administrador, 'modulo_competencia', 'modificacion', $id_modulo,
                mb_substr(($habilitar ? 'Habilitación del módulo ' : 'Deshabilitación del módulo ') . $nombre_modulo, 0, 255), $ip));
        }

    } elseif ($accion !== 'aprobar' && $accion !== 'rechazar') {
        $errores[] = 'Esa acción no existe en esta página.';

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
$modulos    = (new CatalogoRepositorio($conexion))->modulos();

# El registro, de a paginas, con el filtro por accion (por GET). Un
# filtro que no es una accion prevista, o una pagina que no es un
# numero, valen como "todas" y la primera.
$filtro = (isset($_GET['filtro']) && is_string($_GET['filtro']) && in_array($_GET['filtro'], Auditoria::acciones(), true))
        ? $_GET['filtro'] : '';
$pagina = (isset($_GET['pagina']) && is_string($_GET['pagina']) && ctype_digit($_GET['pagina'])) ? max(1, (int)$_GET['pagina']) : 1;
$total_registro = $auditorias->contar($filtro);
$paginas = ($total_registro === null) ? 1 : max(1, (int)ceil($total_registro / FILAS_AUDITORIA));
if ($pagina > $paginas) {
    $pagina = $paginas;
}
$registro = $auditorias->listar($filtro, $pagina, FILAS_AUDITORIA);

$conexion->close();

include __DIR__ . '/../admin.php';
