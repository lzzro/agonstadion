<?php
# =====================================================================
# Controlador: reportes de los torneos (RF-28)
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Tercera entrega. "El organizador puede consultar los reportes de sus
# torneos, y el administrador general, los de todos los torneos."
#
# Solo lee: no recibe formularios ni cambia nada. Quien ve que se
# comprueba ACA en cada pedido, con la cuenta de la sesion leida de la
# base (igual que el panel):
#   sin sesion vigente      a la pagina de acceso
#   con el rol administrador     los reportes de todos los torneos
#   con el rol organizador       los de sus torneos
#   sin ninguno de los dos  al perfil, a la tarjeta de los roles, con
#                           el aviso de que hace falta el de organizador
#
# Por torneo: inscriptos sobre el cupo, partidos jugados sobre los
# armados, tantos por partido, walkovers y empates, y el campeon (o el
# primero de la tabla, si se esta jugando). Arriba, los totales; al
# costado, los torneos por estado. Todo sale de la base en cada pedido.
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/ParticipanteRepositorio.php';
require_once __DIR__ . '/../models/FixtureRepositorio.php';
require_once __DIR__ . '/../models/ResultadoRepositorio.php';
require_once __DIR__ . '/../models/ReporteRepositorio.php';

if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_panel))   { $ruta_panel   = 'panelController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }

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

$cuentas = new UsuarioRepositorio($conexion);
$persona = $cuentas->buscarPorId($id_usuario);

if ($persona === null || !$persona->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}
$cuentas->cargarRoles($persona);

$de_todos = $persona->tieneRol('administrador');
if (!$de_todos && !$persona->tieneRol('organizador')) {
    $conexion->close();
    header('Location: ' . $ruta_perfil . '?aviso=organizador#roles');
    exit;
}

# --- 3. Los torneos y sus numeros ------------------------------------
$torneos = new TorneoRepositorio($conexion);
$lista = $torneos->listarParaReportes($de_todos ? null : $id_usuario);

$numeros   = array();   # id de torneo => los de ReporteRepositorio
$primero_de = array();  # id de torneo => campeon o primero (Participante), o null
if (is_array($lista)) {
    $ids = array();
    foreach ($lista as $fila) {
        $ids[] = (int)$fila['torneo']->getIdTorneo();
    }
    $numeros = (new ReporteRepositorio($conexion))->partidosDe($ids);
    if ($numeros === null) {
        $lista = null;
    }
}
if (is_array($lista)) {
    $participantes = new ParticipanteRepositorio($conexion);
    $fixtures = new FixtureRepositorio($conexion);
    foreach ($lista as $fila) {
        $t  = $fila['torneo'];
        $id = (int)$t->getIdTorneo();
        if (!isset($numeros[$id])) {
            $numeros[$id] = ReporteRepositorio::sinPartidos();
        }
        # El campeon de uno terminado; el primero de la tabla de una liga
        # o un suizo que se esta jugando. Una llave en juego no tiene
        # primero.
        $primero_de[$id] = null;
        if ($t->getEstado() === 'finalizado'
            || ($t->getEstado() === 'en_curso' && !$t->esEliminacion() && $numeros[$id]['jugados'] > 0)) {
            $todos  = $participantes->listarDeTorneo($id, true);
            $rondas = ($todos === null) ? null : $fixtures->fechasDe($id, $todos);
            if ($rondas !== null) {
                $primero_de[$id] = ResultadoRepositorio::campeon($t, $todos, $rondas);
            }
        }
    }
}

$conexion->close();

include __DIR__ . '/../reportes.php';
