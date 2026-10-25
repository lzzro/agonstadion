<?php
# =====================================================================
# Controlador: fechas y resultados de una liga (rondas y resultados, en
# una eliminacion directa o un suizo)
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Fase 3 del motor de torneos (CU-15 Cargar resultado, CU-16 Publicar
# ronda). Una pagina por liga, para quien la organiza:
# partidosController.php?id=N muestra la fecha en juego, y con
# &fecha=M, la fecha M. Desde ahi se cargan y corrigen resultados, se
# programa cada partido (dia, hora y lugar), se marca uno en vivo y se
# cierra la fecha. Al cerrar la ultima, la liga termina con su campeon.
# En la eliminacion directa y el suizo es lo mismo, ronda por ronda: al
# cerrar una se arma la siguiente (ver ResultadoRepositorio).
#
# Es solo para cuentas con el rol organizador, comprobado ACA en cada
# pedido, con la cuenta de la sesion leida de la base (igual que el
# panel):
#   sin sesion vigente   a la pagina de acceso
#   sin el rol, al abrir la pagina: al perfil, a la tarjeta de los
#                        roles, con un aviso
#   sin el rol, con un POST armado a mano: 403, sin hacer nada
# Y la liga tiene que ser de esa cuenta: con el numero de una ajena,
# la pagina dice que no esta a su cargo (404) y un POST no hace nada
# (ResultadoRepositorio lo vuelve a mirar con la fila bloqueada).
#
# Recibe cuatro formularios, que se distinguen por el campo "accion":
#   resultado      el marcador (local, visitante) o un walkover
#                  (desenlace wo-local o wo-visitante); si ya tenia,
#                  lo corrige
#   programar      dia y hora (datetime-local, vacio = a confirmar) y
#                  lugar
#   en_vivo        en_vivo = 1 lo marca en vivo, 0 lo saca
#   cerrar_fecha   cierra la fecha en juego (id_ronda)
# De cada formulario se leen solo numeros y los textos de la fecha y el
# lugar. Quien actua es siempre la cuenta de la sesion.
#
# Todo formulario trae el token de config/csrf.php. Cada accion hecha
# queda en la auditoria (resultado, programacion, cierre y final), y
# despues se vuelve a la pagina con un GET (?aviso=...): recargar no
# repite la accion.
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/ParticipanteRepositorio.php';
require_once __DIR__ . '/../models/FixtureRepositorio.php';
require_once __DIR__ . '/../models/PosicionRepositorio.php';
require_once __DIR__ . '/../models/ResultadoRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';
require_once __DIR__ . '/../fechas.php';

if (!isset($ruta_publica))  { $ruta_publica  = '../../public'; }
if (!isset($ruta_perfil))   { $ruta_perfil   = 'perfilController.php'; }
if (!isset($ruta_panel))    { $ruta_panel    = 'panelController.php'; }
if (!isset($ruta_partidos)) { $ruta_partidos = 'partidosController.php'; }

$titulo  = 'Fechas y resultados';
$mensaje = '';
$errores = array();
$valores_partido = array();   # id de partido => lo que se escribio, si hubo un error

# Los avisos despues de una accion: un codigo en la direccion, nunca un
# texto.
$avisos = array(
    'resultado'      => 'El resultado queda cargado y la tabla, al día.',
    'correccion'     => 'El resultado queda corregido y la tabla, al día.',
    'programado'     => 'El partido queda programado.',
    'en-vivo'        => 'El partido queda en vivo.',
    'fuera-de-vivo'  => 'El partido deja de estar en vivo.',
    'fecha-cerrada'  => 'La fecha queda cerrada. La siguiente queda en juego.',
    'liga-terminada' => 'La última fecha queda cerrada: la liga termina con su campeón.'
);

# Solo un numero de un texto: un campo mandado como arreglo no es un
# numero.
function numeroDe($origen, $campo)
{
    return (isset($origen[$campo]) && is_string($origen[$campo])) ? (int)$origen[$campo] : 0;
}

# Un marcador: un entero de 0 en adelante escrito como tal, o null.
function marcadorDe($campo)
{
    if (!isset($_POST[$campo]) || !is_string($_POST[$campo])) {
        return null;
    }
    $texto = trim($_POST[$campo]);
    return ($texto !== '' && ctype_digit($texto)) ? (int)$texto : null;
}

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

$cuentas = new UsuarioRepositorio($conexion);
$organizador = $cuentas->buscarPorId($id_usuario);

if ($organizador === null || !$organizador->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}
$cuentas->cargarRoles($organizador);

if (!$organizador->tieneRol('organizador')) {
    $conexion->close();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
        $errores[] = 'Los resultados los carga quien organiza la liga.';
        $persona_cabecera = $organizador;
        include __DIR__ . '/../index.php';
        exit;
    }
    header('Location: ' . $ruta_perfil . '?aviso=organizador#roles');
    exit;
}

$torneos    = new TorneoRepositorio($conexion);
$resultados = new ResultadoRepositorio($conexion);
$auditorias = new AuditoriaRepositorio($conexion);
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

# La liga de la pagina: la del formulario en un POST, la de la
# direccion en un GET.
$id_torneo = ($_SERVER['REQUEST_METHOD'] === 'POST') ? numeroDe($_POST, 'id_torneo') : numeroDe($_GET, 'id');
$fecha_pedida = numeroDe($_GET, 'fecha');

# --- 3. Las acciones ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion  = (isset($_POST['accion']) && is_string($_POST['accion'])) ? $_POST['accion'] : '';
    $id_partido = numeroDe($_POST, 'id_enfrentamiento');
    $hecho   = '';   # el codigo del aviso, si la accion salio bien
    $ancla   = '';
    $salida  = null;

    if (!csrfValido()) {
        $errores[] = rechazarCsrf();

    } elseif ($accion === 'resultado') {
        $desenlace = (isset($_POST['desenlace']) && is_string($_POST['desenlace'])) ? $_POST['desenlace'] : '';
        $salida = $resultados->cargarResultado($id_torneo, $id_partido, $organizador,
                                               marcadorDe('local'), marcadorDe('visitante'), $desenlace);
        if (empty($salida['errores'])) {
            $auditorias->registrar(new Auditoria(null, $organizador, 'enfrentamiento', 'resultado',
                $id_partido, $salida['detalle'], $ip));
            $hecho = $salida['correccion'] ? 'correccion' : 'resultado';
        }
        $ancla = 'partido-' . $id_partido;
        $valores_partido[$id_partido] = array(
            'desenlace' => $desenlace,
            'local'     => (isset($_POST['local']) && is_string($_POST['local'])) ? $_POST['local'] : '',
            'visitante' => (isset($_POST['visitante']) && is_string($_POST['visitante'])) ? $_POST['visitante'] : '');

    } elseif ($accion === 'programar') {
        $cuando = (isset($_POST['fecha_hora']) && is_string($_POST['fecha_hora'])) ? trim($_POST['fecha_hora']) : '';
        $donde  = (isset($_POST['lugar']) && is_string($_POST['lugar'])) ? trim($_POST['lugar']) : '';
        $salida = $resultados->programar($id_torneo, $id_partido, $organizador,
                                         ($cuando === '') ? null : $cuando, $donde);
        if (empty($salida['errores'])) {
            $auditorias->registrar(new Auditoria(null, $organizador, 'enfrentamiento', 'programacion',
                $id_partido, $salida['detalle'], $ip));
            $hecho = 'programado';
        }
        $ancla = 'partido-' . $id_partido;
        $valores_partido[$id_partido] = array('fecha_hora' => $cuando, 'lugar' => $donde);

    } elseif ($accion === 'en_vivo') {
        $en_vivo = (numeroDe($_POST, 'en_vivo') === 1);
        $salida = $resultados->cambiarEnVivo($id_torneo, $id_partido, $organizador, $en_vivo);
        if (empty($salida['errores'])) {
            $auditorias->registrar(new Auditoria(null, $organizador, 'enfrentamiento', 'programacion',
                $id_partido, $salida['detalle'], $ip));
            $hecho = $en_vivo ? 'en-vivo' : 'fuera-de-vivo';
        }
        $ancla = 'partido-' . $id_partido;

    } elseif ($accion === 'cerrar_fecha') {
        $id_ronda = numeroDe($_POST, 'id_ronda');
        $salida = $resultados->cerrarFecha($id_torneo, $id_ronda, $organizador);
        if (empty($salida['errores'])) {
            $auditorias->registrar(new Auditoria(null, $organizador, 'ronda', 'cierre',
                $id_ronda, $salida['detalle'], $ip));
            if ($salida['final']) {
                $auditorias->registrar(new Auditoria(null, $organizador, 'torneo', 'final', $id_torneo,
                    mb_substr($salida['detalle'] . ' · campeón: ' . $salida['campeon'], 0, 255), $ip));
                $hecho = 'liga-terminada';
            } else {
                $hecho = 'fecha-cerrada';
            }
        }
        $ancla = 'fecha';

    } else {
        $errores[] = 'Esa acción no existe en esta página.';
    }

    if ($salida !== null) {
        $errores = array_merge($errores, $salida['errores']);
        # La fecha de la accion, para volver a ella (o a la que quede en
        # juego, despues de cerrar).
        if ($salida['numero'] > 0 && $accion !== 'cerrar_fecha') {
            $fecha_pedida = $salida['numero'];
        }
    }

    if ($hecho !== '') {
        $conexion->close();
        $destino = $ruta_partidos . '?id=' . $id_torneo;
        if ($accion !== 'cerrar_fecha' && $fecha_pedida > 0) {
            $destino .= '&fecha=' . $fecha_pedida;
        }
        header('Location: ' . $destino . '&aviso=' . $hecho . '#' . $ancla);
        exit;
    }
}

# --- 4. El aviso de la accion anterior -------------------------------
if (isset($_GET['aviso']) && is_string($_GET['aviso']) && isset($avisos[$_GET['aviso']]) && empty($errores)) {
    $mensaje = $avisos[$_GET['aviso']];
}
# El mensaje del cierre, en la llave y el suizo (se ajusta abajo, con
# el torneo leido).
$aviso_pedido = (isset($_GET['aviso']) && is_string($_GET['aviso'])) ? $_GET['aviso'] : '';

# --- 5. Lo que muestra la pagina --------------------------------------
# La liga tiene que ser de esta cuenta y una liga con fixture; si no, la
# pagina lo dice y no muestra nada mas.
$torneo  = ($id_torneo > 0) ? $torneos->buscarPorId($id_torneo) : null;
$rondas  = array();
$participantes = array();
$tabla   = array();
$campeon = null;
$sin_liga = '';
if ($torneo === null || (int)$torneo->getOrganizador()->getIdUsuario() !== $id_usuario
    || $torneo->estaCancelado()) {
    $sin_liga = 'Esa competencia no está a cargo de esta cuenta.';
    http_response_code(404);
    $torneo = null;
} else {
    $participantes = (new ParticipanteRepositorio($conexion))->listarDeTorneo($id_torneo, true);
    $rondas = ($participantes === null) ? null
            : (new FixtureRepositorio($conexion))->fechasDe($id_torneo, $participantes);
    if ($rondas === null) {
        $sin_liga = 'Los partidos no se pueden leer por ahora.';
        $rondas = array();
    } elseif (empty($rondas)) {
        $sin_liga = $torneo->tieneInscripcionAbierta()
                  ? 'Con la inscripción abierta todavía no hay partidos: el calendario se arma al cerrarla.'
                  : 'Todavía no hay calendario: se arma en el panel.';
    }
    if (!empty($rondas) && $torneo->getEstado() === 'finalizado') {
        $tabla = (new PosicionRepositorio($conexion))->tablaDe($id_torneo, $participantes, $torneo->getConfiguracion());
        if ($tabla === null) { $tabla = array(); }
        $campeon = ResultadoRepositorio::campeon($torneo, $participantes, $rondas);
    }
}

$conexion->close();

if ($torneo !== null && !$torneo->esLiga() && $mensaje !== '') {
    if ($aviso_pedido === 'fecha-cerrada') {
        $mensaje = 'La ronda queda cerrada, y la siguiente queda armada.';
    } elseif ($aviso_pedido === 'liga-terminada') {
        $mensaje = 'La última ronda queda cerrada: el torneo termina con su campeón.';
    }
}

# La fecha en juego y la que se muestra: la pedida si existe; si no, la
# que esta en juego (o la ultima, con la liga terminada).
$en_juego = ResultadoRepositorio::fechaEnJuego($rondas);
$abierta  = null;
foreach ($rondas as $ronda) {
    if ($ronda->getNumero() === $fecha_pedida) { $abierta = $ronda; }
}
if ($abierta === null) {
    $abierta = ($en_juego !== null) ? $en_juego : (empty($rondas) ? null : $rondas[count($rondas) - 1]);
}

include __DIR__ . '/../partidos.php';
