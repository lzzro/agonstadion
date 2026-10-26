<?php
# Vista: reportes de los torneos (RF-28)

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/pie.php';
require_once __DIR__ . '/ligas.php';
require_once __DIR__ . '/config/recursos.php';

# Un grafico de barras horizontales.
function barrasReporte($filas, $titulo)
{
    $maximo = 1;
    foreach ($filas as $f) {
        $maximo = max($maximo, $f['lleno'] + $f['pendiente'] + $f['vacio']);
    }
    $alto = count($filas) * 46;
    $resumen = array();
    foreach ($filas as $f) {
        $resumen[] = $f['nombre'] . ', ' . $f['texto'];
    }
    $svg = '<svg class="grafico grafico-barras grafico-reporte" viewBox="0 0 480 ' . $alto . '" role="img" aria-label="'
         . htmlspecialchars($titulo . ': ' . implode('; ', $resumen) . '.') . '">' . "\n";
    foreach ($filas as $i => $f) {
        $y = $i * 46;
        $nombre = (mb_strlen($f['nombre'], 'UTF-8') > 38) ? mb_substr($f['nombre'], 0, 37, 'UTF-8') . '…' : $f['nombre'];
        $svg .= '  <text class="rotulo-barra" x="0" y="' . ($y + 14) . '">' . htmlspecialchars($nombre) . '</text>'
              . '<text class="rotulo-barra" x="480" y="' . ($y + 14) . '" text-anchor="end">' . htmlspecialchars($f['texto']) . '</text>' . "\n";
        $x = 0;
        foreach (array('lleno' => 'tramo', 'pendiente' => 'tramo-pendiente', 'vacio' => 'tramo-vacio') as $clave => $clase) {
            if ($f[$clave] <= 0) {
                continue;
            }
            $ancho = 478 * $f[$clave] / $maximo;
            $svg .= '  <rect class="' . $clase . '" x="' . round($x + 1, 1) . '" y="' . ($y + 21) . '" width="' . round($ancho, 1) . '" height="14"/>' . "\n";
            $x = $x + $ancho;
        }
    }
    return $svg . '</svg>' . "\n";
}

# Un numero con una cifra decimal y coma: "3,4".
function promedioTexto($total, $cantidad)
{
    return ($cantidad === 0) ? '—' : number_format($total / $cantidad, 1, ',', '.');
}

# Los totales y las filas de los graficos.
$cantidad = 0;
$inscriptos_total = 0;
$jugados_total = 0;
$partidos_total = 0;
$tantos_total = 0;
$con_marcador_total = 0;
$por_estado = array('en_curso' => 0, 'inscripcion' => 0, 'finalizado' => 0, 'cancelado' => 0);
$filas_inscriptos = array();
$filas_partidos = array();
if (is_array($lista)) {
    foreach ($lista as $fila) {
        $t  = $fila['torneo'];
        $id = (int)$t->getIdTorneo();
        $n  = $numeros[$id];
        if (isset($por_estado[$t->getEstado()])) {
            $por_estado[$t->getEstado()]++;
        }
        $jugados_total = $jugados_total + $n['jugados'];
        $tantos_total = $tantos_total + $n['tantos'];
        $con_marcador_total = $con_marcador_total + $n['con_marcador'];
        if ($t->getEstado() === 'cancelado') {
            continue;
        }
        $cantidad++;
        $inscriptos_total = $inscriptos_total + $fila['inscriptos'];
        $partidos_total = $partidos_total + $n['partidos'];
        $filas_inscriptos[] = array('nombre' => $t->getNombre(), 'lleno' => $fila['inscriptos'], 'pendiente' => 0,
                                    'vacio' => max(0, $t->getMaxParticipantes() - $fila['inscriptos']),
                                    'texto' => $fila['inscriptos'] . ' de ' . $t->getMaxParticipantes());
        if ($n['partidos'] > 0) {
            $filas_partidos[] = array('nombre' => $t->getNombre(), 'lleno' => $n['jugados'],
                                      'pendiente' => $n['partidos'] - $n['jugados'], 'vacio' => 0,
                                      'texto' => plural($n['jugados'], 'jugado', 'jugados') . ' · '
                                               . ($n['partidos'] - $n['jugados']) . ' por jugar');
        }
    }
}
$nombres_estado = array('en_curso' => array('estado-en-juego', 'En juego'),
                        'inscripcion' => array('estado-inscripcion', 'Inscripción abierta'),
                        'finalizado' => array('estado-cerrado', 'Finalizado'),
                        'cancelado' => array('estado-cerrado', 'Cancelado'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Reportes · Stadion</title>
  <link rel="icon" href="<?php echo $ruta_publica; ?>/img/stadion.png">
  <link rel="stylesheet" href="<?php echo recurso($ruta_publica, 'css/style.css'); ?>">
  <script src="<?php echo recurso($ruta_publica, 'js/tema.js'); ?>"></script>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="pagina">
<header>
  <a class="marca" href="<?php echo $ruta_publica; ?>/index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $persona); ?>
</header>
<nav><a href="<?php echo $ruta_publica; ?>/index.php">Inicio</a><a href="<?php echo $ruta_publica; ?>/torneos.php">Torneos</a><a href="<?php echo $ruta_publica; ?>/calendario.php">Calendario</a><a href="<?php echo $ruta_publica; ?>/torneo.php#posiciones">Posiciones</a><a href="<?php echo $ruta_publica; ?>/panel.php" class="activo" aria-current="true">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἱστορίη</p>
  <h1>Reportes</h1>
  <p class="intro"><?php echo $de_todos ? 'Todos los torneos del sitio, de todas las cuentas.' : 'Los torneos a cargo de esta cuenta.'; ?></p>
  <div class="fila">
<?php if ($persona->tieneRol('organizador')) { ?>
    <a href="<?php echo htmlspecialchars($ruta_panel); ?>"><span aria-hidden="true">←</span> Panel del organizador</a>
<?php } ?>
<?php if ($de_todos) { ?>
    <a href="<?php echo htmlspecialchars($ruta_admin); ?>"><span aria-hidden="true">←</span> Administración</a>
<?php } ?>
  </div>
</section>

<?php if ($lista === null) { ?>
<section class="tarjeta">
  <p>Los reportes no se pueden leer por ahora.</p>
</section>
<?php } elseif (empty($lista)) { ?>
<section class="tarjeta">
  <p><?php echo $de_todos ? 'Ningún torneo en el sitio.' : 'Ningún torneo a cargo de esta cuenta.'; ?></p>
</section>
<?php } else { ?>
<div class="datos">
  <div><strong><?php echo $cantidad; ?></strong><span class="etiqueta"><?php echo ($cantidad === 1) ? 'Torneo' : 'Torneos'; ?></span></div>
  <div><strong><?php echo $inscriptos_total; ?></strong><span class="etiqueta">Participantes</span></div>
  <div><strong><?php echo $jugados_total; ?></strong><span class="etiqueta">Partidos jugados</span></div>
  <div><strong><?php echo promedioTexto($tantos_total, $con_marcador_total); ?></strong><span class="etiqueta">Tantos por partido</span></div>
</div>

<?php   if (!empty($filas_inscriptos)) { ?>
<section class="tarjeta" id="inscriptos">
  <h2>Inscriptos por torneo</h2>
  <span class="etiqueta">Inscriptos sobre el cupo</span>
  <?php echo barrasReporte($filas_inscriptos, 'Inscriptos sobre el cupo'); ?>
</section>
<?php   } ?>

<?php   if (!empty($filas_partidos)) { ?>
<section class="tarjeta" id="partidos">
  <h2>Partidos</h2>
  <span class="etiqueta">Jugados y por jugar</span>
  <?php echo barrasReporte($filas_partidos, 'Partidos jugados y por jugar'); ?>
  <p><small>Los partidos ya armados: en la eliminación directa y el suizo, cada ronda se arma al cerrar la anterior.</small></p>
</section>
<?php   } ?>

<section class="tarjeta" id="detalle">
  <h2>Detalle por torneo</h2>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Detalle por torneo">
  <table class="detalle-reporte">
    <thead><tr><th>Torneo</th><th>Estado</th><th class="num">Inscriptos</th><th class="num">Jugados</th><th class="num">Tantos por partido</th><th>Campeón o primero</th></tr></thead>
    <tbody>
<?php   foreach ($lista as $fila) {
          $t  = $fila['torneo'];
          $id = (int)$t->getIdTorneo();
          $n  = $numeros[$id];
          $primero = isset($primero_de[$id]) ? $primero_de[$id] : null;
          $publico = in_array($t->getEstado(), array('inscripcion', 'en_curso', 'finalizado'), true); ?>
      <tr>
        <td><?php if ($publico) { ?><a href="<?php echo $ruta_publica; ?>/torneo.php?id=<?php echo $id; ?>"><?php echo htmlspecialchars($t->getNombre()); ?></a><?php } else { echo htmlspecialchars($t->getNombre()); } ?><?php if ($t->getOrganizador()->esDeMuestra()) { echo ' ' . marcaMuestra(); } ?><br><small><?php echo htmlspecialchars(formatoTexto($t)); ?><?php if ($de_todos) { echo ' · organiza ' . htmlspecialchars($t->getOrganizador()->getNombreCompletoVisible()); } ?></small></td>
        <td><?php echo chipLigaHtml($t, $fila['en_vivo']); ?></td>
        <td class="num"><?php echo $fila['inscriptos']; ?> de <?php echo $t->getMaxParticipantes(); ?></td>
        <td class="num"><?php echo $n['jugados']; ?> de <?php echo $n['partidos']; ?><?php if ($n['walkovers'] > 0 || $n['empates'] > 0) { ?><br><small><?php echo plural($n['walkovers'], 'W.O.', 'W.O.'); ?> · <?php echo plural($n['empates'], 'empate', 'empates'); ?></small><?php } ?></td>
        <td class="num"><?php echo promedioTexto($n['tantos'], $n['con_marcador']); ?></td>
        <td><?php if ($primero === null) { echo '—'; } elseif ($t->getEstado() === 'finalizado') { echo htmlspecialchars($primero->getNombreVisible()) . ' ' . chipVencedor(); } else { echo htmlspecialchars($primero->getNombreVisible()) . ' <small>· va primero</small>'; } ?></td>
      </tr>
<?php   } ?>
    </tbody>
  </table>
  </div>
</section>
<?php } ?>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Por estado</span>
  <ul class="lista-apilada por-estado">
<?php foreach ($por_estado as $estado => $cuantos) { ?>
    <li><span class="estado <?php echo $nombres_estado[$estado][0]; ?>"><?php echo $nombres_estado[$estado][1]; ?></span><strong><?php echo $cuantos; ?></strong></li>
<?php } ?>
  </ul>
  <p><small>Un torneo cancelado conserva lo jugado: cuenta en los partidos y los tantos, no en los inscriptos.</small></p>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
