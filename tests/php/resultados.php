<?php
# Resultados, fechas y final de una liga (fase 3) - Stadion (Agon)

require __DIR__ . '/comun.php';
require_once $APLICACION . '/models/ConfiguracionTorneo.php';
require_once $APLICACION . '/models/Resultado.php';
require_once $APLICACION . '/models/Enfrentamiento.php';
require_once $APLICACION . '/models/TablaPosiciones.php';
require_once $APLICACION . '/models/TorneoRepositorio.php';
require_once $APLICACION . '/models/ParticipanteRepositorio.php';
require_once $APLICACION . '/models/FixtureRepositorio.php';
require_once $APLICACION . '/models/PosicionRepositorio.php';
require_once $APLICACION . '/models/ResultadoRepositorio.php';
require_once $APLICACION . '/models/CatalogoRepositorio.php';
require_once $APLICACION . '/models/UsuarioRepositorio.php';

echo "===== Resultados, fechas y final (fase 3) =====\n";

# 1. Los modelos
echo "-- Modelos\n";
$serie3 = new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 3);
$serie5 = new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 5);
$serie7 = new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 7);
$unico  = new ConfiguracionTorneo(1);
$sin_empate = new ConfiguracionTorneo(1, 3, 1, 0, 0);

chk(empty($serie3->validar()) && empty($serie5->validar()) && empty($serie7->validar()), 'al mejor de 3, 5 y 7 sin empate: validos');
chk(!empty((new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 4))->validar()), 'al mejor de 4, no');
chk(!empty((new ConfiguracionTorneo(1, 3, 1, 0, 1, 0, 0, null, null, 'diferencia', 3))->validar()), 'una serie que admite empate, no');
chk($serie3->ganaSerieCon() === 2 && $serie5->ganaSerieCon() === 3 && $serie7->ganaSerieCon() === 4 && $unico->ganaSerieCon() === null,
    'la serie se gana con 2, 3 y 4; un partido unico no es serie');
chk($serie3->marcadoresDeSerie() === '2-0 o 2-1' && $serie7->marcadoresDeSerie() === '4-0, 4-1, 4-2 o 4-3', 'los marcadores de la serie, en palabras');

$validos3 = array();
for ($l = 0; $l <= 4; $l++) {
    for ($v = 0; $v <= 4; $v++) {
        if (empty($serie3->erroresMarcador($l, $v))) { $validos3[] = $l . '-' . $v; }
    }
}
chk(implode(' ', $validos3) === '0-2 1-2 2-0 2-1', 'al mejor de 3 valen solo 2-0, 2-1, 1-2 y 0-2 (' . implode(' ', $validos3) . ')');
$validos5 = 0;
for ($l = 0; $l <= 6; $l++) { for ($v = 0; $v <= 6; $v++) { if (empty($serie5->erroresMarcador($l, $v))) { $validos5++; } } }
chk($validos5 === 6, 'al mejor de 5, seis marcadores posibles');
chk(empty($serie3->erroresMarcador(0, 0, true)), 'un walkover no se mira por el marcador');
chk(empty($unico->erroresMarcador(1, 1)) && !empty($sin_empate->erroresMarcador(1, 1)) && empty($sin_empate->erroresMarcador(3, 1)),
    'el empate: si en la liga que lo admite, no en la que no');
chk(!empty($unico->erroresMarcador(-1, 0)) && !empty($unico->erroresMarcador(0, 501)) && empty($unico->erroresMarcador(500, 0)),
    'cada lado de 0 a 500');

$a = new Participante(1, null, new Equipo(1, 'Alfa'), 'confirmado');
$b = new Participante(2, null, new Equipo(2, 'Beta'), 'confirmado');
$p = new Enfrentamiento(1, 1, $a, $b);
chk(!empty($p->asignarResultado(new Resultado(2, 1, $b))), 'el ganador tiene que ser el del marcador mas alto');
chk(!empty((new Resultado(0, 0, null, 1))->validar()), 'un walkover sin ganador, no');
chk(!empty((new Resultado(2, 0, $a, 1))->validar()), 'un walkover con marcador, no');
chk(empty($p->asignarResultado(new Resultado(0, 0, $b, 1))) && $p->estaJugado(), 'un walkover para el visitante, 0 a 0: jugado');
chk(!empty($p->cambiarEnVivo(true)), 'un partido jugado no vuelve a en vivo');
chk(!empty($p->programar('2026-11-01 18:00', 'Cancha 1')), 'un partido jugado no se reprograma');

$q = new Enfrentamiento(2, 2, $b, $a);
chk(empty($q->cambiarEnVivo(true)) && $q->estaEnVivo() && empty($q->cambiarEnVivo(false)) && $q->getEstado() === 'programado',
    'programado, en vivo y de vuelta');
chk(empty($q->programar('2026-11-01T18:30', '  Cancha 2 ')) && $q->getFechaHora() === '2026-11-01 18:30:00' && $q->getLugar() === 'Cancha 2',
    'programar guarda la fecha como la base y el lugar sin espacios de mas');
chk(!empty($q->programar('2026-02-30 10:00', '')) && !empty($q->programar('2026-11-01 25:00', '')) && !empty($q->programar('mañana', '')),
    'fechas que no existen, no');
chk(!empty($q->programar(null, str_repeat('x', 81))) && empty($q->programar(null, '')) && $q->getFechaHora() === null && $q->getLugar() === null,
    'el lugar hasta 80 caracteres; todo vacio es "a confirmar"');
$libre = new Enfrentamiento(3, 3, $a, null);
chk(!empty($libre->programar('2026-11-01 18:00', '')) && !empty($libre->cambiarEnVivo(true)), 'un libre no se programa ni va en vivo');

# La tabla, por el ganador: el walkover suma la victoria sin tantos.
$ronda = new Ronda(1, 1, 'Fecha 1');
$ronda->cargarEnfrentamiento($p);
$r = new Enfrentamiento(4, 2, $a, $b, null, null, 'programado');
$r->cargarResultado(new Resultado(5, 0, $a));          # resultado sin jugar: no cuenta
$ronda->cargarEnfrentamiento($r);
$tabla = new TablaPosiciones($unico, array(1 => $a, 2 => $b));
$tabla->sumarRondas(array($ronda));
$filas = $tabla->getPosiciones();
chk($filas[2]->getGanados() === 1 && $filas[1]->getPerdidos() === 1 && $filas[1]->getFavor() === 0 && $filas[2]->getFavor() === 0
    && $filas[1]->getPartidosJugados() === 1,
    'la tabla suma el walkover como victoria y derrota, 0 a 0, y no cuenta un partido sin jugar');

# 2. Contra la base
echo "-- Contra la base\n";
$conexion = conexionDePrueba();
$conexion->query("SET time_zone = '-03:00'");

if (!chk((int)valor($conexion, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = 'configuracion_torneo' AND COLUMN_NAME = 'mejor_de'") === 1,
         'la base tiene la 006 (configuracion_torneo.mejor_de)')) { fin(); }

$marca = bin2hex(random_bytes(4));
$creado = array('usuarios' => array(), 'torneos' => array(), 'equipos' => array());

function limpiar($conexion, $creado)
{
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
}
register_shutdown_function(function () use ($conexion, &$creado) {
    limpiar($conexion, $creado);
    $quedan = (int)valor($conexion, "SELECT COUNT(*) FROM usuario WHERE correo LIKE 'prueba-%@ejemplo.invalid'
                                      AND id_usuario IN (" . (empty($creado['usuarios']) ? '0' : implode(',', array_map('intval', $creado['usuarios']))) . ')');
    echo ($quedan === 0) ? "  (lo creado por la prueba quedo borrado)\n" : "  ATENCION: quedaron $quedan cuentas de la prueba\n";
});

$usuarios = new UsuarioRepositorio($conexion);
function cuentaDePrueba($conexion, $usuarios, &$creado, $nombre)
{
    $correo = 'prueba-' . bin2hex(random_bytes(4)) . '@ejemplo.invalid';
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $sentencia = $conexion->prepare('INSERT INTO usuario (correo, hash_password, nombre, apellido) VALUES (?, ?, ?, ?)');
    $apellido = 'Prueba';
    $sentencia->bind_param('ssss', $correo, $hash, $nombre, $apellido);
    $sentencia->execute();
    $id = (int)$conexion->insert_id;
    $creado['usuarios'][] = $id;
    $conexion->query("INSERT INTO usuario_rol (id_usuario, id_rol) SELECT $id, id_rol FROM rol WHERE nombre = 'organizador'");
    $cuenta = $usuarios->buscarPorId($id);
    $usuarios->cargarRoles($cuenta);
    return $cuenta;
}
$org  = cuentaDePrueba($conexion, $usuarios, $creado, 'Organiza');
$otro = cuentaDePrueba($conexion, $usuarios, $creado, 'Ajena');

$catalogos = new CatalogoRepositorio($conexion);
$torneos   = new TorneoRepositorio($conexion);
$inscritos = new ParticipanteRepositorio($conexion);
$fixtures  = new FixtureRepositorio($conexion);
$repo      = new ResultadoRepositorio($conexion);
$disciplinas = $catalogos->disciplinas();
$esports = null;
foreach ($disciplinas as $d) { if ($d->getNombre() === 'Esports') { $esports = $d; } }

# Una liga con sus equipos, la inscripcion cerrada y el fixture armado.
function ligaDePrueba($nombre, $equipos, $config, &$creado)
{
    global $conexion, $catalogos, $torneos, $inscritos, $fixtures, $org, $esports;
    $torneo = new Torneo(null, $nombre, $esports, $catalogos->tipoPorNombre('Por equipos'),
                         $catalogos->moduloPorNombre('Liga'), $org, null, null, 16, null, 'inscripcion');
    $errores = $torneos->crearLiga($torneo, $config);
    if (!empty($errores)) { echo '  ' . implode(' ', $errores) . "\n"; return null; }
    $creado['torneos'][] = (int)$torneo->getIdTorneo();
    foreach ($equipos as $equipo) {
        $r = $inscritos->agregarEquipo($torneo, $org, $equipo);
        if ($r['id_equipo_nuevo'] !== null) { $creado['equipos'][] = (int)$r['id_equipo_nuevo']; }
    }
    $torneos->cerrarInscripcion($torneo, $org);
    $fixtures->generar($torneo, $org, false);
    return $torneos->buscarPorId($torneo->getIdTorneo());
}

# Las fechas de la liga, leidas de nuevo de la base.
function fechas($id)
{
    global $inscritos, $fixtures;
    return $fixtures->fechasDe($id, $inscritos->listarDeTorneo($id, true));
}
# La tabla guardada, como texto "Equipo G-E-P favor:contra", en orden.
function tablaGuardada($torneo)
{
    global $conexion, $inscritos;
    $id = (int)$torneo->getIdTorneo();
    $filas = (new PosicionRepositorio($conexion))->tablaDe($id, $inscritos->listarDeTorneo($id, true), $torneo->getConfiguracion());
    $texto = array();
    foreach ($filas as $f) {
        $texto[$f->getNombreVisible()] = $f->getGanados() . '-' . $f->getEmpatados() . '-' . $f->getPerdidos() . ' ' . $f->getFavor() . ':' . $f->getContra();
    }
    return $texto;
}
# La tabla recalculada desde los partidos guardados, igual que la otra.
function tablaRecalculada($torneo)
{
    $id = (int)$torneo->getIdTorneo();
    global $inscritos;
    $tabla = new TablaPosiciones($torneo->getConfiguracion(), $inscritos->listarDeTorneo($id, true));
    $tabla->sumarRondas(fechas($id));
    $texto = array();
    foreach ($tabla->getOrdenadas() as $f) {
        $texto[$f->getNombreVisible()] = $f->getGanados() . '-' . $f->getEmpatados() . '-' . $f->getPerdidos() . ' ' . $f->getFavor() . ':' . $f->getContra();
    }
    return $texto;
}
function primeroJugable($ronda)
{
    foreach ($ronda->getEnfrentamientos() as $e) { if (!$e->esLibre()) { return $e; } }
    return null;
}
function libreDe($ronda)
{
    foreach ($ronda->getEnfrentamientos() as $e) { if ($e->esLibre()) { return $e; } }
    return null;
}

$serie = new ConfiguracionTorneo(null, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 3);
$liga  = ligaDePrueba('Liga Series ' . $marca, array("Alfa $marca", "Beta $marca", "Gama $marca", "Delta $marca", "Épsilon $marca"), $serie, $creado);
if (!chk($liga !== null && $liga->estaEnCurso() && $liga->getConfiguracion()->getMejorDe() === 3,
         'una liga al mejor de 3 se crea con su formato y queda en curso')) { fin(); }
$id = (int)$liga->getIdTorneo();
$rondas = fechas($id);
chk(count($rondas) === 5, 'cinco equipos: cinco fechas, con un libre en cada una');

$f1 = $rondas[0];
$f2 = $rondas[1];
$e1 = primeroJugable($f1);
$e2 = primeroJugable($f2);

# Permisos y turno.
$r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $otro, 2, 0, 'marcador');
chk(in_array('Esa competencia no está a cargo de esta cuenta.', $r['errores'], true), 'otra cuenta no carga resultados en la liga');
$r = $repo->cargarResultado($id, $e2->getIdEnfrentamiento(), $org, 2, 0, 'marcador');
chk(in_array('Los resultados se cargan en la fecha en juego, la Fecha 1.', $r['errores'], true), 'en la fecha 2 todavia no');
$r = $repo->cargarResultado($id, libreDe($f1)->getIdEnfrentamiento(), $org, 2, 0, 'marcador');
chk(!empty($r['errores']), 'un libre no lleva resultado');
$otra_liga = ligaDePrueba('Liga Otra ' . $marca, array("Uno $marca", "Dos $marca", "Tres $marca", "Cuatro $marca"), new ConfiguracionTorneo(null), $creado);
$ajeno = primeroJugable(fechas((int)$otra_liga->getIdTorneo())[0]);
$r = $repo->cargarResultado($id, $ajeno->getIdEnfrentamiento(), $org, 2, 0, 'marcador');
chk(in_array('Ese partido no es de esta competencia.', $r['errores'], true), 'un partido de otra liga (aunque sea propia), no');

# El marcador de la serie.
foreach (array(array(1, 1), array(3, 0), array(2, 2), array(1, 0)) as $m) {
    $r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, $m[0], $m[1], 'marcador');
    chk(!empty($r['errores']), 'al mejor de 3, ' . $m[0] . '-' . $m[1] . ' no');
}
$r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, null, 1, 'marcador');
chk(!empty($r['errores']), 'sin marcador de un lado, no');
$r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, 2, 1, 'empate');
chk(!empty($r['errores']), 'un desenlace inventado, no');
chk((int)valor($conexion, 'SELECT COUNT(*) FROM resultado WHERE id_enfrentamiento = ' . (int)$e1->getIdEnfrentamiento()) === 0,
    'nada de eso quedo guardado');

# Cargar, corregir, walkover.
$local = $e1->getLocal()->getNombreVisible();
$visit = $e1->getVisitante()->getNombreVisible();
$r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, 2, 1, 'marcador');
$t = tablaGuardada($liga);
chk(empty($r['errores']) && !$r['correccion'] && $t[$local] === '1-0-0 2:1' && $t[$visit] === '0-0-1 1:2',
    'un 2-1 queda cargado y la tabla al dia (' . $t[$local] . ' / ' . $t[$visit] . ')');
chk(valor($conexion, 'SELECT estado FROM enfrentamiento WHERE id_enfrentamiento = ' . (int)$e1->getIdEnfrentamiento()) === 'jugado'
    && valor($conexion, 'SELECT estado FROM ronda WHERE id_ronda = ' . (int)$f1->getIdRonda()) === 'en_curso',
    'el partido queda jugado y la fecha, en curso');
chk((int)valor($conexion, 'SELECT id_participante_ganador FROM resultado WHERE id_enfrentamiento = ' . (int)$e1->getIdEnfrentamiento())
    === (int)$e1->getLocal()->getIdParticipante()
    && (int)valor($conexion, 'SELECT id_usuario_carga FROM resultado WHERE id_enfrentamiento = ' . (int)$e1->getIdEnfrentamiento())
    === (int)$org->getIdUsuario(),
    'el ganador y quien carga, guardados');
chk(strpos($r['detalle'], $local . ' 2-1 ' . $visit) !== false, 'el detalle para la auditoria nombra el marcador');

$r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, 0, 2, 'marcador');
$t = tablaGuardada($liga);
chk(empty($r['errores']) && $r['correccion'] && $t[$local] === '0-0-1 0:2' && $t[$visit] === '1-0-0 2:0',
    'corregido a 0-2: la tabla no guarda restos del anterior');
$r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, null, null, 'wo-local');
$t = tablaGuardada($liga);
chk(empty($r['errores']) && $t[$local] === '1-0-0 0:0' && $t[$visit] === '0-0-1 0:0'
    && valor($conexion, 'SELECT CONCAT(puntaje_local, "-", puntaje_visitante, "-", walkover) FROM resultado WHERE id_enfrentamiento = '
                        . (int)$e1->getIdEnfrentamiento()) === '0-0-1',
    'walkover a favor del local: victoria sin tantos, guardada 0 a 0');
chk(strpos($r['detalle'], 'W.O. a favor de ' . $local) !== false, 'el detalle nombra el walkover');

# En vivo y programar.
$e1b = null;
foreach ($f1->getEnfrentamientos() as $e) { if (!$e->esLibre() && $e !== $e1) { $e1b = $e; } }
$r = $repo->cambiarEnVivo($id, $e1b->getIdEnfrentamiento(), $org, true);
chk(empty($r['errores']) && valor($conexion, 'SELECT estado FROM enfrentamiento WHERE id_enfrentamiento = ' . (int)$e1b->getIdEnfrentamiento()) === 'en_vivo',
    'un partido de la fecha en juego queda en vivo');
$r = $repo->cambiarEnVivo($id, $e2->getIdEnfrentamiento(), $org, true);
chk(!empty($r['errores']), 'uno de la fecha 2, todavia no');
$r = $repo->cambiarEnVivo($id, $e1->getIdEnfrentamiento(), $org, true);
chk(!empty($r['errores']), 'uno jugado no vuelve a en vivo');
$r = $repo->cambiarEnVivo($id, $e1b->getIdEnfrentamiento(), $org, false);
chk(empty($r['errores']) && valor($conexion, 'SELECT estado FROM enfrentamiento WHERE id_enfrentamiento = ' . (int)$e1b->getIdEnfrentamiento()) === 'programado',
    'y sale de en vivo');

$r = $repo->programar($id, $e2->getIdEnfrentamiento(), $org, '2026-11-14T20:30', 'Sala 2');
$fila = $conexion->query('SELECT fecha_hora, lugar FROM enfrentamiento WHERE id_enfrentamiento = ' . (int)$e2->getIdEnfrentamiento())->fetch_assoc();
$ronda2 = $conexion->query('SELECT fecha_inicio, fecha_fin FROM ronda WHERE id_ronda = ' . (int)$f2->getIdRonda())->fetch_assoc();
chk(empty($r['errores']) && $fila['fecha_hora'] === '2026-11-14 20:30:00' && $fila['lugar'] === 'Sala 2',
    'un partido de una fecha por jugar se programa (dia, hora y lugar)');
chk($ronda2['fecha_inicio'] === '2026-11-14' && $ronda2['fecha_fin'] === '2026-11-14', 'la fecha toma el dia de sus partidos');
$r = $repo->programar($id, $e2->getIdEnfrentamiento(), $org, '2026-11-31 20:30', '');
chk(!empty($r['errores']), 'un dia que no existe, no');
$r = $repo->programar($id, $e2->getIdEnfrentamiento(), $otro, '2026-11-15 20:30', '');
chk(!empty($r['errores']) && valor($conexion, 'SELECT fecha_hora FROM enfrentamiento WHERE id_enfrentamiento = ' . (int)$e2->getIdEnfrentamiento()) === '2026-11-14 20:30:00',
    'otra cuenta no programa partidos de la liga');
$r = $repo->programar($id, $e1->getIdEnfrentamiento(), $org, '2026-11-15 20:30', '');
chk(!empty($r['errores']), 'un partido jugado no se reprograma');

# Cerrar la fecha: en orden y con todo jugado.
$r = $repo->cerrarFecha($id, $f1->getIdRonda(), $org);
chk(in_array('A la Fecha 1 le falta 1 resultado.', $r['errores'], true), 'la fecha 1 con un resultado pendiente no se cierra');
$r = $repo->cerrarFecha($id, $f2->getIdRonda(), $org);
chk(in_array('Las fechas se cierran en orden: primero la Fecha 1.', $r['errores'], true), 'la 2 no se cierra antes que la 1');
$r = $repo->cerrarFecha($id, $f1->getIdRonda(), $otro);
chk(!empty($r['errores']), 'otra cuenta no cierra fechas');

$cuenta = 0;
$final = null;
foreach (fechas($id) as $i => $ronda) {
    foreach ($ronda->getEnfrentamientos() as $e) {
        if ($e->esLibre() || $e->estaJugado()) { continue; }
        $cuenta++;
        $par = ($cuenta % 2 === 0);
        $r = $repo->cargarResultado($id, $e->getIdEnfrentamiento(), $org, $par ? 1 : 2, $par ? 2 : 0, 'marcador');
        if (!empty($r['errores'])) { echo '  ' . implode(' ', $r['errores']) . "\n"; }
    }
    $r = $repo->cerrarFecha($id, $ronda->getIdRonda(), $org);
    if (!empty($r['errores'])) { echo '  ' . implode(' ', $r['errores']) . "\n"; }
    if ($i === 0) {
        chk(empty($r['errores']) && !$r['final'] && valor($conexion, 'SELECT estado FROM ronda WHERE id_ronda = ' . (int)$ronda->getIdRonda()) === 'cerrada',
            'la fecha 1, completa, se cierra');
        $r2 = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, 2, 0, 'marcador');
        chk(in_array('La Fecha 1 está cerrada: sus resultados quedan como están.', $r2['errores'], true),
            'cerrada, sus resultados no se corrigen');
        $r2 = $repo->programar($id, $e1b->getIdEnfrentamiento(), $org, null, 'Otra sala');
        chk(!empty($r2['errores']), 'ni sus partidos se reprograman');
    }
    $final = $r;
}
chk($cuenta === 9, 'diez partidos en la liga: uno ya cargado y nueve mas');
$fila = $conexion->query("SELECT estado, fecha_fin, CURDATE() AS hoy FROM torneo WHERE id_torneo = $id")->fetch_assoc();
chk($final !== null && $final['final'] && $fila['estado'] === 'finalizado' && $fila['fecha_fin'] === $fila['hoy'],
    'la ultima fecha cerrada termina la liga, con la fecha de hoy');
$liga = $torneos->buscarPorId($id);
$guardada = tablaGuardada($liga);
chk($guardada === tablaRecalculada($liga), 'la tabla guardada es la que sale de los partidos');
chk($final['campeon'] === array_keys($guardada)[0], 'el campeon es el primero de la tabla (' . $final['campeon'] . ')');
$r = $repo->cargarResultado($id, $e1->getIdEnfrentamiento(), $org, 2, 0, 'marcador');
chk(in_array('La liga está finalizada: sus partidos quedan como están.', $r['errores'], true), 'con la liga terminada, nada cambia');
chk((int)valor($conexion, "SELECT COUNT(*) FROM torneo WHERE id_torneo = $id AND nombre_vigente IS NULL") === 1,
    'terminada, su nombre queda libre para otra liga');

# Una liga de partido unico con empates: el 1-1 suma un empate a cada uno.
$otra = $torneos->buscarPorId((int)$otra_liga->getIdTorneo());
$e = primeroJugable(fechas((int)$otra->getIdTorneo())[0]);
$r = $repo->cargarResultado((int)$otra->getIdTorneo(), $e->getIdEnfrentamiento(), $org, 1, 1, 'marcador');
$t = tablaGuardada($otra);
chk(empty($r['errores']) && $t[$e->getLocal()->getNombreVisible()] === '0-1-0 1:1' && $t[$e->getVisitante()->getNombreVisible()] === '0-1-0 1:1'
    && valor($conexion, 'SELECT id_participante_ganador FROM resultado WHERE id_enfrentamiento = ' . (int)$e->getIdEnfrentamiento()) === null,
    'en una liga con empates, el 1-1 es un empate sin ganador');

# Las restricciones nuevas de la base (la 006).
$id_otra = (int)$otra->getIdTorneo();
chk($conexion->query("UPDATE configuracion_torneo SET mejor_de = 4 WHERE id_torneo = $id_otra") === false && $conexion->errno === 4025,
    'la base no acepta al mejor de 4 (CHECK)');
chk($conexion->query("UPDATE configuracion_torneo SET mejor_de = 3 WHERE id_torneo = $id_otra") === false && $conexion->errno === 4025,
    'ni una serie que admite empate (CHECK)');

fin();
