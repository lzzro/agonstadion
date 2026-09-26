<?php
# =====================================================================
# El fixture (metodo del circulo), sin base - Stadion (Agon)
# ---------------------------------------------------------------------
#   php tests/php/fixture_propiedades.php
#
# Para cada cantidad de equipos de una liga (4 a 32), de una vuelta y de
# ida y vuelta, comprueba lo que pide la fase 2:
#   - cada equipo juega contra cada otro exactamente una vez por vuelta;
#   - nadie juega dos veces en la misma fecha (y todos aparecen en todas);
#   - con una cantidad impar, uno queda libre en cada fecha, al final;
#   - la cantidad de fechas es la que dice ConfiguracionTorneo;
#   - en una vuelta, la localia esta repartida (nadie es local mas de
#     una vez por encima de otro);
#   - la vuelta repite la ida, fecha por fecha, con la localia al reves;
#   - con los mismos equipos en el mismo orden, sale el mismo fixture.
# =====================================================================

require __DIR__ . '/comun.php';
require_once $APLICACION . '/models/Fixture.php';
require_once $APLICACION . '/models/Equipo.php';
require_once $APLICACION . '/models/ConfiguracionTorneo.php';

echo "===== Fixture: propiedades del metodo del circulo =====\n";

function equipos($n)
{
    $lista = array();
    for ($i = 1; $i <= $n; $i++) {
        $lista[] = new Participante($i, null, new Equipo($i, 'Equipo ' . $i));
    }
    return $lista;
}

function comoTexto($fechas)
{
    $texto = array();
    foreach ($fechas as $cruces) {
        $fila = array();
        foreach ($cruces as $cruce) {
            $fila[] = $cruce['local']->getIdParticipante() . '-'
                    . (($cruce['visitante'] === null) ? 'libre' : $cruce['visitante']->getIdParticipante());
        }
        $texto[] = implode(' ', $fila);
    }
    return implode(' | ', $texto);
}

$problemas = array();
$casos = 0;
for ($n = 4; $n <= 32; $n++) {
    foreach (array(0, 1) as $iv) {
        $casos++;
        $fixture = new Fixture(equipos($n), $iv);
        $errores = $fixture->generar();
        if (!empty($errores)) { $problemas[] = "$n/$iv: " . implode(' ', $errores); continue; }
        $fechas  = $fixture->getFechas();
        $vueltas = $iv + 1;
        $config  = new ConfiguracionTorneo(null, 3, 1, 0, 1, 0, $iv);

        if (count($fechas) !== $config->rondasDeLiga($n)) {
            $problemas[] = "$n/$iv: " . count($fechas) . ' fechas, ConfiguracionTorneo dice ' . $config->rondasDeLiga($n);
        }
        $parejas = array();
        $locales = array();
        foreach ($fechas as $k => $cruces) {
            $vistos = array();
            $libres = 0;
            foreach ($cruces as $j => $cruce) {
                $l = $cruce['local']->getIdParticipante();
                $vistos[] = $l;
                if ($cruce['visitante'] === null) {
                    $libres++;
                    if ($j !== count($cruces) - 1) { $problemas[] = "$n/$iv fecha $k: el libre no va al final"; }
                    continue;
                }
                $v = $cruce['visitante']->getIdParticipante();
                $vistos[] = $v;
                $clave = min($l, $v) . '-' . max($l, $v);
                $parejas[$clave] = isset($parejas[$clave]) ? $parejas[$clave] + 1 : 1;
                if ($k < count($fechas) / $vueltas) {
                    $locales[$l] = isset($locales[$l]) ? $locales[$l] + 1 : 1;
                }
            }
            if (count($vistos) !== count(array_unique($vistos)) || count($vistos) !== $n) {
                $problemas[] = "$n/$iv fecha $k: alguien juega dos veces o falta alguien";
            }
            if ($libres !== $n % 2) {
                $problemas[] = "$n/$iv fecha $k: $libres libres";
            }
        }
        if (count($parejas) !== $n * ($n - 1) / 2) {
            $problemas[] = "$n/$iv: " . count($parejas) . ' parejas, deberian ser ' . ($n * ($n - 1) / 2);
        }
        foreach ($parejas as $clave => $veces) {
            if ($veces !== $vueltas) { $problemas[] = "$n/$iv: la pareja $clave se cruza $veces veces"; }
        }
        for ($i = 1; $i <= $n; $i++) {
            if (!isset($locales[$i])) { $locales[$i] = 0; }
        }
        if (max($locales) - min($locales) > 1) {
            $problemas[] = "$n/$iv: localia despareja (" . min($locales) . ' a ' . max($locales) . ')';
        }
        if ($iv === 1) {
            $mitad = count($fechas) / 2;
            for ($k = 0; $k < $mitad; $k++) {
                foreach ($fechas[$k] as $j => $cruce) {
                    $otro = $fechas[$k + $mitad][$j];
                    $ok = ($cruce['visitante'] === null)
                        ? ($otro['visitante'] === null && $otro['local'] === $cruce['local'])
                        : ($otro['local'] === $cruce['visitante'] && $otro['visitante'] === $cruce['local']);
                    if (!$ok) { $problemas[] = "$n/1: la vuelta de la fecha " . ($k + 1) . ' no es la ida al reves'; }
                }
            }
        }
        $otra = new Fixture(equipos($n), $iv);
        $otra->generar();
        if (comoTexto($otra->getFechas()) !== comoTexto($fechas)) {
            $problemas[] = "$n/$iv: con los mismos equipos sale otro fixture";
        }
    }
}
chk(empty($problemas), "$casos casos (4 a 32 equipos, una vuelta e ida y vuelta) sin ningun problema"
                       . (empty($problemas) ? '' : ': ' . implode('; ', array_slice($problemas, 0, 5))));

# Fuera del cupo de una liga.
$chico = new Fixture(equipos(3));
chk(!empty($chico->generar()), 'con 3 equipos no se arma');
$grande = new Fixture(equipos(33));
chk(!empty($grande->generar()), 'con 33 equipos no se arma');

# Un caso chico, escrito a mano: 4 equipos, una vuelta.
$cuatro = new Fixture(equipos(4));
$cuatro->generar();
chk(comoTexto($cuatro->getFechas()) === '1-4 2-3 | 3-1 4-2 | 1-2 3-4',
    'con 4 equipos: 1-4 2-3 | 3-1 4-2 | 1-2 3-4 (' . comoTexto($cuatro->getFechas()) . ')');
$cinco = new Fixture(equipos(5));
$cinco->generar();
chk(comoTexto($cinco->getFechas()) === '2-5 3-4 1-libre | 5-1 2-3 4-libre | 1-4 5-3 2-libre | 3-1 4-2 5-libre | 1-2 4-5 3-libre',
    'con 5 equipos, el libre al final de cada fecha (' . comoTexto($cinco->getFechas()) . ')');

fin();
