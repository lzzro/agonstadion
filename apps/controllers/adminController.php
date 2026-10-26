<?php
# Controlador: administracion

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/PedidoRolRepositorio.php';
require_once __DIR__ . '/../models/CatalogoRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Direcciones de la cabecera y de esta misma pagina.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }
if (!isset($ruta_reportes)) { $ruta_reportes = 'reportesController.php'; }

# Cuantas filas del registro de auditoria se muestran por pagina.
define('FILAS_AUDITORIA', 30);

$titulo  = 'Administración';
$mensaje = '';
$errores = array();

# 1. Sin sesion vigente, al acceso
if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

# 2. Conexion, cuenta y rol
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

# Sin el rol, nada: ni la pagina ni los formularios.
if (!$administrador->tieneRol('administrador')) {
    $conexion->close();
    http_response_code(403);
    $errores[] = 'Esta página es solo para la administración.';
    $persona_cabecera = $administrador;
    include __DIR__ . '/../index.php';
    exit;
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

# 3. Aprobar o rechazar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = (isset($_POST['accion']) && is_string($_POST['accion'])) ? $_POST['accion'] : '';
    $id_pedido = (isset($_POST['id_pedido']) && is_string($_POST['id_pedido'])) ? (int)$_POST['id_pedido'] : 0;
    $id_cuenta = (isset($_POST['id_usuario']) && is_string($_POST['id_usuario'])) ? (int)$_POST['id_usuario'] : 0;
    $id_modulo = (isset($_POST['id_modulo']) && is_string($_POST['id_modulo'])) ? (int)$_POST['id_modulo'] : 0;

    if (!csrfValido()) {
        $errores[] = rechazarCsrf();

    } elseif ($accion === 'baja_cuenta' || $accion === 'reactivar_cuenta') {

        # La baja logica de una cuenta, o su vuelta (RF-11)
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

        # Las cuentas administrativas (RF-24)
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

        # Los modulos de competencia (RF-24)
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

$pendientes = $pedidos->listarPendientes();
$lista      = $cuentas->listarConRoles();
$modulos    = (new CatalogoRepositorio($conexion))->modulos();

# El registro, de a paginas, con el filtro por accion (por GET).
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
