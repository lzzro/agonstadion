<?php
# =====================================================================
# Datos de prueba - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
#   php scripts/datos-de-prueba.php NOMBRE_DE_LA_BASE
#
# Carga en una base de PRUEBA mas de 50 registros por cada parte del
# sistema, para probarlo con volumen (la consigna de la tercera entrega
# lo pide para el entorno de Docker):
#   - 71 cuentas: 60 jugadores, 10 organizadoras y una de administracion
#   - 60 equipos, cada uno con su capitan y dos integrantes con dorsal
#   - 54 torneos: 18 ligas por equipos, 18 eliminaciones directas y 18
#     suizos individuales, en todos los estados: con la inscripcion
#     abierta, en juego, terminados y cancelados
#   - sus participantes, rondas, partidos (con dia, hora y lugar) y
#     resultados, algun walkover y algun partido en vivo
#   - 420 mediciones de rendimiento fisico (siete por jugador)
#   - 55 pedidos del rol de organizador (resueltos y pendientes)
#   - la auditoria del alta de cada cuenta y de cada torneo
#
# Todo pasa por los repositorios de la aplicacion (los mismos que usan
# las pantallas), asi que cumple las mismas reglas: el fixture es el que
# arma el sistema, cada resultado es valido para su torneo y las tablas
# salen de los partidos. Los numeros salen de un generador con semilla
# fija (mt_srand): cada carga da los mismos datos.
#
# Al final, todas las cuentas que crea quedan como cuentas de muestra
# (usuario.de_muestra = 1, sin contrasena): no inician sesion nunca, y el
# sitio las marca "De muestra" igual que a las de la migracion 005. Los
# correos son datos-NN@ejemplo.invalid, un dominio que no existe. Para
# entrar al sitio se crea una cuenta propia desde la pagina de alta y se
# le da el rol administrador con sql/primer_administrador.sql.
#
# RESGUARDOS:
#   - Corre solo desde la consola (nunca por la web).
#   - Pide el nombre de la base como argumento, y tiene que ser el mismo
#     que el de la configuracion: no se corre "sin querer" contra otra.
#   - Si los datos ya estan cargados, avisa y no hace nada.
#   - NUNCA en una base con cuentas reales: es para la base de Docker o
#     la de XAMPP.
# =====================================================================

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

# La aplicacion: apps/ en el repositorio, stadion_app/ en Docker y en la
# copia de publicacion.
$app = is_dir(__DIR__ . '/../apps') ? __DIR__ . '/../apps' : __DIR__ . '/../stadion_app';
require_once $app . '/config/database.php';
require_once $app . '/fechas.php';
require_once $app . '/models/UsuarioRepositorio.php';
require_once $app . '/models/CatalogoRepositorio.php';
require_once $app . '/models/EquipoRepositorio.php';
require_once $app . '/models/TorneoRepositorio.php';
require_once $app . '/models/ParticipanteRepositorio.php';
require_once $app . '/models/PedidoInscripcion.php';
require_once $app . '/models/PedidoInscripcionRepositorio.php';
require_once $app . '/models/PedidoRolRepositorio.php';
require_once $app . '/models/FixtureRepositorio.php';
require_once $app . '/models/ResultadoRepositorio.php';
require_once $app . '/models/MedicionRepositorio.php';
require_once $app . '/models/Auditoria.php';
require_once $app . '/models/AuditoriaRepositorio.php';

$base = isset($argv[1]) ? $argv[1] : '';
if ($base === '' || $base !== $configuracion_bd['base']) {
    echo "Uso: php scripts/datos-de-prueba.php NOMBRE_DE_LA_BASE\n";
    echo "El nombre tiene que ser el de la configuracion (" . $configuracion_bd['base'] . "): es para no cargar datos en otra base sin querer.\n";
    exit(2);
}

$conexion = conectarBD();
if ($conexion === null) {
    echo $error_bd . "\n";
    exit(2);
}

$cuentas = new UsuarioRepositorio($conexion);
if ($cuentas->existeCorreo('datos-01@ejemplo.invalid')) {
    echo "Los datos de prueba ya están cargados en $base: no se carga nada.\n";
    exit(0);
}

mt_srand(2026);
$hoy = date('Y-m-d');
$ip  = '127.0.0.1';
$auditorias = new AuditoriaRepositorio($conexion);
$errores_carga = 0;

# Anota un error de la carga y sigue: al final se cuenta.
function revisar($errores, $que)
{
    global $errores_carga;
    if (!empty($errores)) {
        $errores_carga++;
        echo "  ! $que: " . implode(' ', (array)$errores) . "\n";
        return false;
    }
    return true;
}

# Una fecha y hora a $dias de hoy (negativo: en el pasado).
function diaHora($dias, $hora)
{
    return date('Y-m-d', strtotime(($dias >= 0 ? '+' : '') . $dias . ' days')) . ' ' . $hora;
}

# --- 1. Cuentas -------------------------------------------------------
echo "Cuentas...\n";
$nombres = array('Aquiles', 'Héctor', 'Ayax', 'Paris', 'Odiseo', 'Penélope', 'Telémaco', 'Helena', 'Andrómaca', 'Casandra',
                 'Eneas', 'Patroclo', 'Néstor', 'Diomedes', 'Briseida', 'Hécuba', 'Príamo', 'Agamenón', 'Menelao', 'Clitemnestra',
                 'Electra', 'Orestes', 'Ifigenia', 'Antígona', 'Ismene', 'Edipo', 'Yocasta', 'Creonte', 'Tiresias', 'Medea',
                 'Jasón', 'Orfeo', 'Eurídice', 'Teseo', 'Ariadna', 'Fedra', 'Hipólito', 'Perseo', 'Andrómeda', 'Dánae',
                 'Atalanta', 'Meleagro', 'Belerofonte', 'Hipsípila', 'Calipso', 'Circe', 'Nausícaa', 'Arete', 'Alcínoo', 'Laertes',
                 'Euriclea', 'Filoctetes', 'Neoptólemo', 'Pentesilea', 'Hipólita', 'Deméter', 'Perséfone', 'Céfalo', 'Procris', 'Psique');
$lugares = array('Argos', 'Tebas', 'Delfos', 'Corinto', 'Rodas', 'Esparta', 'Mileto', 'Éfeso', 'Pérgamo', 'Siracusa',
                 'Ítaca', 'Creta', 'Naxos', 'Samos', 'Lesbos');
$jugadores = array();
foreach ($nombres as $i => $nombre) {
    $n = $i + 1;
    $u = new Usuario(null, sprintf('datos-%02d@ejemplo.invalid', $n), null, $nombre, $lugares[$i % count($lugares)]);
    $u->asignarClave('Dp7' . random_int(100000000, 999999999) . random_int(100000000, 999999999));
    # insertar() le da el rol jugador, como a toda cuenta nueva.
    if (revisar($cuentas->insertar($u), "cuenta $n")) {
        $jugadores[] = $u;
    }
}
$organizadoras = array();
$clubes = array(array('Club', 'Delfos'), array('Liga', 'Corinto'), array('Escuela', 'Argos'), array('Centro', 'Esparta'),
                array('Club', 'Rodas'), array('Peña', 'Tebas'), array('Colegio', 'Mileto'), array('Club', 'Éfeso'),
                array('Asociación', 'Pérgamo'), array('Club', 'Siracusa'));
foreach ($clubes as $i => $club) {
    $u = new Usuario(null, sprintf('datos-org-%02d@ejemplo.invalid', $i + 1), null, $club[0], $club[1]);
    $u->asignarClave('Dp7' . random_int(100000000, 999999999) . random_int(100000000, 999999999));
    if (revisar($cuentas->insertar($u), 'organizadora ' . ($i + 1))) {
        $cuentas->asignarRolPorNombre($u, 'organizador');
        $organizadoras[] = $u;
    }
}
$admin = new Usuario(null, 'datos-admin@ejemplo.invalid', null, 'Administración', 'de Prueba');
$admin->asignarClave('Dp7' . random_int(100000000, 999999999) . random_int(100000000, 999999999));
revisar($cuentas->insertar($admin), 'la cuenta de administracion');
$cuentas->asignarRolPorNombre($admin, 'administrador');
$cuentas->cargarRoles($admin);
foreach (array_merge($jugadores, $organizadoras, array($admin)) as $u) {
    $auditorias->registrar(new Auditoria(null, $u, 'usuario', 'alta', $u->getIdUsuario(), 'Carga de datos de prueba', $ip));
}

# --- 2. Equipos con sus integrantes -----------------------------------
echo "Equipos...\n";
$colectivos = array('Argonautas', 'Mirmidones', 'Hoplitas', 'Centauros', 'Tritones', 'Amazonas',
                    'Cíclopes', 'Sirenas', 'Grifos', 'Pegasos', 'Minotauros', 'Gorgonas');
$sedes = array('Argos', 'Tebas', 'Delfos', 'Corinto', 'Rodas');
$equipos_repo = new EquipoRepositorio($conexion);
$equipos = array();   # cada uno: array('equipo' => Equipo, 'capitan' => Usuario)
$cantidad_jugadores = count($jugadores);
for ($i = 0; $i < 60 && $cantidad_jugadores >= 60; $i++) {
    $capitan = $jugadores[$i];
    $equipo = new Equipo(null, $colectivos[$i % 12] . ' de ' . $sedes[intdiv($i, 12)], null, $capitan);
    if (!revisar($equipos_repo->crearConCapitan($equipo), 'equipo ' . ($i + 1))) {
        continue;
    }
    $id_equipo = (int)$equipo->getIdEquipo();
    $equipos_repo->cambiarDorsal($id_equipo, $capitan, $capitan->getIdUsuario(), 1 + ($i % 9));
    foreach (array(20, 40) as $k => $salto) {
        $otro = $jugadores[($i + $salto) % 60];
        $r = $equipos_repo->sumarIntegrante($id_equipo, $capitan, $otro->getCorreo(), 10 + $k * 10 + ($i % 9));
        revisar($r['errores'], 'integrante del equipo ' . ($i + 1));
    }
    $equipos[] = array('equipo' => $equipo, 'capitan' => $capitan);
}

# --- 3. Torneos --------------------------------------------------------
echo "Torneos...\n";
$catalogos = new CatalogoRepositorio($conexion);
$disc = array();
foreach ($catalogos->disciplinas() as $d) {
    $disc[$d->getNombre()] = $d;
}
$por_equipos = $catalogos->tipoPorNombre('Por equipos');
$individual  = $catalogos->tipoPorNombre('Individual');
$modulos     = $catalogos->modulos();
$torneos     = new TorneoRepositorio($conexion);
$inscritos   = new ParticipanteRepositorio($conexion);
$pedidos     = new PedidoInscripcionRepositorio($conexion);
$fixtures    = new FixtureRepositorio($conexion);
$resultados  = new ResultadoRepositorio($conexion);

# El estado de cada torneo segun su orden dentro del formato: 4 con la
# inscripcion abierta, 6 en juego, 7 terminados y 1 cancelado.
function estadoDe($k)
{
    if ($k < 4)  { return 'inscripcion'; }
    if ($k < 10) { return 'en_curso'; }
    if ($k < 17) { return 'finalizado'; }
    return 'cancelado';
}

# Un marcador valido para la configuracion: una serie al mejor de N, un
# partido que puede terminar empatado, o uno que siempre tiene ganador.
function marcador(ConfiguracionTorneo $config, $disciplina)
{
    if ($config->esSerie()) {
        $gana = $config->ganaSerieCon();
        $pierde = mt_rand(0, $gana - 1);
        return (mt_rand(0, 1) === 0) ? array($gana, $pierde) : array($pierde, $gana);
    }
    $tope = ($disciplina === 'Ajedrez') ? 1 : 5;
    do {
        $a = mt_rand(0, $tope);
        $b = mt_rand(0, $tope);
    } while ($a === $b && !$config->admiteEmpate());
    return array($a, $b);
}

# Juega una ronda: programa sus partidos (dia, hora y lugar) y, si
# $jugar, carga todos los resultados (o los primeros $hasta). Devuelve
# cuantos quedaron jugados.
function jugarRonda($torneo, $organizadora, $ronda, $dias, $jugar, $hasta = null)
{
    global $resultados;
    $id = (int)$torneo->getIdTorneo();
    $horas = array('18:00', '19:30', '21:00');
    $jugados = 0;
    $n = 0;
    foreach ($ronda->getEnfrentamientos() as $partido) {
        if ($partido->esLibre()) {
            continue;
        }
        $lugar = ($torneo->getDisciplina()->getNombre() === 'Esports') ? 'Servidor ' . (1 + $n % 2) : 'Cancha ' . (1 + $n % 3);
        revisar($resultados->programar($id, $partido->getIdEnfrentamiento(), $organizadora,
                diaHora($dias, $horas[$n % 3]), $lugar)['errores'], 'programar un partido');
        $n++;
        if (!$jugar || ($hasta !== null && $jugados >= $hasta)) {
            continue;
        }
        $config = $torneo->getConfiguracion();
        if (mt_rand(1, 25) === 1) {
            # Un walkover de vez en cuando.
            $r = $resultados->cargarResultado($id, $partido->getIdEnfrentamiento(), $organizadora, 0, 0,
                                              (mt_rand(0, 1) === 0) ? 'wo-local' : 'wo-visitante');
        } else {
            $m = marcador($config, $torneo->getDisciplina()->getNombre());
            $r = $resultados->cargarResultado($id, $partido->getIdEnfrentamiento(), $organizadora, $m[0], $m[1], 'marcador');
        }
        if (revisar($r['errores'], 'resultado de ' . $torneo->getNombre())) {
            $jugados++;
        }
    }
    return $jugados;
}

$formatos = array(
    'liga'        => array('modulo' => 'Liga', 'tipo' => $por_equipos, 'nombre' => 'Liga'),
    'eliminacion' => array('modulo' => 'Eliminación directa', 'tipo' => $individual, 'nombre' => 'Copa'),
    'suizo'       => array('modulo' => 'Suizo', 'tipo' => $individual, 'nombre' => 'Abierto')
);
$creados = 0;
$siguiente_equipo = 0;
$siguiente_jugador = 0;
foreach ($formatos as $clave => $f) {
    for ($k = 0; $k < 18; $k++) {
        $organizadora = $organizadoras[($k + strlen($clave)) % count($organizadoras)];
        $estado = estadoDe($k);
        $cupo = ($clave === 'suizo') ? 5 + ($k % 4) : 4 + ($k % 5);
        if ($clave === 'liga') {
            $disciplina = $disc[($k % 3 === 0) ? 'Esports' : (($k % 3 === 1) ? 'Fútbol 5' : 'Fútbol')];
            $config = ($disciplina->getNombre() === 'Esports')
                    ? new ConfiguracionTorneo(null, 3, 1, 0, 0, ($k % 2 === 0) ? 2 : 0, 0, null, null, 'diferencia', 3)
                    : new ConfiguracionTorneo(null, 3, 1, 0, 1, ($k % 2 === 0) ? 2 : 0, ($k === 5) ? 1 : 0, null, null, 'diferencia');
        } elseif ($clave === 'eliminacion') {
            $disciplina = $disc[($k % 2 === 0) ? 'Tenis de mesa' : 'Cartas'];
            $config = ($k % 3 === 0) ? new ConfiguracionTorneo(null, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 5)
                                     : new ConfiguracionTorneo(null, 3, 1, 0, 0);
        } else {
            $disciplina = $disc['Ajedrez'];
            # Ajedrez: 2 puntos la victoria y 1 el empate (las tablas).
            $config = new ConfiguracionTorneo(null, 2, 1, 0, 1, 0, 0, 3, null, 'favor');
        }
        $nombre = $f['nombre'] . ' de ' . $lugares[$k % count($lugares)] . ' · ' . sprintf('%02d', $k + 1);
        $torneo = new Torneo(null, $nombre, $disciplina, $f['tipo'], $modulos[$f['modulo']], $organizadora,
                             null, null, $cupo, 'Sede ' . $sedes[$k % count($sedes)], 'inscripcion');
        if (!revisar($torneos->crearLiga($torneo, $config), "crear $nombre")) {
            continue;
        }
        $creados++;
        $id = (int)$torneo->getIdTorneo();
        $auditorias->registrar(new Auditoria(null, $organizadora, 'torneo', 'alta', $id, mb_substr('Carga de datos de prueba · ' . $nombre, 0, 255), $ip));

        # La inscripcion: los equipos piden lugar y la organizadora acepta;
        # los jugadores se inscriben solos. Con la inscripcion abierta,
        # queda a medio llenar.
        $cuantos = ($estado === 'inscripcion') ? max(1, $cupo - 2) : $cupo;
        for ($j = 0; $j < $cuantos; $j++) {
            if ($clave === 'liga') {
                $e = $equipos[$siguiente_equipo % count($equipos)];
                $siguiente_equipo++;
                $pedido = new PedidoInscripcion(null, $torneos->buscarPorId($id), $e['equipo'], $e['capitan']);
                if (revisar($pedidos->crear($pedido), "pedido en $nombre")) {
                    revisar($pedidos->resolver($pedidos->buscarPorId($pedido->getIdPedidoInscripcion()), $organizadora, true), "aceptar en $nombre");
                }
            } else {
                $jugador = $jugadores[$siguiente_jugador % count($jugadores)];
                $siguiente_jugador++;
                revisar($inscritos->inscribirJugador($torneos->buscarPorId($id), $jugador)['errores'], "inscripcion en $nombre");
            }
        }
        if ($estado === 'inscripcion') {
            continue;
        }
        revisar($torneos->cerrarInscripcion($torneos->buscarPorId($id), $organizadora)['errores'], "cerrar $nombre");
        revisar($fixtures->generar($torneos->buscarPorId($id), $organizadora, false)['errores'], "fixture de $nombre");

        # Las rondas: un terminado se jugo hace unas semanas, una ronda por
        # semana; uno en juego viene jugando y tiene la ronda en curso con
        # la mitad de los partidos cargados (y uno en vivo, a veces).
        $torneo = $torneos->buscarPorId($id);
        # En juego (y el que despues se cancela): una ronda cerrada hace
        # una semana y la de hoy a medias (una llave de 4 no llega a
        # terminar).
        $inicio = ($estado === 'finalizado') ? -70 - 3 * $k : -7;
        $semana = 0;
        $vueltas = 0;
        while ($vueltas < 20) {
            $vueltas++;
            $todos  = $inscritos->listarDeTorneo($id, true);
            $rondas = $fixtures->fechasDe($id, $todos);
            $abierta = null;
            foreach ($rondas as $ronda) {
                if (!$ronda->estaCerrada()) { $abierta = $ronda; break; }
            }
            if ($abierta === null) {
                break;
            }
            $dias = $inicio + 7 * $semana;
            if ($estado !== 'finalizado' && $dias >= 0) {
                # La ronda de esta semana: la mitad jugada, y las que
                # siguen, programadas.
                $mitad = intdiv(count($abierta->getEnfrentamientos()), 2);
                jugarRonda($torneo, $organizadora, $abierta, 0, true, $mitad);
                if ($k % 2 === 0) {
                    foreach ($fixtures->fechasDe($id, $inscritos->listarDeTorneo($id, true)) as $r) {
                        if ((int)$r->getIdRonda() !== (int)$abierta->getIdRonda()) { continue; }
                        foreach ($r->getEnfrentamientos() as $p) {
                            if (!$p->esLibre() && !$p->estaJugado()) {
                                $resultados->cambiarEnVivo($id, $p->getIdEnfrentamiento(), $organizadora, true);
                                break;
                            }
                        }
                    }
                }
                $mas = 1;
                foreach ($rondas as $r) {
                    if ($r->getNumero() > $abierta->getNumero()) {
                        jugarRonda($torneo, $organizadora, $r, 7 * $mas, false);
                        $mas++;
                    }
                }
                break;
            }
            jugarRonda($torneo, $organizadora, $abierta, $dias, true);
            revisar($resultados->cerrarFecha($id, $abierta->getIdRonda(), $organizadora)['errores'], "cerrar una ronda de $nombre");
            $semana++;
        }
        # Las fechas del torneo: las de sus partidos (el cierre de la
        # ultima ronda pone la de hoy).
        $conexion->query("UPDATE torneo t SET
              fecha_inicio = (SELECT DATE(MIN(e.fecha_hora)) FROM enfrentamiento e JOIN ronda r USING (id_ronda) WHERE r.id_torneo = t.id_torneo),
              fecha_fin = CASE WHEN t.estado = 'finalizado'
                               THEN (SELECT DATE(MAX(e.fecha_hora)) FROM enfrentamiento e JOIN ronda r USING (id_ronda) WHERE r.id_torneo = t.id_torneo)
                               ELSE t.fecha_fin END
            WHERE t.id_torneo = $id");
        if ($estado === 'cancelado') {
            revisar($torneos->cancelar($torneos->buscarPorId($id), $organizadora)['errores'], "cancelar $nombre");
        }
    }
}

# --- 4. Mediciones -----------------------------------------------------
echo "Mediciones...\n";
$mediciones = new MedicionRepositorio($conexion);
foreach ($jugadores as $i => $jugador) {
    $masa = 55 + mt_rand(0, 30);
    $base_regla = 10 + mt_rand(0, 80) / 10;
    $base_sprint = 2.2 + mt_rand(0, 60) / 100;
    $base_salto = 0.38 + mt_rand(0, 14) / 100;
    foreach (array(-60, -30, -5) as $n => $dias) {
        revisar($mediciones->insertar(new Medicion(null, $jugador, 'reaccion', round(max(0.5, $base_regla - $n * 0.8 + mt_rand(-10, 10) / 10), 1),
                null, null, date('Y-m-d', strtotime("$dias days"))), $hoy), 'medicion de reaccion');
    }
    foreach (array(-45, -10) as $n => $dias) {
        revisar($mediciones->insertar(new Medicion(null, $jugador, 'sprint', null, round($base_sprint - $n * 0.05, 2), $masa,
                date('Y-m-d', strtotime("$dias days"))), $hoy), 'medicion de sprint');
        revisar($mediciones->insertar(new Medicion(null, $jugador, 'salto', null, round($base_salto + $n * 0.02, 2), $masa,
                date('Y-m-d', strtotime(($dias + 1) . ' days'))), $hoy), 'medicion de salto');
    }
}

# --- 5. Pedidos del rol de organizador ---------------------------------
echo "Pedidos de rol...\n";
$roles = new PedidoRolRepositorio($conexion);
# 55 pedidos (la consigna pide 50 o mas por parte del sistema): 49
# resueltos, uno de cada tres aprobado, y 6 pendientes.
for ($i = 5; $i < 60 && $i < count($jugadores); $i++) {
    revisar($roles->crear($jugadores[$i], 'organizador'), 'pedido de rol');
    if ($i < 54) {
        $pedido = $roles->ultimoDe($jugadores[$i]->getIdUsuario(), 'organizador');
        revisar($roles->resolver($pedido, $admin, $i % 3 === 0), 'resolver un pedido de rol');
    }
}

# --- 6. Cuentas de muestra ----------------------------------------------
# Todas las cuentas de la carga quedan de muestra y sin contrasena: no
# inician sesion nunca (ver loginController y la migracion 005).
$conexion->query("UPDATE usuario SET de_muestra = 1, hash_password = 'cuenta de prueba: no inicia sesion'
                  WHERE correo LIKE 'datos-%@ejemplo.invalid'");

# --- 7. Lo que quedo cargado --------------------------------------------
echo "\nLo que quedo cargado:\n";
$cuentas_sql = "SELECT id_usuario FROM usuario WHERE correo LIKE 'datos-%@ejemplo.invalid'";
$torneos_sql = "SELECT id_torneo FROM torneo WHERE id_usuario_organizador IN ($cuentas_sql)";
$conteos = array(
    'cuentas'                => "SELECT COUNT(*) FROM usuario WHERE correo LIKE 'datos-%@ejemplo.invalid'",
    'equipos'                => "SELECT COUNT(*) FROM equipo WHERE id_usuario_capitan IN ($cuentas_sql)",
    'integrantes'            => "SELECT COUNT(*) FROM integrante_equipo WHERE id_usuario IN ($cuentas_sql)",
    'torneos'                => "SELECT COUNT(*) FROM torneo WHERE id_torneo IN ($torneos_sql)",
    'participantes'          => "SELECT COUNT(*) FROM participante WHERE id_torneo IN ($torneos_sql)",
    'pedidos de inscripcion' => "SELECT COUNT(*) FROM pedido_inscripcion WHERE id_torneo IN ($torneos_sql)",
    'rondas'                 => "SELECT COUNT(*) FROM ronda WHERE id_torneo IN ($torneos_sql)",
    'partidos'               => "SELECT COUNT(*) FROM enfrentamiento e JOIN ronda r USING (id_ronda) WHERE r.id_torneo IN ($torneos_sql)",
    'resultados'             => "SELECT COUNT(*) FROM resultado re JOIN enfrentamiento e USING (id_enfrentamiento) JOIN ronda r USING (id_ronda) WHERE r.id_torneo IN ($torneos_sql)",
    'filas de las tablas'    => "SELECT COUNT(*) FROM tabla_posiciones tp JOIN participante p USING (id_participante) WHERE p.id_torneo IN ($torneos_sql)",
    'mediciones'             => "SELECT COUNT(*) FROM medicion WHERE id_usuario IN ($cuentas_sql)",
    'pedidos de rol'         => "SELECT COUNT(*) FROM pedido_rol WHERE id_usuario IN ($cuentas_sql)",
    'auditoria'              => "SELECT COUNT(*) FROM auditoria WHERE id_usuario IN ($cuentas_sql)"
);
foreach ($conteos as $que => $sql) {
    $fila = $conexion->query($sql)->fetch_row();
    echo '  ' . str_pad($que, 24) . $fila[0] . "\n";
}
$conexion->close();
echo ($errores_carga === 0) ? "\nListo: los datos de prueba quedaron cargados.\n" : "\n$errores_carga pasos no se pudieron hacer (ver arriba).\n";
exit(($errores_carga === 0) ? 0 : 1);
