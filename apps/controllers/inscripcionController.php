<?php
# Controlador: pedir lugar en una liga, o inscribirse en una individual

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/EquipoRepositorio.php';
require_once __DIR__ . '/../models/PedidoInscripcion.php';
require_once __DIR__ . '/../models/PedidoInscripcionRepositorio.php';
require_once __DIR__ . '/../models/ParticipanteRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }

$titulo  = 'Pedido de lugar';
$mensaje = '';
$errores = array();

if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}
$id_usuario = (int)$_SESSION['id_usuario'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errores[] = 'El pedido de lugar llega desde la página de la liga.';
    include __DIR__ . '/../index.php';
    exit;
}
if (!csrfValido()) {
    $errores[] = rechazarCsrf();
    include __DIR__ . '/../index.php';
    exit;
}

$id_torneo = isset($_POST['id_torneo']) ? (int)$_POST['id_torneo'] : 0;
$id_equipo = isset($_POST['id_equipo']) ? (int)$_POST['id_equipo'] : 0;

$conexion = conectarBD();
if ($conexion === null) {
    $errores[] = $error_bd;
    include __DIR__ . '/../index.php';
    exit;
}

$cuentas = new UsuarioRepositorio($conexion);
$capitan = $cuentas->buscarPorId($id_usuario);
if ($capitan === null || !$capitan->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$torneos = new TorneoRepositorio($conexion);
$torneo  = ($id_torneo > 0) ? $torneos->buscarPorId($id_torneo) : null;
if ($torneo === null) {
    $conexion->close();
    $errores[] = 'Esa liga no existe.';
    include __DIR__ . '/../index.php';
    exit;
}

# Una liga individual: la cuenta de la sesion se inscribe sola.
if (!$torneo->esPorEquipos()) {
    $codigos = array(
        'La inscripción de esa liga está cerrada.'      => 'cerrada',
        'La liga ya tiene su cupo completo.'             => 'cupo',
        'Esa cuenta ya juega esta competencia.'                 => 'ya-inscripto',
        'Una liga de muestra no recibe inscripciones.'   => 'muestra-jugador'
    );
    $salida = (new ParticipanteRepositorio($conexion))->inscribirJugador($torneo, $capitan);
    if (empty($salida['errores'])) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        (new AuditoriaRepositorio($conexion))->registrar(new Auditoria(null, $capitan,
            'participante', 'inscripcion', $salida['id_participante'],
            mb_substr($capitan->getNombreCompletoVisible() . ' en ' . $torneo->getNombre(), 0, 255), $ip));
        $codigo = 'inscripto';
    } else {
        $codigo = isset($codigos[$salida['errores'][0]]) ? $codigos[$salida['errores'][0]] : 'no-disponible';
    }
    $conexion->close();
    header('Location: ' . $ruta_publica . '/torneo.php?id=' . $id_torneo . '&aviso=' . $codigo);
    exit;
}

# El equipo, entre los que capitanea la cuenta de la sesion.
$equipo  = null;
$equipos = (new EquipoRepositorio($conexion))->listarDeCapitan($capitan);
if (is_array($equipos)) {
    foreach ($equipos as $propio) {
        if ((int)$propio->getIdEquipo() === $id_equipo) {
            $equipo = $propio;
        }
    }
}

# Cada mensaje del repositorio, con el codigo que entiende torneo.php.
$codigos = array(
    'La inscripción de esa liga está cerrada.'                => 'cerrada',
    'La liga ya tiene su cupo completo.'                       => 'cupo',
    'Ese equipo ya juega esta competencia.'                           => 'ya-juega',
    'Ese equipo ya tiene un pedido en revisión en esta competencia.'  => 'en-revision',
    'Una liga de muestra no recibe pedidos.'                   => 'muestra'
);

if ($equipo === null) {
    $codigo = 'sin-equipo';
} else {
    $pedido  = new PedidoInscripcion(null, $torneo, $equipo, $capitan);
    $errores = (new PedidoInscripcionRepositorio($conexion))->crear($pedido);
    if (empty($errores)) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        (new AuditoriaRepositorio($conexion))->registrar(new Auditoria(null, $capitan,
            'pedido_inscripcion', 'solicitud', $pedido->getIdPedidoInscripcion(),
            mb_substr($equipo->getNombre() . ' en ' . $torneo->getNombre(), 0, 255), $ip));
        $codigo = 'pedido-enviado';
    } else {
        $codigo = isset($codigos[$errores[0]]) ? $codigos[$errores[0]] : 'no-disponible';
    }
}

$conexion->close();
header('Location: ' . $ruta_publica . '/torneo.php?id=' . $id_torneo . '&aviso=' . $codigo);
exit;
