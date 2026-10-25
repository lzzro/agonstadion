<?php
# =====================================================================
# Vista parcial: rendimiento fisico (RF-29)
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Lo que dibuja la pestana Rendimiento del perfil con las mediciones de
# la base: el grafico de la evolucion de un indicador, en SVG y sin
# JavaScript (el mismo estilo del grafico de la maqueta: .grafico,
# .reja, .trazo, .punto, .rotulo), y el nombre corto de una persona
# para la comparacion ("H. Alejandría").
#
# Cada numero del grafico va escrito (los rotulos de los ejes, el primer
# y el ultimo valor, y un <title> por punto), y el SVG lleva en
# aria-label lo que muestra, para quien no lo ve.
# =====================================================================

require_once __DIR__ . '/models/Medicion.php';
require_once __DIR__ . '/fechas.php';

# "H. Alejandría": la inicial del nombre y el apellido, para mostrar.
function nombreCorto(Usuario $usuario)
{
    $nombre = $usuario->getNombreVisible();
    return mb_substr($nombre, 0, 1, 'UTF-8') . '. ' . $usuario->getApellidoVisible();
}

# "12/10"
function diaMes($fecha)
{
    return substr($fecha, 8, 2) . '/' . substr($fecha, 5, 2);
}

# El grafico de la evolucion de una prueba. $mediciones: las de esa
# prueba, de la mas vieja a la mas nueva (al menos dos).
function graficoEvolucion($mediciones, $prueba)
{
    $datos   = Medicion::pruebas();
    $nombre  = $datos[$prueba]['indicador'];
    $corta   = $datos[$prueba]['corta'];
    $valores = array();
    foreach ($mediciones as $m) {
        $valores[] = $m->indicador();
    }
    $n = count($valores);

    # Los ejes: de un poco menos que el minimo a un poco mas que el
    # maximo, con cuatro lineas de reja a pasos redondos.
    $minimo = min($valores);
    $maximo = max($valores);
    if ($maximo - $minimo < 1e-9) {
        $minimo = $minimo - 1;
        $maximo = $maximo + 1;
    }
    $paso_crudo = ($maximo - $minimo) / 3;
    $potencia   = pow(10, floor(log10($paso_crudo)));
    $paso = $potencia;
    foreach (array(1, 2, 2.5, 5, 10) as $f) {
        if ($f * $potencia >= $paso_crudo) {
            $paso = $f * $potencia;
            break;
        }
    }
    $abajo  = floor($minimo / $paso) * $paso;
    $arriba = $abajo + 3 * $paso;
    while ($arriba < $maximo) {
        $arriba = $arriba + $paso;
    }
    $lineas = (int)round(($arriba - $abajo) / $paso);

    $x0 = 60; $x1 = 520; $y0 = 30; $y1 = 172;
    $x = function ($i) use ($n, $x0, $x1) { return ($n === 1) ? ($x0 + $x1) / 2 : $x0 + ($x1 - $x0) * $i / ($n - 1); };
    $y = function ($v) use ($abajo, $arriba, $y0, $y1) { return $y1 - ($y1 - $y0) * ($v - $abajo) / ($arriba - $abajo); };
    $decimales = ($prueba === 'sprint') ? 2 : 0;
    $texto = function ($v) use ($decimales) { return number_format($v, $decimales, ',', '.'); };

    $primero = $mediciones[0];
    $ultimo  = $mediciones[$n - 1];
    $resumen = $nombre . ' en ' . $n . ' mediciones, del ' . fechaTexto($primero->getFecha(), true) . ' al '
             . fechaTexto($ultimo->getFecha(), true) . ': de ' . $texto($valores[0]) . ' a ' . $texto($valores[$n - 1])
             . ' ' . $datos[$prueba]['unidad'] . '.';

    $svg = '<svg class="grafico grafico-evolucion" viewBox="0 0 560 212" role="img" aria-label="' . htmlspecialchars($resumen) . '">' . "\n";
    for ($k = 0; $k <= $lineas; $k++) {
        $v  = $abajo + $k * $paso;
        $yy = round($y($v), 1);
        $svg .= '  <line class="reja" x1="52" y1="' . $yy . '" x2="530" y2="' . $yy . '"/>'
              . '<text class="rotulo" x="44" y="' . ($yy + 4) . '" text-anchor="end">' . $texto($v) . '</text>' . "\n";
    }
    $puntos = array();
    for ($i = 0; $i < $n; $i++) {
        $puntos[] = round($x($i), 1) . ',' . round($y($valores[$i]), 1);
    }
    $svg .= '  <polyline class="trazo" points="' . implode(' ', $puntos) . '"/>' . "\n";
    for ($i = 0; $i < $n; $i++) {
        $svg .= '  <circle class="punto" cx="' . round($x($i), 1) . '" cy="' . round($y($valores[$i]), 1) . '" r="4"><title>'
              . diaMes($mediciones[$i]->getFecha()) . ': ' . $texto($valores[$i]) . ' ' . $corta . '</title></circle>' . "\n";
    }
    # El primer y el ultimo valor escritos, del lado contrario al tramo
    # que llega a su punto (asi la linea no los tapa), y las fechas abajo:
    # todas si son pocas, si no la primera y la ultima. En el telefono los
    # dos valores no se dibujan (style.css): estan en las tarjetas y en la
    # tabla de mediciones.
    foreach (array(0 => 1, $n - 1 => $n - 2) as $i => $vecino) {
        $yy    = $y($valores[$i]);
        $abajo_del_punto = ($y($valores[$vecino]) < $yy);
        $svg .= '  <text class="rotulo-dato" x="' . round(($i === 0) ? $x($i) - 6 : $x($i) + 6, 1) . '" y="'
              . round($abajo_del_punto ? $yy + 9 : $yy - 9, 1) . '" text-anchor="' . (($i === 0) ? 'start' : 'end') . '"'
              . ($abajo_del_punto ? ' dominant-baseline="hanging"' : '') . '>' . $texto($valores[$i]) . '</text>' . "\n";
    }
    for ($i = 0; $i < $n; $i++) {
        if ($n <= 8 || $i === 0 || $i === $n - 1) {
            $svg .= '  <text class="rotulo" x="' . round($x($i), 1) . '" y="205" text-anchor="middle">' . diaMes($mediciones[$i]->getFecha()) . '</text>' . "\n";
        }
    }
    return $svg . '</svg>' . "\n";
}

# El dato crudo de una medicion, en pocas palabras y en HTML: "15,0 cm",
# "2,30 s · 68,0 kg". Cada dato en su <span class="dato">, que no se
# parte: en el telefono el renglon se corta solo entre los dos.
function datoCrudo(Medicion $m)
{
    if ($m->getPrueba() === 'reaccion') {
        return '<span class="dato">' . number_format($m->getDistanciaCm(), 1, ',', '.') . ' cm</span>';
    }
    return '<span class="dato">' . number_format($m->getTiempoS(), 2, ',', '.') . ' s</span> · <span class="dato">'
         . number_format($m->getMasaKg(), 1, ',', '.') . ' kg</span>';
}
