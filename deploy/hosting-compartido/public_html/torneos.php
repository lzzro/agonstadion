<?php
require __DIR__ . '/../stadion_app/config/pagina.php';
require_once $carpeta_app . '/models/TorneoRepositorio.php';
require_once $carpeta_app . '/ligas.php';

# =====================================================================
# Torneos publicos - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Todo sale de la base (TorneoRepositorio::listarPublicos): los torneos
# con la inscripcion abierta, en curso o finalizados, con su estado, su
# organizador y su avance. Nada escrito a mano. Lo que organiza una
# cuenta de muestra (las ligas de la migracion 005) lleva la marca "De
# muestra".
#
# "Proximas fechas" junta dos cosas que tienen dia: el cierre de la
# fecha en juego de cada liga, y el comienzo de las que todavia tienen
# la inscripcion abierta. Solo lo que es de hoy en adelante.
# =====================================================================

$lista = null;
$conexion = conectarBD();
if ($conexion !== null) {
    $lista = (new TorneoRepositorio($conexion))->listarPublicos();
    $conexion->close();
}

$en_vivo  = 0;
$hoy      = date('Y-m-d');
$proximas = array();
if (is_array($lista)) {
    foreach ($lista as $fila) {
        $t = $fila['torneo'];
        if ($fila['en_vivo']) { $en_vivo++; }
        if ($t->estaEnCurso() && $fila['actual'] !== null && !empty($fila['fin_actual'])
            && diasEntre($hoy, $fila['fin_actual']) >= 0) {
            $proximas[$fila['fin_actual'] . '|' . count($proximas)] = array('dia' => $fila['fin_actual'],
                'torneo' => $t, 'que' => 'Fecha ' . $fila['actual'], 'verbo' => 'cierra');
        }
        if ($t->tieneInscripcionAbierta() && !empty($t->getFechaInicio())
            && diasEntre($hoy, $t->getFechaInicio()) >= 0) {
            $proximas[$t->getFechaInicio() . '|' . count($proximas)] = array('dia' => $t->getFechaInicio(),
                'torneo' => $t, 'que' => 'Inicio', 'verbo' => 'empieza');
        }
    }
    # De la mas cercana a la mas lejana (la clave empieza con el dia), y
    # solo las tres primeras.
    ksort($proximas);
    $proximas = array_slice($proximas, 0, 3);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Torneos · Stadion</title>
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
<nav><a href="index.php">Inicio</a><a href="torneos.php" class="activo" aria-current="page">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.php">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἀγῶνες</p>
  <h1>Torneos</h1>
<?php if (is_array($lista)) { ?>
  <p class="intro"><?php echo plural(count($lista), 'competencia pública', 'competencias públicas'); ?> · <?php echo $en_vivo; ?> en vivo</p>
<?php } ?>
</section>
<section>
  <h2 class="visualmente-oculto">Competencias públicas</h2>
<?php if ($lista === null) { ?>
  <p>La lista de torneos no se puede leer por ahora.</p>
<?php } elseif (empty($lista)) { ?>
  <p>Ninguna competencia a la vista. Toda liga empieza con un nombre y un cupo.</p>
<?php } else { ?>
  <div class="grilla">
<?php   foreach ($lista as $fila) {
          $t   = $fila['torneo'];
          $nom = htmlspecialchars($t->getNombre()); ?>
  <article class="tarjeta">
    <div class="fila"><?php echo chipLigaHtml($t, $fila['en_vivo']); ?><span class="etiqueta"><?php echo htmlspecialchars($t->getModulo()->getNombre() . ' · ' . $t->getDisciplina()->getNombre()); ?></span></div>
    <h3><?php echo $nom; ?></h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;"><?php echo htmlspecialchars($t->getOrganizador()->getNombreCompletoVisible() . ' · ' . avanceLiga($fila)); ?></p>
    <div class="barra"><span style="width:<?php echo porcentajeLiga($fila); ?>%"></span></div>
    <div class="fila" style="justify-content:space-between"><a href="torneo.php?id=<?php echo (int)$t->getIdTorneo(); ?>">Ver torneo<span class="visualmente-oculto"> <?php echo $nom; ?></span> <span aria-hidden="true">→</span></a><?php if ($t->esDeMuestra()) { echo marcaMuestra(); } ?></div>
  </article>
<?php   } ?>
  </div>
<?php } ?>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Próximas fechas</span>
<?php if (empty($proximas)) { ?>
  <p>Ninguna fecha a la vista.</p>
<?php } else { ?>
  <table><?php foreach ($proximas as $p) {
    $dias = diasEntre($hoy, $p['dia']); ?><tr><td><?php echo htmlspecialchars($p['torneo']->getNombre() . ' · ' . $p['que']); ?><br><small><?php echo $p['verbo'] . ' ' . (($dias === 0) ? 'hoy' : 'el ' . htmlspecialchars(fechaConDia($p['dia']))); ?></small></td><td class="num"><?php echo $dias; ?> d</td></tr><?php } ?></table>
<?php } ?>
</div>
<div class="tarjeta tarjeta-contraste">
  <span class="etiqueta">Para organizadores</span>
  <h3>Un estadio propio</h3>
  <p>Participantes, formato y fecha. Con eso, el calendario y la llave quedan trazados.</p>
  <a class="btn" href="crear.php">Crear torneo</a>
</div>
</aside>
<?php piePagina(true); ?>
</body>
</html>
