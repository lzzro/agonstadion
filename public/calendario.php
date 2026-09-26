<?php
require __DIR__ . '/../apps/config/pagina.php';
require_once $carpeta_app . '/models/EnfrentamientoRepositorio.php';
require_once $carpeta_app . '/ligas.php';

# =====================================================================
# Calendario del sitio - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Los partidos de una semana, de lunes a domingo, de todas las ligas
# publicas. Todo sale de la base (EnfrentamientoRepositorio::entreDias).
# El dia de la semana se calcula de la fecha, no se escribe a mano.
#
# calendario.php?semana=AAAA-MM-DD muestra la semana de ese dia. Sin
# semana, la del partido en vivo; si no hay, la del proximo partido; y
# si tampoco, la de hoy. "Semana anterior", "Hoy" y "Semana siguiente"
# son enlaces comunes, sin JavaScript.
#
# Un partido sin hora va en el ultimo dia de su fecha, como "Horario a
# confirmar". Los de una liga de muestra llevan la marca "De muestra".
# =====================================================================

$hoy = date('Y-m-d');
$partidos = null;
$lunes = null;

$pedida = isset($_GET['semana']) ? (string)$_GET['semana'] : '';
$conexion = conectarBD();
if ($conexion !== null) {
    $repositorio = new EnfrentamientoRepositorio($conexion);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pedida)
        && checkdate((int)substr($pedida, 5, 2), (int)substr($pedida, 8, 2), (int)substr($pedida, 0, 4))) {
        $lunes = lunesDe($pedida);
    } else {
        $destacado = $repositorio->diaDestacado(date('Y-m-d H:i:s'));
        $lunes = lunesDe(($destacado === null) ? $hoy : $destacado);
    }
    $partidos = $repositorio->entreDias($lunes, sumarDias($lunes, 6));
    $conexion->close();
} else {
    $lunes = lunesDe($hoy);
}
$domingo = sumarDias($lunes, 6);

# Por dia, en orden, y lo que dice el resumen de la semana.
$por_dia = array();
$disciplinas = array();
$torneos_semana = array();
$en_vivo = 0;
if (is_array($partidos)) {
    foreach ($partidos as $p) {
        $por_dia[$p['dia']][] = $p;
        $disciplinas[$p['torneo']->getDisciplina()->getNombre()] = true;
        $torneos_semana[(int)$p['torneo']->getIdTorneo()] = true;
        if ($p['enfrentamiento']->estaEnVivo()) { $en_vivo++; }
    }
}

# "Semana del 14 al 20 de septiembre", o "del 28 de septiembre al 4 de
# octubre" si cruza de mes.
if (substr($lunes, 5, 2) === substr($domingo, 5, 2)) {
    $titulo_semana = 'Semana del ' . (int)substr($lunes, 8, 2) . ' al ' . fechaTexto($domingo);
} else {
    $titulo_semana = 'Semana del ' . fechaTexto($lunes) . ' al ' . fechaTexto($domingo);
}
$es_esta_semana = ($lunes === lunesDe($hoy));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Calendario · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="<?php echo recurso($ruta_publica, 'css/style.css'); ?>">
  <script src="<?php echo recurso($ruta_publica, 'js/tema.js'); ?>"></script>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="pagina">
<header>
  <a class="marca" href="index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $persona_sesion); ?>
</header>
<nav><a href="index.php">Inicio</a><a href="torneos.php">Torneos</a><a href="calendario.php" class="activo" aria-current="page">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.php">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἡμέραι</p>
  <h1><?php echo htmlspecialchars($titulo_semana); ?></h1>
  <div class="fila semanas"><a href="calendario.php?semana=<?php echo sumarDias($lunes, -7); ?>"><span aria-hidden="true">←</span> Semana anterior</a><?php if ($es_esta_semana) { ?><span class="enlace-apagado" aria-disabled="true">Hoy</span><?php } else { ?><a href="calendario.php?semana=<?php echo lunesDe($hoy); ?>">Hoy</a><?php } ?><a href="calendario.php?semana=<?php echo sumarDias($lunes, 7); ?>">Semana siguiente <span aria-hidden="true">→</span></a></div>
<?php if ($partidos === null) { ?>
  <p class="intro">El calendario no se puede leer por ahora.</p>
<?php } elseif (empty($partidos)) { ?>
  <p class="intro">Ningún partido en esta semana.</p>
<?php } else { ?>
  <p class="intro"><?php echo mayuscula(plural(count($por_dia), 'día con partidos', 'días con partidos')); ?>.<?php if ($en_vivo > 0) { echo ($en_vivo === 1) ? ' Uno en vivo.' : ' ' . $en_vivo . ' en vivo.'; } ?></p>
<?php } ?>
</section>
<?php if (!empty($disciplinas)) { ?>
<section class="tarjeta">
  <span class="etiqueta">Disciplinas de la semana</span>
  <p><?php echo htmlspecialchars(implode(' · ', array_keys($disciplinas))); ?></p>
</section>
<?php } ?>
<?php if (!empty($por_dia)) { ?>
<section class="agenda">
<h2 class="visualmente-oculto">Partidos de la semana</h2>
<?php   foreach ($por_dia as $dia => $lista) { ?>
<div class="agenda-dia">
  <p class="etiqueta dia"><?php echo htmlspecialchars(mayuscula(fechaConDia($dia))); ?></p>
<?php     foreach ($lista as $p) {
            $t = $p['torneo'];
            $arriba = '<a href="torneo.php?id=' . (int)$t->getIdTorneo() . '">' . htmlspecialchars($t->getNombre()) . '</a> · '
                    . htmlspecialchars($p['ronda']->getNombreVisible());
            if ($p['enfrentamiento']->getFechaHora() === null) {
                $arriba .= ' · Horario a confirmar';
            }
            if ($t->esDeMuestra()) {
                $arriba .= ' ' . marcaMuestra();
            }
            filaPartido($p['enfrentamiento'], $arriba);
          } ?>
</div>
<?php   } ?>
</section>
<?php } ?>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Exportar</span>
  <h3>El calendario en el teléfono</h3>
  <p>Los partidos de los torneos seguidos aparecen entre los eventos del teléfono, con su hora y su ronda.</p>
  <span class="enlace-apagado" aria-disabled="true">Copiar enlace iCal</span>
</div>
<div class="tarjeta tarjeta-olivo">
  <span class="etiqueta">Esta semana</span>
  <h3><?php echo plural(is_array($partidos) ? count($partidos) : 0, 'partido', 'partidos'); ?></h3>
  <p><?php echo plural(count($disciplinas), 'disciplina', 'disciplinas'); ?> · <?php echo plural(count($torneos_semana), 'torneo', 'torneos'); ?></p>
</div>
</aside>
<?php piePagina(true); ?>
</body>
</html>
