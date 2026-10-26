<?php
# Administracion, equipos, reportes y mis resultados - Stadion (Agon)

require __DIR__ . '/comun.php';
require_once $APLICACION . '/models/CatalogoRepositorio.php';
require_once $APLICACION . '/models/UsuarioRepositorio.php';
require_once $APLICACION . '/models/EquipoRepositorio.php';
require_once $APLICACION . '/models/TorneoRepositorio.php';
require_once $APLICACION . '/models/ParticipanteRepositorio.php';
require_once $APLICACION . '/models/FixtureRepositorio.php';
require_once $APLICACION . '/models/ResultadoRepositorio.php';
require_once $APLICACION . '/models/EnfrentamientoRepositorio.php';
require_once $APLICACION . '/models/ReporteRepositorio.php';

echo "===== Administracion, equipos, reportes y mis resultados =====\n";
$conexion = conexionDePrueba();
$conexion->query("SET time_zone = '-03:00'");

if (!chk((int)valor($conexion, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = 'modulo_competencia' AND COLUMN_NAME = 'habilitado'") === 1,
         'la base tiene la 008 (modulo_competencia.habilitado)')) { fin(); }

$modulos_antes = valor($conexion, 'SELECT GROUP_CONCAT(CONCAT(id_modulo, ":", habilitado) ORDER BY id_modulo) FROM modulo_competencia');
$creado = array('usuarios' => array(), 'torneos' => array(), 'equipos' => array());
register_shutdown_function(function () use ($conexion, &$creado, $modulos_antes) {
    foreach (explode(',', (string)$modulos_antes) as $par) {
        list($id, $hab) = array_map('intval', explode(':', $par));
        $conexion->query("UPDATE modulo_competencia SET habilitado = $hab WHERE id_modulo = $id");
    }
    foreach ($creado['torneos'] as $id) {
        $id = (int)$id;
        $conexion->query("DELETE FROM ronda WHERE id_torneo = $id");
        $conexion->query("DELETE FROM participante WHERE id_torneo = $id");
        $conexion->query("DELETE FROM torneo WHERE id_torneo = $id");
    }
    foreach ($creado['equipos'] as $id) {
        $conexion->query('DELETE FROM equipo WHERE id_equipo = ' . (int)$id);
    }
    foreach ($creado['usuarios'] as $id) {
        $id = (int)$id;
        $conexion->query("DELETE FROM usuario WHERE id_usuario = $id AND correo LIKE 'prueba-%@ejemplo.invalid'");
    }
    $quedan = empty($creado['usuarios']) ? 0 : (int)valor($conexion, 'SELECT COUNT(*) FROM usuario WHERE id_usuario IN ('
                                                         . implode(',', array_map('intval', $creado['usuarios'])) . ')');
    $iguales = valor($conexion, 'SELECT GROUP_CONCAT(CONCAT(id_modulo, ":", habilitado) ORDER BY id_modulo) FROM modulo_competencia') === $modulos_antes;
    echo ($quedan === 0 && $iguales) ? "  (lo creado quedo borrado y los modulos como estaban)\n" : "  ATENCION: quedaron cuentas de la prueba o los modulos cambiados\n";
});

$usuarios = new UsuarioRepositorio($conexion);
function cuenta($nombre, $apellido, $roles = array())
{
    global $conexion, $usuarios, $creado;
    $correo = 'prueba-' . bin2hex(random_bytes(4)) . '@ejemplo.invalid';
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $sentencia = $conexion->prepare('INSERT INTO usuario (correo, hash_password, nombre, apellido) VALUES (?, ?, ?, ?)');
    $sentencia->bind_param('ssss', $correo, $hash, $nombre, $apellido);
    $sentencia->execute();
    $id = (int)$conexion->insert_id;
    $creado['usuarios'][] = $id;
    foreach (array_merge(array('jugador'), $roles) as $rol) {
        $conexion->query("INSERT INTO usuario_rol (id_usuario, id_rol) SELECT $id, id_rol FROM rol WHERE nombre = '$rol'");
    }
    $u = $usuarios->buscarPorId($id);
    $usuarios->cargarRoles($u);
    return $u;
}

# 1. Modulos
echo "-- Modulos (RF-24)\n";
$catalogo = new CatalogoRepositorio($conexion);
$conexion->query('UPDATE modulo_competencia SET habilitado = 1');
$m = $catalogo->modulos();
chk(implode(',', array_keys($m)) === 'Liga,Eliminación directa,Suizo' && $m['Suizo']->estaHabilitado(), 'tres modulos, habilitados');
chk(empty($catalogo->cambiarModulo($m['Suizo']->getIdModulo(), false)) && !$catalogo->modulos()['Suizo']->estaHabilitado(),
    'el suizo se deshabilita');
chk($catalogo->cambiarModulo($m['Suizo']->getIdModulo(), false) === array('Ese módulo ya está deshabilitado.'), 'dos veces, no');
chk(empty($catalogo->cambiarModulo($m['Eliminación directa']->getIdModulo(), false)), 'la eliminacion directa tambien');
chk($catalogo->cambiarModulo($m['Liga']->getIdModulo(), false) === array('Siempre queda al menos un módulo de competencia habilitado.')
    && $catalogo->modulos()['Liga']->estaHabilitado(), 'el ultimo habilitado, no');
chk($catalogo->cambiarModulo(999999, true) === array('Ese módulo no existe.'), 'un modulo que no existe, no');
chk(empty($catalogo->cambiarModulo($m['Suizo']->getIdModulo(), true)) && empty($catalogo->cambiarModulo($m['Eliminación directa']->getIdModulo(), true)),
    'y vuelven a habilitarse');

# 2. Cuentas
echo "-- Cuentas (RF-11 y RF-24)\n";
$admin = cuenta('Ariadna', 'Minos', array('administrador'));
$otra  = cuenta('Teseo', 'Atenas');
$id_muestra = (int)valor($conexion, 'SELECT MIN(id_usuario) FROM usuario WHERE de_muestra = 1');
$r = $usuarios->cambiarActivo($admin, $admin->getIdUsuario(), false);
chk(count($r['errores']) === 1 && strpos($r['errores'][0], 'La cuenta propia') === 0, 'la cuenta propia no se da de baja');
if ($id_muestra > 0) {
    chk($usuarios->cambiarActivo($admin, $id_muestra, false)['errores'] === array('Una cuenta de muestra no cambia de estado.'),
        'una de muestra tampoco');
}
$r = $usuarios->cambiarActivo($admin, $otra->getIdUsuario(), false);
chk(empty($r['errores']) && valor($conexion, 'SELECT activo FROM usuario WHERE id_usuario = ' . (int)$otra->getIdUsuario()) === '0',
    'otra cuenta, de baja (activo = 0, la fila queda)');
chk($usuarios->cambiarActivo($admin, $otra->getIdUsuario(), false)['errores'] === array('Esa cuenta ya está de baja.'), 'dos veces, no');
chk($usuarios->cambiarAdministrador($admin, $otra->getIdUsuario(), true)['errores'] === array('El rol administrador va solo a una cuenta activa.'),
    'a una cuenta de baja no se le da el rol administrador');
chk(empty($usuarios->cambiarActivo($admin, $otra->getIdUsuario(), true)['errores'])
    && valor($conexion, 'SELECT activo FROM usuario WHERE id_usuario = ' . (int)$otra->getIdUsuario()) === '1', 'y vuelve');
chk(empty($usuarios->cambiarAdministrador($admin, $otra->getIdUsuario(), true)['errores'])
    && (int)valor($conexion, "SELECT COUNT(*) FROM usuario_rol ur JOIN rol r USING (id_rol) WHERE ur.id_usuario = " . (int)$otra->getIdUsuario()
                             . " AND r.nombre = 'administrador'") === 1, 'el rol administrador, dado');
chk($usuarios->cambiarAdministrador($admin, $otra->getIdUsuario(), true)['errores'] === array('Esa cuenta ya es de la administración.'), 'dos veces, no');
$r = $usuarios->cambiarAdministrador($admin, $admin->getIdUsuario(), false);
chk(count($r['errores']) === 1 && strpos($r['errores'][0], 'El rol propio') === 0, 'el rol propio no se quita');
chk(empty($usuarios->cambiarAdministrador($admin, $otra->getIdUsuario(), false)['errores'])
    && (int)valor($conexion, "SELECT COUNT(*) FROM usuario_rol WHERE id_usuario = " . (int)$otra->getIdUsuario()) === 1,
    'y se quita (le queda el de jugador)');
chk($usuarios->cambiarActivo($admin, 999999999, false)['errores'] === array('Esa cuenta no existe.'), 'una cuenta que no existe, no');

# 3. Integrantes
echo "-- Integrantes con su dorsal (RF-12)\n";
$capitan = cuenta('Jasón', 'Yolco');
$heracles = cuenta('Heracles', 'Tebas');
$orfeo = cuenta('Orfeo', 'Tracia');
$de_baja = cuenta('Medea', 'Cólquide');
$conexion->query('UPDATE usuario SET activo = 0 WHERE id_usuario = ' . (int)$de_baja->getIdUsuario());
$equipos = new EquipoRepositorio($conexion);
$argo = new Equipo(null, 'Argonautas ' . bin2hex(random_bytes(3)), null, $capitan);
chk(empty($equipos->crearConCapitan($argo)), 'el capitan arma su equipo');
$creado['equipos'][] = (int)$argo->getIdEquipo();
$id_argo = (int)$argo->getIdEquipo();
$r = $equipos->sumarIntegrante($id_argo, $capitan, $heracles->getCorreo(), 12);
chk(empty($r['errores']) && $r['nombre'] === 'Heracles Tebas', 'suma una cuenta por su correo, con el dorsal 12');
chk($equipos->sumarIntegrante($id_argo, $capitan, $heracles->getCorreo(), 3)['errores'] === array('Heracles Tebas ya integra el equipo.'),
    'dos veces, no');
chk($equipos->sumarIntegrante($id_argo, $capitan, $orfeo->getCorreo(), 12)['errores'] === array('El dorsal 12 ya está ocupado en este equipo.'),
    'un dorsal repetido, no');
chk($equipos->sumarIntegrante($id_argo, $capitan, $orfeo->getCorreo(), 100)['errores'] === array('El dorsal va de 0 a 99.'), 'el dorsal 100, no');
chk($equipos->sumarIntegrante($id_argo, $capitan, $de_baja->getCorreo(), null)['errores'] === array('Ninguna cuenta activa con ese correo.')
    && $equipos->sumarIntegrante($id_argo, $capitan, 'nadie-' . bin2hex(random_bytes(3)) . '@ejemplo.invalid', null)['errores'] === array('Ninguna cuenta activa con ese correo.'),
    'una cuenta de baja y un correo de nadie dan el mismo aviso');
chk($equipos->sumarIntegrante($id_argo, $heracles, $orfeo->getCorreo(), null)['errores'] === array('Ese equipo no lo capitanea esta cuenta.'),
    'quien no es el capitan, no suma');
chk(empty($equipos->sumarIntegrante($id_argo, $capitan, $orfeo->getCorreo(), null)['errores']), 'sin dorsal, si');
chk(empty($equipos->cambiarDorsal($id_argo, $capitan, $capitan->getIdUsuario(), 1)), 'el capitan se pone el 1');
chk($equipos->cambiarDorsal($id_argo, $capitan, $orfeo->getIdUsuario(), 1) === array('El dorsal 1 ya está ocupado en este equipo.'),
    'otro con el mismo, no');
$integ = array();
foreach ($equipos->integrantesDe($argo) as $i) { $integ[] = $i->getUsuario()->getNombre() . ':' . (($i->getDorsal() === null) ? '-' : $i->getDorsal()); }
chk(implode(' ', $integ) === 'Jasón:1 Heracles:12 Orfeo:-', 'los integrantes: el capitan primero, despues por dorsal (' . implode(' ', $integ) . ')');
chk($equipos->bajaIntegrante($id_argo, $capitan, $capitan->getIdUsuario()) === array('El capitán no se da de baja de su equipo.'),
    'el capitan no se da de baja');
chk(empty($equipos->bajaIntegrante($id_argo, $capitan, $heracles->getIdUsuario()))
    && valor($conexion, "SELECT CONCAT(activo, '|', IFNULL(dorsal, 'NULL')) FROM integrante_equipo WHERE id_equipo = $id_argo AND id_usuario = "
                        . (int)$heracles->getIdUsuario()) === '0|NULL', 'la baja: la fila queda, sin dorsal');
chk(empty($equipos->cambiarDorsal($id_argo, $capitan, $orfeo->getIdUsuario(), 12)), 'y el 12 queda libre');
$ajenos = $equipos->listarDeIntegrante($orfeo);
chk(count($ajenos) === 1 && $ajenos[0]['dorsal'] === 12 && $ajenos[0]['equipo']->getCapitan()->getNombre() === 'Jasón',
    'Orfeo ve el equipo que integra, con su dorsal y su capitan');
chk(empty($equipos->sumarIntegrante($id_argo, $capitan, $heracles->getCorreo(), 7)['errores'])
    && valor($conexion, "SELECT CONCAT(activo, '|', dorsal) FROM integrante_equipo WHERE id_equipo = $id_argo AND id_usuario = "
                        . (int)$heracles->getIdUsuario()) === '1|7', 'Heracles vuelve, con otro dorsal');

# 4. Una eliminacion directa jugada hasta la final
echo "-- Mis resultados (RF-26) y reportes (RF-28)\n";
$org = cuenta('Atalanta', 'Arcadia', array('organizador'));
$jugadores = array(cuenta('Aquiles', 'Ftía'), cuenta('Héctor', 'Troya'), cuenta('Ayax', 'Salamina'), cuenta('Paris', 'Troya'));
$catalogos = new CatalogoRepositorio($conexion);
$ajedrez = null;
foreach ($catalogos->disciplinas() as $d) { if ($d->getNombre() === 'Ajedrez') { $ajedrez = $d; } }
$torneos = new TorneoRepositorio($conexion);
$copa = new Torneo(null, 'Copa de Troya ' . bin2hex(random_bytes(3)), $ajedrez, $catalogos->tipoPorNombre('Individual'),
                   $catalogos->moduloPorNombre('Eliminación directa'), $org, null, null, 4, 'Club de Ajedrez del Cerro', 'inscripcion');
chk(empty($torneos->crearLiga($copa, new ConfiguracionTorneo(null, 3, 1, 0, 0))), 'una eliminacion directa individual de 4, con su sede');
$creado['torneos'][] = (int)$copa->getIdTorneo();
$id_copa = (int)$copa->getIdTorneo();
chk(valor($conexion, "SELECT sede FROM torneo WHERE id_torneo = $id_copa") === 'Club de Ajedrez del Cerro', 'la sede queda guardada (RF-13)');
$inscritos = new ParticipanteRepositorio($conexion);
$part = array();
foreach ($jugadores as $j) {
    $r = $inscritos->inscribirJugador($torneos->buscarPorId($id_copa), $j);
    $part[$j->getNombre()] = (int)$r['id_participante'];
}
$torneos->cerrarInscripcion($torneos->buscarPorId($id_copa), $org);
(new FixtureRepositorio($conexion))->generar($torneos->buscarPorId($id_copa), $org, false);
$res = new ResultadoRepositorio($conexion);
$fixtures = new FixtureRepositorio($conexion);
# Cada ronda: gana el de nombre que va primero en el alfabeto, 2 a 1.
for ($vuelta = 0; $vuelta < 2; $vuelta++) {
    $rondas = $fixtures->fechasDe($id_copa, $inscritos->listarDeTorneo($id_copa, true));
    $ronda = $rondas[count($rondas) - 1];
    foreach ($ronda->getEnfrentamientos() as $e) {
        if ($e->esLibre()) { continue; }
        $local_gana = strcmp($e->getLocal()->getNombreVisible(), $e->getVisitante()->getNombreVisible()) < 0;
        $res->cargarResultado($id_copa, $e->getIdEnfrentamiento(), $org, $local_gana ? 2 : 1, $local_gana ? 1 : 2, 'marcador');
    }
    $res->cerrarFecha($id_copa, $ronda->getIdRonda(), $org);
}
chk(valor($conexion, "SELECT estado FROM torneo WHERE id_torneo = $id_copa") === 'finalizado', 'semifinales y final jugadas: la copa termina');

$partidos = new EnfrentamientoRepositorio($conexion);
$de_aquiles = $partidos->jugadosDe(array($part['Aquiles']));
$de_paris   = $partidos->jugadosDe(array($part['Paris']));
chk(count($de_aquiles) === 2 && $de_aquiles[0]['ronda']->getNombre() === 'Final' && $de_aquiles[1]['ronda']->getNombre() === 'Semifinal',
    'Aquiles: dos partidos, primero la final (la mas nueva)');
chk((int)$de_aquiles[0]['propio']->getIdParticipante() === $part['Aquiles'], 'cada partido sabe de que lado juega la persona');
chk(count($de_paris) === 1, 'Paris: uno, la semifinal que pierde');
chk($partidos->jugadosDe(array()) === array(), 'sin participaciones, ningun partido');
$finalistas = 0;
foreach ($part as $nombre => $id_p) {
    foreach ($partidos->jugadosDe(array($id_p)) as $p) { if ($p['torneo']->esEliminacion() && $p['ronda']->getNombre() === 'Final') { $finalistas++; } }
}
chk($finalistas === 2, 'la final cuenta para sus dos jugadores');
$todos = $inscritos->listarDeTorneo($id_copa, true);
$campeon = ResultadoRepositorio::campeon($torneos->buscarPorId($id_copa), $todos, $fixtures->fechasDe($id_copa, $todos));
chk($campeon !== null && (int)$campeon->getIdParticipante() === $part['Aquiles'], 'y el campeon es Aquiles');

$lista = $torneos->listarParaReportes($org->getIdUsuario());
chk(count($lista) === 1 && (int)$lista[0]['torneo']->getIdTorneo() === $id_copa, 'los reportes de la organizadora: su torneo, y nada mas');
$numeros = (new ReporteRepositorio($conexion))->partidosDe(array($id_copa));
chk($numeros[$id_copa] === array('partidos' => 3, 'jugados' => 3, 'walkovers' => 0, 'empates' => 0, 'con_marcador' => 3, 'tantos' => 9),
    'los numeros del reporte: 3 partidos jugados, 9 tantos, sin walkovers ni empates');
$todas = $torneos->listarParaReportes(null);
$esta = false;
foreach ($todas as $fila) { if ((int)$fila['torneo']->getIdTorneo() === $id_copa) { $esta = true; } }
chk($esta && count($todas) >= 1, 'los de la administracion: todos, tambien este');
chk((new ReporteRepositorio($conexion))->partidosDe(array()) === array(), 'sin torneos, nada que contar');

fin();
