<?php
# =====================================================================
# Rendimiento fisico (RF-29) - Stadion (Agon)
# ---------------------------------------------------------------------
#   php tests/php/rendimiento.php       (con la base de PRUEBA migrada,
#                                        hasta la 007)
#
# Dos partes:
#   1. Medicion, sin la base: los tres modelos de Fisica con los valores
#      de la segunda entrega de Fisica (128 y 212 ms; 3,78 m/s^2 y
#      257,1 N; 583,4 y 381,4 W), la marca mejor de cada prueba, el
#      formato con coma decimal y los rangos de validar(), que son los de
#      la restriccion ck_med_datos.
#   2. MedicionRepositorio contra la base: guardar (y no una fecha
#      futura ni un dato fuera de rango), listar de la mas vieja a la mas
#      nueva, borrar solo una propia, y la comparacion (la mejor marca de
#      cada cuenta activa, ordenada, sin las cuentas dadas de baja). Y el
#      grafico de la evolucion (apps/rendimiento.php).
#
# Crea sus propias cuentas (prueba-...@ejemplo.invalid) y al final las
# BORRA, pase lo que pase: sus mediciones se van con ellas (ON DELETE
# CASCADE). No toca nada mas.
# =====================================================================

require __DIR__ . '/comun.php';
require_once $APLICACION . '/models/Medicion.php';
require_once $APLICACION . '/models/MedicionRepositorio.php';
require_once $APLICACION . '/rendimiento.php';

echo "===== Rendimiento fisico (RF-29) =====\n";

# --- 1. Los modelos ----------------------------------------------------
echo "-- Modelos\n";
$nadie = new Usuario(1, null, null, 'Hipatia', 'Alejandría');
function medicion($prueba, $d, $t, $m, $fecha = '2026-10-01')
{
    global $nadie;
    return new Medicion(null, $nadie, $prueba, $d, $t, $m, $fecha);
}
function cerca($a, $b, $tolerancia)
{
    return $a !== null && abs($a - $b) <= $tolerancia;
}

$r8  = medicion('reaccion', 8, null, null);
$r22 = medicion('reaccion', 22, null, null);
chk(cerca($r8->tiempoReaccionMs(), 127.8, 0.05) && Medicion::formatear('reaccion', $r8->indicador()) === '128',
    'reaccion, 8 cm: t = raiz(2d/g) = 128 ms');
chk(Medicion::formatear('reaccion', $r22->indicador()) === '212', 'reaccion, 22 cm: 212 ms');

$s = medicion('sprint', null, 2.30, 68);
chk(cerca($s->velocidadMedia(), 4.348, 0.001) && cerca($s->aceleracion(), 3.781, 0.001) && cerca($s->fuerza(), 257.1, 0.05),
    'sprint, 10 m en 2,30 s con 68 kg: v = 4,35 m/s, a = 2d/t^2 = 3,78 m/s^2, F = m a = 257,1 N');
chk(Medicion::formatear('sprint', $s->indicador()) === '3,78', 'el indicador del sprint, con coma y dos decimales: 3,78');

$j1 = medicion('salto', null, 0.45, 60);
$j2 = medicion('salto', null, 0.38, 55);
chk(cerca($j1->altura(), 0.2481, 0.0001) && cerca($j1->energiaPotencial(), 145.86, 0.01) && cerca($j1->potencia(), 583.4, 0.05),
    'salto, 0,45 s de vuelo con 60 kg: h = g t^2/8 = 0,248 m, Ep = 145,9 J, P = Ep/0,25 = 583,4 W');
chk(cerca($j2->potencia(), 381.4, 0.05) && Medicion::formatear('salto', $j2->indicador()) === '381', 'salto, 0,38 s con 55 kg: 381 W');
chk(Medicion::formatear('salto', 1234.4) === '1.234' && Medicion::formatear('reaccion', null) === '—', 'miles con punto; sin valor, una raya');

chk($r8->velocidadMedia() === null && $r8->potencia() === null && $s->tiempoReaccionMs() === null && $s->altura() === null,
    'cada medicion calcula solo los indicadores de su prueba');
chk(Medicion::esMejor('reaccion', 120, 128) && !Medicion::esMejor('reaccion', 130, 128)
    && Medicion::esMejor('sprint', 3.9, 3.78) && Medicion::esMejor('salto', 600, 583),
    'la mejor marca: el tiempo menor en la reaccion, el valor mayor en las otras');
$p = Medicion::pruebas();
chk(implode(',', array_keys($p)) === 'reaccion,sprint,salto' && $p['reaccion']['corta'] === 'ms' && $p['salto']['unidad'] === 'watts',
    'tres pruebas, con su indicador y su unidad');

chk(empty($r8->validar()) && empty($s->validar()) && empty($j1->validar()), 'las tres mediciones de arriba son validas');
chk(empty(medicion('reaccion', 0.5, null, null)->validar()) && empty(medicion('reaccion', 30, null, null)->validar())
    && !empty(medicion('reaccion', 0.4, null, null)->validar()) && !empty(medicion('reaccion', 30.1, null, null)->validar()),
    'la regla: de 0,5 a 30 cm, bordes incluidos');
chk(empty(medicion('sprint', null, 1, 20)->validar()) && empty(medicion('sprint', null, 6, 200)->validar())
    && !empty(medicion('sprint', null, 0.99, 68)->validar()) && !empty(medicion('sprint', null, 6.01, 68)->validar()),
    'el sprint: de 1 a 6 s');
chk(!empty(medicion('salto', null, 0.09, 60)->validar()) && !empty(medicion('salto', null, 1.01, 60)->validar())
    && !empty(medicion('salto', null, 0.45, 19.9)->validar()) && !empty(medicion('salto', null, 0.45, 200.1)->validar()),
    'el salto: vuelo de 0,1 a 1 s y masa de 20 a 200 kg');
chk(!empty(medicion('sprint', null, 2.3, null)->validar()) && !empty(medicion('reaccion', null, null, null)->validar()),
    'sin el dato que pide la prueba, no');
chk(!empty(medicion('natacion', 10, null, null)->validar()), 'una prueba que no es de las tres, no');
chk(!empty(medicion('reaccion', 8, null, null, '2026-02-30')->validar()) && !empty(medicion('reaccion', 8, null, null, '1/10/2026')->validar()),
    'una fecha que no existe o mal escrita, no');
$errores = medicion('reaccion', 31, null, null)->validar();
chk(count($errores) === 1 && $errores[0] === 'La regla mide 30 cm: la distancia va de 0,5 a 30.', 'el aviso, en presente: "' . $errores[0] . '"');

# --- 2. Contra la base -------------------------------------------------
echo "-- Contra la base\n";
$conexion = conexionDePrueba();
$conexion->query("SET time_zone = '-03:00'");
date_default_timezone_set('America/Montevideo');

if (!chk((int)valor($conexion, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = 'medicion'") === 1, 'la base tiene la 007 (la tabla medicion)')) { fin(); }

$creadas = array();
register_shutdown_function(function () use ($conexion, &$creadas) {
    foreach ($creadas as $id) {
        $conexion->query('DELETE FROM usuario WHERE id_usuario = ' . (int)$id . " AND correo LIKE 'prueba-%@ejemplo.invalid'");
    }
    $quedan = empty($creadas) ? 0 : (int)valor($conexion, 'SELECT COUNT(*) FROM medicion WHERE id_usuario IN ('
                                                            . implode(',', array_map('intval', $creadas)) . ')');
    echo ($quedan === 0) ? "  (lo creado por la prueba quedo borrado)\n" : "  ATENCION: quedaron $quedan mediciones de la prueba\n";
});
function cuenta($conexion, &$creadas, $nombre, $apellido, $activa = 1)
{
    $correo = 'prueba-' . bin2hex(random_bytes(4)) . '@ejemplo.invalid';
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $sentencia = $conexion->prepare('INSERT INTO usuario (correo, hash_password, nombre, apellido, activo) VALUES (?, ?, ?, ?, ?)');
    $sentencia->bind_param('ssssi', $correo, $hash, $nombre, $apellido, $activa);
    $sentencia->execute();
    $id = (int)$conexion->insert_id;
    $creadas[] = $id;
    return new Usuario($id, $correo, null, $nombre, $apellido);
}
$a = cuenta($conexion, $creadas, 'Hipatia', 'Alejandría');
$b = cuenta($conexion, $creadas, 'Tales', 'Mileto');
$c = cuenta($conexion, $creadas, 'Safo', 'Lesbos');
$baja = cuenta($conexion, $creadas, 'Zenón', 'Elea', 0);
$repo = new MedicionRepositorio($conexion);
$hoy = date('Y-m-d');

$m1 = new Medicion(null, $a, 'reaccion', 14.2, null, null, '2026-09-01');
chk(empty($repo->insertar($m1, $hoy)) && $m1->getIdMedicion() > 0, 'una medicion valida se guarda, y queda con su numero');
chk(valor($conexion, 'SELECT CONCAT(prueba, "|", distancia_cm, "|", IFNULL(tiempo_s, "NULL"), "|", fecha) FROM medicion WHERE id_medicion = '
                     . (int)$m1->getIdMedicion()) === 'reaccion|14.2|NULL|2026-09-01', 'en la base, solo los datos crudos y la fecha');
$futura = new Medicion(null, $a, 'reaccion', 10, null, null, date('Y-m-d', strtotime('+1 day')));
$e = $repo->insertar($futura, $hoy);
chk($e === array('La fecha de la medición va hasta hoy.') && $futura->getIdMedicion() === null, 'una fecha futura, no: "' . implode(' ', $e) . '"');
chk(!empty($repo->insertar(new Medicion(null, $a, 'sprint', null, 0.5, 68, '2026-09-02'), $hoy)), 'un dato fuera de rango, no');
chk(empty($repo->insertar(new Medicion(null, $a, 'reaccion', 10, null, null, $hoy), $hoy)), 'hoy, si');

foreach (array(array('reaccion', 8.0, null, null, '2026-09-15'), array('sprint', null, 2.45, 68, '2026-09-02'),
               array('sprint', null, 2.30, 67.5, '2026-10-07'), array('reaccion', 12.0, null, null, '2026-09-08')) as $f) {
    $repo->insertar(new Medicion(null, $a, $f[0], $f[1], $f[2], $f[3], $f[4]), $hoy);
}
$lista = $repo->listarDeUsuario($a);
$fechas = array();
foreach ($lista as $m) { $fechas[] = $m->getFecha(); }
$ordenadas = $fechas;
sort($ordenadas);
chk(count($lista) === 6 && $fechas === $ordenadas, 'las de la cuenta, de la mas vieja a la mas nueva (' . count($lista) . ')');
chk($lista[0]->getUsuario() === $a && $lista[0]->getFechaCarga() !== null, 'cada una con su cuenta y su fecha de carga');
chk($repo->listarDeUsuario($b) === array(), 'otra cuenta, sin mediciones: lista vacia');

# La comparacion: la mejor de cada cuenta activa.
$repo->insertar(new Medicion(null, $b, 'reaccion', 7.5, null, null, '2026-10-01'), $hoy);
$repo->insertar(new Medicion(null, $b, 'reaccion', 20, null, null, '2026-10-02'), $hoy);
$repo->insertar(new Medicion(null, $c, 'reaccion', 9.0, null, null, '2026-10-01'), $hoy);
$repo->insertar(new Medicion(null, $b, 'sprint', null, 2.6, 80, '2026-10-01'), $hoy);
$conexion->query('INSERT INTO medicion (id_usuario, prueba, distancia_cm, fecha) VALUES (' . (int)$baja->getIdUsuario() . ", 'reaccion', 1, '2026-10-01')");
$propias = array($a->getIdUsuario(), $b->getIdUsuario(), $c->getIdUsuario(), $baja->getIdUsuario());
function deLaPrueba($lista, $propias)
{
    $salida = array();
    foreach ($lista as $fila) {
        if (in_array($fila['usuario']->getIdUsuario(), $propias)) {
            $salida[] = nombreCorto($fila['usuario']) . ' ' . Medicion::formatear($fila['mejor']->getPrueba(), $fila['mejor']->indicador())
                      . ' (' . $fila['cantidad'] . ')';
        }
    }
    return implode(', ', $salida);
}
$reaccion = deLaPrueba($repo->comparacion('reaccion'), $propias);
chk($reaccion === 'T. Mileto 124 (2), H. Alejandría 128 (4), S. Lesbos 136 (1)',
    'reaccion: la mejor de cada una, del tiempo menor al mayor, sin la cuenta dada de baja (' . $reaccion . ')');
$sprint = deLaPrueba($repo->comparacion('sprint'), $propias);
chk($sprint === 'H. Alejandría 3,78 (2), T. Mileto 2,96 (1)', 'sprint: de la aceleracion mayor a la menor (' . $sprint . ')');
chk(deLaPrueba($repo->comparacion('salto'), $propias) === '', 'salto: sin mediciones de la prueba, nadie');

# Borrar: solo una propia.
$id_ajena = (int)valor($conexion, 'SELECT MIN(id_medicion) FROM medicion WHERE id_usuario = ' . (int)$b->getIdUsuario());
chk($repo->borrar($id_ajena, $a->getIdUsuario()) === false
    && (int)valor($conexion, 'SELECT COUNT(*) FROM medicion WHERE id_medicion = ' . $id_ajena) === 1, 'una medicion de otra cuenta no se borra');
chk($repo->borrar($m1->getIdMedicion(), $a->getIdUsuario()) === true && count($repo->listarDeUsuario($a)) === 5, 'una propia, si');
chk($repo->borrar($m1->getIdMedicion(), $a->getIdUsuario()) === false, 'y una ya borrada, no otra vez');

# El grafico de la evolucion.
$reacciones = array();
foreach ($repo->listarDeUsuario($a) as $m) { if ($m->getPrueba() === 'reaccion') { $reacciones[] = $m; } }
$svg = graficoEvolucion($reacciones, 'reaccion');
preg_match('/points="([^"]*)"/', $svg, $puntos);
chk(count($reacciones) === 3 && count(explode(' ', $puntos[1])) === 3 && substr_count($svg, '<circle class="punto"') === 3,
    'el grafico: una linea y un punto por medicion');
chk(strpos($svg, 'role="img"') !== false
    && strpos($svg, 'aria-label="Tiempo de reacción en 3 mediciones, del 8 de septiembre de 2026 al ') !== false
    && strpos($svg, ': de 156 a 143 milisegundos.') !== false, 'y en aria-label lo que muestra, con la primera y la ultima');
chk(strpos($svg, '<title>08/09: 156 ms</title>') !== false && strpos($svg, '>08/09</text>') !== false,
    'cada punto con su valor en un <title>, y las fechas escritas abajo');
preg_match_all('/<text class="rotulo" x="44" y="([\d.]+)"/', $svg, $rejas);
chk(count($rejas[1]) >= 4, 'al menos cuatro lineas de reja, cada una con su numero');
preg_match_all('/<circle class="punto" cx="[\d.]+" cy="([\d.]+)"/', $svg, $cy);
chk(min($cy[1]) >= 30 && max($cy[1]) <= 172, 'los puntos quedan dentro de la reja');

fin();
