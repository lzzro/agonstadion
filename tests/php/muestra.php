<?php
# =====================================================================
# Los datos de muestra de la migracion 005 - Stadion (Agon)
# ---------------------------------------------------------------------
#   php tests/php/muestra.php        (con la base de PRUEBA ya migrada)
#
# Comprueba, leyendo la base con los mismos repositorios que usan las
# paginas:
#   - la Liga Valorant no tiene ningun empate: se juega al mejor de 3
#     mapas, asi que cada serie termina 2-0 o 2-1, y la liga no admite
#     empates (admite_empate = 0);
#   - su tabla, recalculada desde los partidos (TablaPosiciones), tiene el
#     orden y las diferencias de mapas de la maqueta de torneo.php, con la
#     columna E en 0 (los puntos cambian donde la maqueta tenia empates);
#   - en las dos ligas en curso, la tabla guardada (tabla_posiciones) es
#     la misma que la recalculada;
#   - el fixture de cada liga en curso es el que arma Fixture.php con sus
#     equipos en el orden de inscripcion (fecha por fecha, cruce por
#     cruce, con la localia);
#   - las cuentas de muestra no entran con ninguna clave.
# =====================================================================

require __DIR__ . '/comun.php';
require_once $APLICACION . '/models/TorneoRepositorio.php';
require_once $APLICACION . '/models/ParticipanteRepositorio.php';
require_once $APLICACION . '/models/FixtureRepositorio.php';
require_once $APLICACION . '/models/PosicionRepositorio.php';
require_once $APLICACION . '/models/TablaPosiciones.php';
require_once $APLICACION . '/models/Fixture.php';
require_once $APLICACION . '/models/UsuarioRepositorio.php';

echo "===== Datos de muestra (migracion 005) =====\n";
$conexion = conexionDePrueba();

# Una tabla como texto: "equipo PJ G E P favor:contra dif pts", fila por fila.
function tablaComoTexto($filas, $config)
{
    $texto = array();
    foreach ($filas as $fila) {
        $texto[] = $fila->getNombreVisible() . ' ' . $fila->getPartidosJugados() . ' ' . $fila->getGanados() . ' '
                 . $fila->getEmpatados() . ' ' . $fila->getPerdidos() . ' ' . $fila->getFavor() . ':' . $fila->getContra()
                 . ' ' . $fila->getDiferencia() . ' ' . $fila->getPuntos($config);
    }
    return $texto;
}

$torneos = new TorneoRepositorio($conexion);
$inscritos = new ParticipanteRepositorio($conexion);
$fixtures = new FixtureRepositorio($conexion);
$posiciones = new PosicionRepositorio($conexion);

$ids = array();
foreach (array('Liga Valorant · Otoño', 'Liga Barrial del Cerro', 'Liga Interna Club Sur') as $nombre) {
    $sentencia = $conexion->prepare('SELECT id_torneo FROM torneo WHERE nombre = ?');
    $sentencia->bind_param('s', $nombre);
    $sentencia->execute();
    $fila = $sentencia->get_result()->fetch_row();
    $ids[$nombre] = ($fila === null) ? null : (int)$fila[0];
}
if (!chk(!in_array(null, $ids, true), 'las tres ligas de muestra estan en la base')) { fin(); }

# La tabla de la Valorant, sin empates. El orden de los 12 y la
# diferencia de mapas de cada uno son los de la maqueta de torneo.php
# (que mostraba las 8 primeras); los puntos cambian donde la maqueta
# tenia empates: Vortex 13 -> 12, Aurora FC 10 -> 9, Ping Masters 8 -> 9,
# Liceo 3 7 -> 9, y abajo Rambla, Atlantida y Sur 7 -> 9.
$esperada = array(
    'Titanes CS 7 6 0 1 13:4 9 18',
    'Nova Esports 7 5 0 2 12:6 6 15',
    'Vortex 7 4 0 3 11:8 3 12',
    'Delta Gaming 7 4 0 3 10:9 1 12',
    'Aurora FC 7 3 0 4 10:10 0 9',
    'Halcones 7 3 0 4 10:11 -1 9',
    'Ping Masters 7 3 0 4 9:11 -2 9',
    'Liceo 3 7 3 0 4 8:11 -3 9',
    'Rambla Esports 7 3 0 4 7:10 -3 9',
    'Atlántida GG 7 3 0 4 6:9 -3 9',
    'Sur Gaming 7 3 0 4 7:11 -4 9',
    'Faro Gaming 7 2 0 5 8:11 -3 6'
);
# De la maqueta: equipo, puesto y diferencia, en el orden de siempre.
$maqueta = array('Titanes CS 9', 'Nova Esports 6', 'Vortex 3', 'Delta Gaming 1', 'Aurora FC 0',
                 'Halcones -1', 'Ping Masters -2', 'Liceo 3 -3');

foreach (array('Liga Valorant · Otoño', 'Liga Barrial del Cerro') as $nombre) {
    $id = $ids[$nombre];
    $torneo = $torneos->buscarPorId($id);
    $config = $torneo->getConfiguracion();
    $todos = $inscritos->listarDeTorneo($id, true);
    $rondas = $fixtures->fechasDe($id, $todos);

    $calculada = new TablaPosiciones($config, $todos);
    $calculada->sumarRondas($rondas);
    $desde_partidos = tablaComoTexto($calculada->getOrdenadas(), $config);
    $guardada = tablaComoTexto($posiciones->tablaDe($id, $todos, $config), $config);

    chk($desde_partidos === $guardada, "$nombre: la tabla guardada es la que sale de los partidos");
    if ($nombre === 'Liga Valorant · Otoño') {
        $orden_y_dif = array();
        foreach (array_slice($calculada->getOrdenadas(), 0, 8) as $fila) {
            $orden_y_dif[] = $fila->getNombreVisible() . ' ' . $fila->getDiferencia();
        }
        chk($orden_y_dif === $maqueta, "$nombre: las 8 primeras, en el orden y con la diferencia de la maqueta");
        chk($desde_partidos === $esperada, "$nombre: las 12 filas, sin empates, con los mapas a favor y en contra");
        $sentencia = $conexion->prepare("SELECT COUNT(*), SUM(re.puntaje_local = re.puntaje_visitante),
                                                SUM(GREATEST(re.puntaje_local, re.puntaje_visitante) = 2
                                                    AND LEAST(re.puntaje_local, re.puntaje_visitante) IN (0, 1)),
                                                SUM(re.id_participante_ganador IS NULL), MAX(c.admite_empate)
                                         FROM resultado re JOIN enfrentamiento en USING (id_enfrentamiento)
                                              JOIN ronda r USING (id_ronda) JOIN configuracion_torneo c ON c.id_torneo = r.id_torneo
                                         WHERE r.id_torneo = ?");
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $cuentas = $sentencia->get_result()->fetch_row();
        chk($cuentas[0] == 42 && $cuentas[1] == 0 && $cuentas[2] == 42 && $cuentas[3] == 0 && $cuentas[4] == 0,
            "$nombre: 42 series jugadas, todas 2-0 o 2-1, con ganador, ningun empate, y la liga no admite empates ("
            . implode('/', $cuentas) . ')');
        if ($desde_partidos !== $esperada) { echo '        ' . implode("\n        ", $desde_partidos) . "\n"; }
    }

    # El fixture: el que arma Fixture.php con los equipos en el orden en
    # que se inscribieron. En la base, cada fecha esta en el orden de su
    # numero de cruce, que es el orden del fixture.
    $en_juego = $inscritos->listarDeTorneo($id);
    $fixture = new Fixture($en_juego, $config->getIdaYVuelta());
    $fixture->generar();
    $esperado = array();
    foreach ($fixture->getFechas() as $cruces) {
        $fila = array();
        foreach ($cruces as $cruce) {
            $fila[] = $cruce['local']->getNombreVisible() . ' - '
                    . (($cruce['visitante'] === null) ? 'libre' : $cruce['visitante']->getNombreVisible());
        }
        $esperado[] = implode(' | ', $fila);
    }
    $guardado = array();
    $sentencia = $conexion->prepare("SELECT r.numero, GROUP_CONCAT(CONCAT(el.nombre, ' - ', IFNULL(ev.nombre, 'libre'))
                                            ORDER BY en.numero SEPARATOR ' | ')
                                     FROM ronda r JOIN enfrentamiento en ON en.id_ronda = r.id_ronda
                                          JOIN participante pl ON pl.id_participante = en.id_participante_local
                                          JOIN equipo el ON el.id_equipo = pl.id_equipo
                                          LEFT JOIN participante pv ON pv.id_participante = en.id_participante_visitante
                                          LEFT JOIN equipo ev ON ev.id_equipo = pv.id_equipo
                                     WHERE r.id_torneo = ? GROUP BY r.numero ORDER BY r.numero");
    $sentencia->bind_param('i', $id);
    $sentencia->execute();
    $resultado = $sentencia->get_result();
    while ($fila = $resultado->fetch_row()) { $guardado[] = $fila[1]; }
    chk($guardado === $esperado, "$nombre: el fixture guardado es el que arma Fixture.php ("
                                 . count($guardado) . ' fechas)');
}

# La fecha 8 de la Valorant: la de calendario.php y torneo.php.
$ocho = valor($conexion, "SELECT GROUP_CONCAT(CONCAT(el.nombre, ' vs ', ev.nombre, ' ', IFNULL(DATE_FORMAT(en.fecha_hora, '%d %H:%i'), 'sin hora'), ' ', en.estado)
                                               ORDER BY en.fecha_hora IS NULL, en.fecha_hora SEPARATOR ' / ')
                           FROM ronda r JOIN enfrentamiento en ON en.id_ronda = r.id_ronda
                                JOIN participante pl ON pl.id_participante = en.id_participante_local JOIN equipo el ON el.id_equipo = pl.id_equipo
                                JOIN participante pv ON pv.id_participante = en.id_participante_visitante JOIN equipo ev ON ev.id_equipo = pv.id_equipo
                           WHERE r.id_torneo = " . $ids['Liga Valorant · Otoño'] . " AND r.numero = 8");
chk($ocho === 'Titanes CS vs Vortex 18 19:00 en_vivo / Nova Esports vs Delta Gaming 18 20:30 programado / '
             . 'Ping Masters vs Atlántida GG 19 20:00 programado / Aurora FC vs Halcones 20 17:00 programado / '
             . 'Rambla Esports vs Faro Gaming 20 19:00 programado / Liceo 3 vs Sur Gaming sin hora programado',
    'la fecha 8 de la Valorant es la de la maqueta del calendario');

# La Liga Interna: inscripcion abierta, 9 de 12, sin fixture.
$interna = $torneos->buscarPorId($ids['Liga Interna Club Sur']);
chk($interna->tieneInscripcionAbierta() && $interna->getMaxParticipantes() === 12
    && count($inscritos->listarDeTorneo($ids['Liga Interna Club Sur'])) === 9
    && $fixtures->estado($ids['Liga Interna Club Sur'])['fechas'] === 0,
    'Liga Interna Club Sur: inscripcion abierta, 9 de 12, sin fixture');

# Las cuentas de muestra: ninguna clave las abre.
$cuentas = new UsuarioRepositorio($conexion);
foreach (array('vortice@ejemplo.invalid', 'clubsur@ejemplo.invalid', 'cerro@ejemplo.invalid') as $correo) {
    $cuenta = $cuentas->buscarPorCorreo($correo);
    $abre = false;
    foreach (array('', 'x', '!cuenta-de-muestra:sin-clave', $cuenta === null ? '' : $cuenta->getHashPassword(),
                   'claveDePrueba123', str_repeat('a', 72)) as $intento) {
        if ($cuenta !== null && $cuenta->verificarClave($intento)) { $abre = true; }
    }
    chk($cuenta !== null && $cuenta->esDeMuestra() && !$abre && substr($cuenta->getHashPassword(), 0, 1) !== '$',
        "$correo: de muestra, y ninguna clave la abre (ni su propio texto)");
}

# Lo que la tabla de torneo.php saca de la configuracion de cada liga:
# cuantos clasifican a playoffs (la franja, nunca un numero escrito en la
# pagina), si hay columna E (admite empate) y como se llaman los tantos.
$como = array();
foreach ($ids as $nombre => $id) {
    $liga = $torneos->buscarPorId($id);
    $cfg  = $liga->getConfiguracion();
    $como[] = $nombre . ': ' . $cfg->getClasificanPlayoffs() . ' ' . ($cfg->admiteEmpate() ? 'con E' : 'sin E') . ' ' . $liga->getDisciplina()->getUnidad();
}
chk(implode(' / ', $como) === 'Liga Valorant · Otoño: 4 sin E mapas / Liga Barrial del Cerro: 0 con E goles / Liga Interna Club Sur: 0 con E goles',
    'la configuracion de la tabla: Valorant clasifica 4 y sin empates (mapas); Barrial e Interna, sin playoffs y con empates (goles)');

# El desempate alfabetico de la tabla ordena como la base
# (utf8mb4_unicode_ci): tildes, mayusculas y las letras que ese cotejo
# trata aparte (æ, ł, ø, þ...). Los nombres van a la base como datos de
# una sentencia preparada, no pegados al SQL.
$nombres = array('Vortex', 'atlántida GG', 'Aurora', 'Ómnibus', 'Ñandú', 'Nova', 'Álamo', 'Liceo 3', 'São Paulo',
                 'Sur Gaming', 'Łódź', 'Lz', 'Zeta', 'Åland', 'Boca', 'Straße', 'Strasse A', 'Ærø', 'Azul', 'Øster', 'Oz',
                 'Œuf', 'Oeste', 'Čačak', 'Czech', 'Šiauliai', 'Þór', 'Tómas', 'İzmir', 'Iz', 'ıb', 'Đa', 'Ða', 'Dz');
$en_php = $nombres;
usort($en_php, array('ConfiguracionTorneo', 'compararNombres'));
$marcas = implode(' UNION ALL ', array_fill(0, count($nombres), 'SELECT ? AS n'));
$sentencia = $conexion->prepare("SELECT n FROM ($marcas) t ORDER BY n COLLATE utf8mb4_unicode_ci, n COLLATE utf8mb4_bin");
$sentencia->bind_param(str_repeat('s', count($nombres)), ...$nombres);
$sentencia->execute();
$en_base = array();
$resultado = $sentencia->get_result();
while ($fila = $resultado->fetch_row()) { $en_base[] = $fila[0]; }
$sentencia->close();
chk($en_php === $en_base, 'el desempate alfabetico ordena ' . count($nombres) . ' nombres igual que la base (utf8mb4_unicode_ci)'
    . ($en_php === $en_base ? '' : ': ' . implode(', ', $en_php) . ' / ' . implode(', ', $en_base)));

$conexion->close();
fin();
