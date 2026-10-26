<?php
# Controlador: panel del organizador

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/ParticipanteRepositorio.php';
require_once __DIR__ . '/../models/PedidoInscripcionRepositorio.php';
require_once __DIR__ . '/../models/FixtureRepositorio.php';
require_once __DIR__ . '/../models/ResultadoRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_crear))   { $ruta_crear   = 'crearController.php'; }
if (!isset($ruta_panel))   { $ruta_panel   = 'panelController.php'; }
if (!isset($ruta_partidos)) { $ruta_partidos = 'partidosController.php'; }
if (!isset($ruta_reportes)) { $ruta_reportes = 'reportesController.php'; }

$titulo  = 'Panel del organizador';
$mensaje = '';
$errores = array();
$errores_equipo = array();   # id de liga => mensaje, al lado del campo
$valor_equipo   = array();   # id de liga => lo que se escribio

# Los avisos despues de una accion.
$avisos = array(
    'liga-creada'        => 'El torneo queda creado, con la inscripción abierta.',
    'equipo-anotado'     => 'El equipo queda anotado.',
    'pedido-aceptado'    => 'El pedido queda aceptado: el equipo juega la liga.',
    'pedido-rechazado'   => 'El pedido queda rechazado.',
    'inscripcion-cerrada'=> 'La inscripción queda cerrada. Falta armar el calendario.',
    'fixture-armado'     => 'El calendario queda armado.',
    'fixture-rehecho'    => 'El calendario queda armado de nuevo.',
    'liga-cancelada'     => 'El torneo queda cancelado: deja de verse en el sitio, y lo jugado queda guardado.'
);

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

# 3. Las acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion    = (isset($_POST['accion']) && is_string($_POST['accion'])) ? $_POST['accion'] : '';
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

    } elseif (in_array($accion, array('agregar', 'cerrar', 'fixture', 'rehacer', 'cancelar'))) {
        # La liga, y que sea de esta cuenta.
        $torneo = ($id_torneo > 0) ? $torneos->buscarPorId($id_torneo) : null;
        if ($torneo === null || (int)$torneo->getOrganizador()->getIdUsuario() !== $id_usuario) {
            $errores[] = 'Esa competencia no está a cargo de esta cuenta.';

        } elseif ($accion === 'cancelar') {
            # Pide su casilla marcada: sin ella no se cancela nada.
            if (!isset($_POST['confirmo_cancelar'])) {
                $errores[] = 'Cancelar la liga pide marcar la confirmación.';
            } else {
                $resultado = $torneos->cancelar($torneo, $organizador);
                $errores = $resultado['errores'];
                if (empty($errores)) {
                    $detalle = $torneo->getNombre();
                    if ($resultado['rechazados'] > 0) {
                        $detalle .= ' · ' . $resultado['rechazados']
                                  . ($resultado['rechazados'] === 1 ? ' pedido sin lugar' : ' pedidos sin lugar');
                    }
                    $auditorias->registrar(new Auditoria(null, $organizador, 'torneo', 'cancelacion',
                        $id_torneo, mb_substr($detalle, 0, 255), $ip));
                    $hecho = 'liga-cancelada';
                }
            }

        } elseif ($accion === 'agregar') {
            $nombre = (isset($_POST['equipo']) && is_string($_POST['equipo'])) ? trim($_POST['equipo']) : '';
            $valor_equipo[$id_torneo] = $nombre;
            # En una liga individual, cada jugador se anota con su cuenta.
            $resultado = $torneo->esPorEquipos() ? $participantes->agregarEquipo($torneo, $organizador, $nombre)
                       : array('errores' => array('En esta competencia compiten jugadores: cada uno se anota con su cuenta.'));
            if (empty($resultado['errores'])) {
                # El equipo nuevo (si hubo que crearlo) y la inscripcion, cada una con el id de su propia fila.
                if ($resultado['id_equipo_nuevo'] !== null) {
                    $auditorias->registrar(new Auditoria(null, $organizador, 'equipo', 'alta',
                        $resultado['id_equipo_nuevo'], mb_substr('Equipo ' . $nombre, 0, 255), $ip));
                }
                $auditorias->registrar(new Auditoria(null, $organizador, 'participante', 'inscripcion',
                    $resultado['id_participante'], mb_substr($nombre . ' en ' . $torneo->getNombre(), 0, 255), $ip));
                $hecho = 'equipo-anotado';
            } else {
                # Van al lado del campo del equipo, en su liga.
                $errores_equipo[$id_torneo] = implode(' ', $resultado['errores']);
                $errores[] = $torneo->getNombre() . ': ' . implode(' ', $resultado['errores']);
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
            $rehacer = ($accion === 'rehacer');
            if ($rehacer && !isset($_POST['confirmo'])) {
                $errores[] = 'Rehacer el fixture pide marcar la confirmación.';
            } else {
                $resultado = $fixtures->generar($torneo, $organizador, $rehacer);
                $errores = $resultado['errores'];
                if (empty($errores)) {
                    $auditorias->registrar(new Auditoria(null, $organizador, 'torneo', 'fixture',
                        $id_torneo, mb_substr($torneo->getNombre() . ' · ' . $resultado['fechas'] . ($torneo->esLiga() ? ' fechas, ' : ' rondas, ')
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
        # Una liga cancelada ya no esta en el panel: se vuelve arriba.
        header('Location: ' . $ruta_panel . '?aviso=' . $hecho
               . (($hecho === 'liga-cancelada') ? '' : '#liga-' . $id_torneo));
        exit;
    }
}

if (isset($_GET['aviso']) && is_string($_GET['aviso']) && isset($avisos[$_GET['aviso']]) && empty($errores)) {
    $mensaje = $avisos[$_GET['aviso']];
}

$ligas = $torneos->listarDeOrganizador($id_usuario);
$equipos_de   = array();   # id de liga => participantes
$pendientes_de = array();  # id de liga => pedidos pendientes
$campeon_de    = array();
if (is_array($ligas)) {
    foreach ($ligas as $fila) {
        $id = (int)$fila['torneo']->getIdTorneo();
        $equipos_de[$id] = $participantes->listarDeTorneo($id);
        $pendientes_de[$id] = $fila['torneo']->tieneInscripcionAbierta() ? $pedidos->pendientesDe($id) : array();
        if ($fila['torneo']->getEstado() === 'finalizado') {
            $todos  = $participantes->listarDeTorneo($id, true);
            $rondas = ($todos === null) ? null : $fixtures->fechasDe($id, $todos);
            $campeon_de[$id] = ($rondas === null) ? null : ResultadoRepositorio::campeon($fila['torneo'], $todos, $rondas);
        }
    }
}

$conexion->close();

include __DIR__ . '/../panel.php';
