<?php
# Eliminacion directa y suizo (fase 3) - Stadion (Agon)

require __DIR__ . '/comun.php';
require_once $APLICACION . '/models/Llave.php';
require_once $APLICACION . '/models/Suizo.php';
require_once $APLICACION . '/models/TablaPosiciones.php';
require_once $APLICACION . '/models/TorneoRepositorio.php';
require_once $APLICACION . '/models/ParticipanteRepositorio.php';
require_once $APLICACION . '/models/FixtureRepositorio.php';
require_once $APLICACION . '/models/PosicionRepositorio.php';
require_once $APLICACION . '/models/ResultadoRepositorio.php';
require_once $APLICACION . '/models/CatalogoRepositorio.php';
require_once $APLICACION . '/models/UsuarioRepositorio.php';

echo "===== Eliminacion directa y suizo (fase 3) =====\n";

function equipos($n)
{
    $lista = array();
    for ($i = 1; $i <= $n; $i++) {
        $lista[] = new Participante($i, null, new Equipo($i, 'E' . $i), 'confirmado');
    }
    return $lista;
}
function nombres($cruces)
{
    $texto = array();
    foreach ($cruces as $c) {
        $texto[] = $c['local']->getNombreVisible() . '-' . (($c['visitante'] === null) ? 'libre' : $c['visitante']->getNombreVisible());
    }
    return implode(' ', $texto);
}

# 1. Los modelos
echo "-- La llave\n";
chk(implode(',', Llave::ordenSiembra(8)) === '1,8,4,5,2,7,3,6', 'la siembra de 8: 1-8, 4-5, 2-7, 3-6');
$l = new Llave(equipos(6));
chk(empty($l->generar()) && nombres($l->getCruces()) === 'E1-libre E4-E5 E2-libre E3-E6',
    'con 6, una llave de 8 con dos pases libres, para los dos primeros (' . nombres($l->getCruces()) . ')');
chk(!empty((new Llave(equipos(3)))->generar()) && !empty((new Llave(equipos(33)))->generar()), 'menos de 4 o mas de 32, no');

$problemas = array();
for ($n = 4; $n <= 32; $n++) {
    $l = new Llave(equipos($n));
    $l->generar();
    $tamano = Llave::tamano($n);
    $cruces = $l->getCruces();
    $vistos = array();
    $libres = 0;
    $libres_juntos = false;
    foreach ($cruces as $c) {
        $vistos[] = $c['local']->getIdParticipante();
        if ($c['visitante'] === null) {
            $libres++;
            if ($c['local']->getIdParticipante() > $tamano - $n) { $libres_juntos = true; }
        } else {
            $vistos[] = $c['visitante']->getIdParticipante();
        }
    }
    sort($vistos);
    if (count($cruces) !== $tamano / 2 || $libres !== $tamano - $n || $vistos !== range(1, $n) || $libres_juntos) {
        $problemas[] = $n;
    }
}
chk(empty($problemas), 'de 4 a 32: cada uno una vez, tantos libres como lugares sobran, y para los primeros sembrados'
    . (empty($problemas) ? '' : ' (fallan: ' . implode(', ', $problemas) . ')'));
chk(Llave::nombreRonda(1) === 'Final' && Llave::nombreRonda(2) === 'Semifinal' && Llave::nombreRonda(4) === 'Cuartos de final'
    && Llave::nombreRonda(8) === 'Octavos de final', 'los nombres de las rondas');
chk(Llave::cantidadRondas(4) === 2 && Llave::cantidadRondas(6) === 3 && Llave::cantidadRondas(32) === 5, 'las rondas de una llave');
$e = equipos(4);
chk(nombres(Llave::siguienteRonda(array($e[0], $e[3], $e[1], $e[2]))) === 'E1-E4 E2-E3' && Llave::siguienteRonda(array($e[0])) === array(),
    'la ronda siguiente, en el orden de los cruces');
$libre = new Enfrentamiento(1, 1, $e[0], null);
$jugado = new Enfrentamiento(2, 2, $e[1], $e[2]);
$jugado->asignarResultado(new Resultado(0, 2, $e[2]));
chk(Llave::quienPasa($libre) === $e[0] && Llave::quienPasa($jugado) === $e[2]
    && Llave::quienPasa(new Enfrentamiento(3, 3, $e[0], $e[3])) === null, 'quien pasa: el libre, el ganador, o nadie todavia');

echo "-- El suizo\n";
chk(nombres(Suizo::primeraRonda(equipos(8))) === 'E1-E5 E2-E6 E3-E7 E4-E8', 'primera ronda: la mitad de arriba contra la de abajo');
chk(nombres(Suizo::primeraRonda(equipos(5))) === 'E1-E3 E2-E4 E5-libre', 'con 5, el ultimo queda libre');
chk(Suizo::rondasSugeridas(8) === 3 && Suizo::rondasSugeridas(16) === 4 && Suizo::rondasSugeridas(4) === 3
    && Suizo::rondasMaximas(8) === 7 && Suizo::rondasMaximas(5) === 5, 'las rondas sugeridas y las maximas');
$e = equipos(4);
# 1 y 2 ya jugaron, y 3 y 4 tambien: el 1 va con el 3, aunque el 2 este mas arriba.
$cruces = Suizo::emparejar($e, array(Suizo::clave(1, 2) => true, Suizo::clave(3, 4) => true), array());
chk(nombres($cruces) === 'E1-E3 E2-E4', 'sin repetir: el 1 salta al 2, con quien ya jugo');
# El 1 puede ir con el 3, pero entonces el 2 y el 4 repetirian: hay que volver atras.
$cruces = Suizo::emparejar($e, array(Suizo::clave(1, 2) => true, Suizo::clave(2, 4) => true), array());
chk(nombres($cruces) === 'E1-E4 E2-E3', 'volviendo atras cuando el primer intento deja a dos sin rival posible');
$cruces = Suizo::emparejar(equipos(5), array(), array(5 => true));
chk(nombres($cruces) === 'E1-E2 E3-E5 E4-libre', 'libre: el de mas abajo que todavia no lo estuvo');

$tabla_suizo = new TablaPosiciones(new ConfiguracionTorneo(1), array(1 => $e[0], 2 => $e[1]), true);
$tabla_liga  = new TablaPosiciones(new ConfiguracionTorneo(1), array(1 => $e[0], 2 => $e[1]));
$r = new Ronda(1, 1);
$r->cargarEnfrentamiento(new Enfrentamiento(1, 1, $e[0], null));
$tabla_suizo->sumarRondas(array($r));
$tabla_liga->sumarRondas(array($r));
chk($tabla_suizo->getPosiciones()[1]->getGanados() === 1 && $tabla_liga->getPosiciones()[1]->getGanados() === 0,
    'el libre suma un partido ganado en el suizo, y nada en la liga');

# 2. Contra la base
echo "-- Contra la base\n";
$conexion = conexionDePrueba();
$conexion->query("SET time_zone = '-03:00'");
$creado = array('usuarios' => array(), 'torneos' => array(), 'equipos' => array());
register_shutdown_function(function () use ($conexion, &$creado) {
    foreach ($creado['torneos'] as $id) {
        $id = (int)$id;
        $conexion->query("DELETE FROM ronda WHERE id_torneo = $id");
        $conexion->query("DELETE FROM participante WHERE id_torneo = $id");
        $conexion->query("DELETE FROM torneo WHERE id_torneo = $id");
    }
    foreach ($creado['equipos'] as $id) { $conexion->query('DELETE FROM equipo WHERE id_equipo = ' . (int)$id); }
    foreach ($creado['usuarios'] as $id) {
        $conexion->query('DELETE FROM usuario WHERE id_usuario = ' . (int)$id . " AND correo LIKE 'prueba-%@ejemplo.invalid'");
    }
    echo "  (lo creado por la prueba quedo borrado)\n";
});

$marca = bin2hex(random_bytes(4));
$correo = 'prueba-' . $marca . '@ejemplo.invalid';
$hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$nombre = 'Formatos';
$apellido = 'Prueba';
$st = $conexion->prepare('INSERT INTO usuario (correo, hash_password, nombre, apellido) VALUES (?, ?, ?, ?)');
$st->bind_param('ssss', $correo, $hash, $nombre, $apellido);
$st->execute();
$id_org = (int)$conexion->insert_id;
$creado['usuarios'][] = $id_org;
$conexion->query("INSERT INTO usuario_rol (id_usuario, id_rol) SELECT $id_org, id_rol FROM rol WHERE nombre = 'organizador'");
$org = (new UsuarioRepositorio($conexion))->buscarPorId($id_org);

$catalogos = new CatalogoRepositorio($conexion);
$torneos   = new TorneoRepositorio($conexion);
$inscritos = new ParticipanteRepositorio($conexion);
$fixtures  = new FixtureRepositorio($conexion);
$repo      = new ResultadoRepositorio($conexion);
$disciplina = null;
foreach ($catalogos->disciplinas() as $d) { if ($d->getNombre() === 'Ajedrez') { $disciplina = $d; } }

function torneoDePrueba($nombre, $modulo, $config, $n)
{
    global $catalogos, $torneos, $inscritos, $fixtures, $org, $disciplina, $creado, $marca;
    $t = new Torneo(null, $nombre, $disciplina, $catalogos->tipoPorNombre('Por equipos'),
                    $catalogos->moduloPorNombre($modulo), $org, null, null, 16, null, 'inscripcion');
    $errores = $torneos->crearLiga($t, $config);
    if (!empty($errores)) { echo '  ' . implode(' ', $errores) . "\n"; return null; }
    $creado['torneos'][] = (int)$t->getIdTorneo();
    for ($i = 1; $i <= $n; $i++) {
        $r = $inscritos->agregarEquipo($t, $org, "E$i $marca");
        if ($r['id_equipo_nuevo'] !== null) { $creado['equipos'][] = (int)$r['id_equipo_nuevo']; }
    }
    $torneos->cerrarInscripcion($t, $org);
    $salida = $fixtures->generar($t, $org, false);
    if (!empty($salida['errores'])) { echo '  ' . implode(' ', $salida['errores']) . "\n"; }
    return $torneos->buscarPorId($t->getIdTorneo());
}
function rondasDe($id)
{
    global $inscritos, $fixtures;
    return $fixtures->fechasDe($id, $inscritos->listarDeTorneo($id, true));
}
function jugarRonda($torneo)
{
    global $repo, $org;
    $id = (int)$torneo->getIdTorneo();
    $ronda = ResultadoRepositorio::fechaEnJuego(rondasDe($id));
    foreach ($ronda->getEnfrentamientos() as $p) {
        if ($p->esLibre()) { continue; }
        $local_gana = (int)substr($p->getLocal()->getNombreVisible(), 1) < (int)substr($p->getVisitante()->getNombreVisible(), 1);
        $r = $repo->cargarResultado($id, $p->getIdEnfrentamiento(), $org, $local_gana ? 1 : 0, $local_gana ? 0 : 1, 'marcador');
        if (!empty($r['errores'])) { echo '  ' . implode(' ', $r['errores']) . "\n"; }
    }
    return $repo->cerrarFecha($id, $ronda->getIdRonda(), $org);
}
function nombresRonda($ronda)
{
    $texto = array();
    foreach ($ronda->getEnfrentamientos() as $p) {
        $texto[] = explode(' ', $p->getLocal()->getNombreVisible())[0] . '-'
                 . ($p->esLibre() ? 'libre' : explode(' ', $p->getVisitante()->getNombreVisible())[0]);
    }
    return implode(' ', $texto);
}

# Las reglas que cruzan formato y configuracion.
$t = new Torneo(null, "Copa Mala $marca", $disciplina, $catalogos->tipoPorNombre('Por equipos'),
                $catalogos->moduloPorNombre('Eliminación directa'), $org, null, null, 8, null, 'inscripcion');
chk(!empty($torneos->crearLiga($t, new ConfiguracionTorneo(null))), 'una eliminacion directa que admite empate no se crea');
chk(!empty($torneos->crearLiga($t, new ConfiguracionTorneo(null, 3, 1, 0, 0, 0, 1))), 'ni una de ida y vuelta');

# Eliminacion directa de 6.
$copa = torneoDePrueba("Copa $marca", 'Eliminación directa', new ConfiguracionTorneo(null, 3, 1, 0, 0), 6);
if (!chk($copa !== null && $copa->esEliminacion(), 'una eliminacion directa de 6 se crea')) { fin(); }
$id = (int)$copa->getIdTorneo();
$rondas = rondasDe($id);
chk(count($rondas) === 1 && $rondas[0]->getNombreVisible() === 'Cuartos de final' && nombresRonda($rondas[0]) === 'E1-libre E4-E5 E2-libre E3-E6'
    && (int)$copa->getConfiguracion()->getRondasPrevistas() === 3,
    'la primera ronda: cuartos, con los dos libres de la siembra, y 3 rondas previstas (' . nombresRonda($rondas[0]) . ')');
$cuartos = $rondas[0]->getEnfrentamientos();
$r = $repo->cargarResultado($id, $cuartos[1]->getIdEnfrentamiento(), $org, 1, 1, 'marcador');
chk(!empty($r['errores']), 'un empate en la llave, no');
$r = jugarRonda($copa);
$rondas = rondasDe($id);
chk(empty($r['errores']) && !$r['final'] && $r['siguiente'] === 'Semifinal' && count($rondas) === 2
    && nombresRonda($rondas[1]) === 'E1-E4 E2-E3', 'cerrados los cuartos, se arma la semifinal con los que pasan: E1-E4 y E2-E3');
$r = jugarRonda($copa);
$rondas = rondasDe($id);
chk(empty($r['errores']) && $r['siguiente'] === 'Final' && nombresRonda($rondas[2]) === 'E1-E2', 'despues, la final: E1-E2');
$r = jugarRonda($copa);
$fila = $conexion->query("SELECT estado FROM torneo WHERE id_torneo = $id")->fetch_assoc();
chk(empty($r['errores']) && $r['final'] && strpos($r['campeon'], 'E1 ') === 0 && $fila['estado'] === 'finalizado',
    'cerrada la final, el torneo termina y el campeon es su ganador (' . $r['campeon'] . ')');
$copa = $torneos->buscarPorId($id);
$campeon = ResultadoRepositorio::campeon($copa, $inscritos->listarDeTorneo($id, true), rondasDe($id));
chk($campeon !== null && strpos($campeon->getNombreVisible(), 'E1 ') === 0, 'campeon() da el mismo campeon leyendo la base');

# Suizo de 5 a 3 rondas.
$suizo = torneoDePrueba("Suizo $marca", 'Suizo', new ConfiguracionTorneo(null, 3, 1, 0, 1, 0, 0, 3), 5);
if (!chk($suizo !== null && $suizo->esSuizo() && (int)$suizo->getConfiguracion()->getRondasPrevistas() === 3,
         'un suizo de 5 a 3 rondas se crea')) { fin(); }
$id = (int)$suizo->getIdTorneo();
chk(nombresRonda(rondasDe($id)[0]) === 'E1-E3 E2-E4 E5-libre', 'la primera ronda, con el libre al final');
$r1 = jugarRonda($suizo);
$r2 = jugarRonda($suizo);
$r3 = jugarRonda($suizo);
$rondas = rondasDe($id);
chk(empty($r1['errores']) && $r1['siguiente'] === 'Ronda 2' && $r2['siguiente'] === 'Ronda 3' && $r3['final'] && count($rondas) === 3,
    'tres rondas, cada una armada al cerrar la anterior, y la tercera termina el torneo');
$cruces = array();
$libres = array();
$repetidos = 0;
foreach ($rondas as $ronda) {
    foreach ($ronda->getEnfrentamientos() as $p) {
        if ($p->esLibre()) { $libres[] = $p->getLocal()->getIdParticipante(); continue; }
        $clave = Suizo::clave($p->getLocal()->getIdParticipante(), $p->getVisitante()->getIdParticipante());
        if (isset($cruces[$clave])) { $repetidos++; }
        $cruces[$clave] = true;
    }
}
chk($repetidos === 0 && count(array_unique($libres)) === 3, 'ningun cruce repetido, y tres libres distintos (' . implode(' / ', array_map('nombresRonda', $rondas)) . ')');
$suizo = $torneos->buscarPorId($id);
$todos = $inscritos->listarDeTorneo($id, true);
$recalculada = new TablaPosiciones($suizo->getConfiguracion(), $todos, true);
$recalculada->sumarRondas($rondas);
$guardada = (new PosicionRepositorio($conexion))->tablaDe($id, $todos, $suizo->getConfiguracion());
$a = array(); $b = array();
foreach ($recalculada->getOrdenadas() as $f) { $a[] = $f->getNombreVisible() . ' ' . $f->getGanados() . '-' . $f->getPerdidos(); }
foreach ($guardada as $f) { $b[] = $f->getNombreVisible() . ' ' . $f->getGanados() . '-' . $f->getPerdidos(); }
chk($a === $b && strpos($r3['campeon'], 'E1 ') === 0, 'la tabla guardada (con los libres) es la recalculada, y E1 es el campeon');

fin();
