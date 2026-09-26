<?php
# =====================================================================
# Controlador: panel del organizador
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Fase 2 del motor de torneos. Reemplaza a la maqueta panel.html (la
# cuenta fija "Club Sur"): muestra las ligas de la cuenta de la sesion,
# con su estado, los equipos anotados sobre el cupo y los pedidos
# pendientes, y recibe las acciones de cada liga.
#
# Es solo para cuentas con el rol organizador, comprobado ACA en cada
# pedido, con la cuenta de la sesion leida de la base (igual que
# crearController.php):
#   sin sesion vigente   a la pagina de acceso
#   sin el rol, al abrir el panel: al perfil, a la tarjeta de los roles,
#                        con un aviso
#   sin el rol, con un POST armado a mano: 403, sin hacer nada
#
# Recibe seis formularios, que se distinguen por el campo "accion":
#   agregar     anotar un equipo a mano, por nombre (ver
#               ParticipanteRepositorio::agregarEquipo)
#   aceptar     aceptar el pedido de lugar de un capitan
#   rechazar    rechazarlo
#   cerrar      cerrar la inscripcion (con 4 equipos o mas)
#   fixture     armar el fixture (con la inscripcion cerrada)
#   rehacer     borrar el fixture y armarlo de nuevo: solo sin
#               resultados, y con la casilla de confirmacion marcada
# De cada formulario se leen solo numeros (el de la liga o el del
# pedido), el nombre del equipo y la casilla. Quien actua es siempre la
# cuenta de la sesion, y cada repositorio comprueba, con la fila del
# torneo bloqueada, que la liga sea de esa cuenta: un numero de liga
# ajena cambiado a mano no hace nada.
#
# Todo formulario trae el token de config/csrf.php. Cada accion hecha
# queda en la auditoria, y despues se vuelve al panel con un GET
# (?aviso=...): recargar la pagina no repite la accion.
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/ParticipanteRepositorio.php';
require_once __DIR__ . '/../models/PedidoInscripcionRepositorio.php';
require_once __DIR__ . '/../models/FixtureRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_crear))   { $ruta_crear   = 'crearController.php'; }
if (!isset($ruta_panel))   { $ruta_panel   = 'panelController.php'; }

$titulo  = 'Panel del organizador';
$mensaje = '';
$errores = array();
$errores_equipo = array();   # id de liga => mensaje, al lado del campo
$valor_equipo   = array();   # id de liga => lo que se escribio

# Los avisos despues de una accion. Llegan por la direccion como un
# codigo, nunca como texto: la direccion no puede poner palabras en la
# pagina.
$avisos = array(
    'liga-creada'        => 'La liga queda creada, con la inscripción abierta.',
    'equipo-anotado'     => 'El equipo queda anotado.',
    'pedido-aceptado'    => 'El pedido queda aceptado: el equipo juega la liga.',
    'pedido-rechazado'   => 'El pedido queda rechazado.',
    'inscripcion-cerrada'=> 'La inscripción queda cerrada. Falta el fixture.',
    'fixture-armado'     => 'El fixture queda armado.',
    'fixture-rehecho'    => 'El fixture queda armado de nuevo.'
);

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
        $errores[] = 'El panel es de quien organiza ligas.';
        $persona_cabecera = $organizador;
        include __DIR__ . '/../index.php';
        exit;
    }
    header('Location: ' . $ruta_perfil . '?aviso=organizador#roles');
    exit;
}

$torneos       = new TorneoRepositorio($conexion);
$participantes = new ParticipanteRepositorio($conexion);
$pedidos       = new PedidoInscripcionRepositorio($conexion);
$fixtures      = new FixtureRepositorio($conexion);
$auditorias    = new AuditoriaRepositorio($conexion);
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

# --- 3. Las acciones ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion    = isset($_POST['accion'])    ? $_POST['accion']         : '';
    $id_torneo = isset($_POST['id_torneo']) ? (int)$_POST['id_torneo'] : 0;
    $id_pedido = isset($_POST['id_pedido']) ? (int)$_POST['id_pedido'] : 0;
    $hecho     = '';   # el codigo del aviso, si la accion salio bien

    if (!csrfValido()) {
        $errores[] = rechazarCsrf();

    } elseif ($accion === 'aceptar' || $accion === 'rechazar') {
        $pedido = ($id_pedido > 0) ? $pedidos->buscarPorId($id_pedido) : null;
        if ($pedido === null) {
            $errores[] = 'Ese pedido no existe.';
        } else {
            $aceptar = ($accion === 'aceptar');
            $errores = $pedidos->resolver($pedido, $organizador, $aceptar);
            if (empty($errores)) {
                $id_torneo = (int)$pedido->getTorneo()->getIdTorneo();
                $auditorias->registrar(new Auditoria(null, $organizador, 'pedido_inscripcion',
                    $aceptar ? 'aprobacion' : 'rechazo', $pedido->getIdPedidoInscripcion(),
                    mb_substr($pedido->getEquipo()->getNombre() . ' en ' . $pedido->getTorneo()->getNombre(), 0, 255), $ip));
                $hecho = $aceptar ? 'pedido-aceptado' : 'pedido-rechazado';
            }
        }

    } elseif (in_array($accion, array('agregar', 'cerrar', 'fixture', 'rehacer'))) {
        # La liga, y que sea de esta cuenta. Los repositorios lo vuelven a
        # mirar con la fila bloqueada; esto da el aviso claro.
        $torneo = ($id_torneo > 0) ? $torneos->buscarPorId($id_torneo) : null;
        if ($torneo === null || (int)$torneo->getOrganizador()->getIdUsuario() !== $id_usuario) {
            $errores[] = 'Esa liga no está a cargo de esta cuenta.';

        } elseif ($accion === 'agregar') {
            $nombre = isset($_POST['equipo']) ? trim((string)$_POST['equipo']) : '';
            $valor_equipo[$id_torneo] = $nombre;
            $resultado = $participantes->agregarEquipo($torneo, $organizador, $nombre);
            if (empty($resultado)) {
                $auditorias->registrar(new Auditoria(null, $organizador, 'participante', 'inscripcion',
                    $id_torneo, mb_substr($nombre . ' en ' . $torneo->getNombre(), 0, 255), $ip));
                $hecho = 'equipo-anotado';
            } else {
                # Van al lado del campo del equipo, en su liga.
                $errores_equipo[$id_torneo] = implode(' ', $resultado);
                $errores[] = $torneo->getNombre() . ': ' . implode(' ', $resultado);
            }

        } elseif ($accion === 'cerrar') {
            $resultado = $torneos->cerrarInscripcion($torneo, $organizador);
            $errores = $resultado['errores'];
            if (empty($errores)) {
                $detalle = $torneo->getNombre();
                if ($resultado['rechazados'] > 0) {
                    $detalle .= ' · ' . $resultado['rechazados']
                              . ($resultado['rechazados'] === 1 ? ' pedido sin lugar' : ' pedidos sin lugar');
                }
                $auditorias->registrar(new Auditoria(null, $organizador, 'torneo', 'cierre',
                    $id_torneo, mb_substr($detalle, 0, 255), $ip));
                $hecho = 'inscripcion-cerrada';
            }

        } else {
            # fixture o rehacer. Rehacer pide la casilla marcada: sin
            # ella no se borra nada.
            $rehacer = ($accion === 'rehacer');
            if ($rehacer && !isset($_POST['confirmo'])) {
                $errores[] = 'Rehacer el fixture pide marcar la confirmación.';
            } else {
                $resultado = $fixtures->generar($torneo, $organizador, $rehacer);
                $errores = $resultado['errores'];
                if (empty($errores)) {
                    $auditorias->registrar(new Auditoria(null, $organizador, 'torneo', 'fixture',
                        $id_torneo, mb_substr($torneo->getNombre() . ' · ' . $resultado['fechas'] . ' fechas, '
                                              . $resultado['partidos'] . ' partidos'
                                              . ($rehacer ? ' · rehecho' : ''), 0, 255), $ip));
                    $hecho = $rehacer ? 'fixture-rehecho' : 'fixture-armado';
                }
            }
        }

    } else {
        $errores[] = 'Esa acción no existe en el panel.';
    }

    if ($hecho !== '') {
        $conexion->close();
        header('Location: ' . $ruta_panel . '?aviso=' . $hecho . '#liga-' . $id_torneo);
        exit;
    }
}

# --- 4. El aviso de la accion anterior -------------------------------
if (isset($_GET['aviso']) && isset($avisos[$_GET['aviso']]) && empty($errores)) {
    $mensaje = $avisos[$_GET['aviso']];
}

# --- 5. Lo que muestra el panel ---------------------------------------
# null quiere decir que no se pudo leer, para que la vista no lo
# confunda con "ninguna liga".
$ligas = $torneos->listarDeOrganizador($id_usuario);
$equipos_de   = array();   # id de liga => participantes
$pendientes_de = array();  # id de liga => pedidos pendientes
if (is_array($ligas)) {
    foreach ($ligas as $fila) {
        $id = (int)$fila['torneo']->getIdTorneo();
        $equipos_de[$id] = $participantes->listarDeTorneo($id);
        $pendientes_de[$id] = $fila['torneo']->tieneInscripcionAbierta() ? $pedidos->pendientesDe($id) : array();
    }
}

$conexion->close();

include __DIR__ . '/../panel.php';
