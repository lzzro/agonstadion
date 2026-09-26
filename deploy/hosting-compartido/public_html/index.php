<?php
require __DIR__ . '/../stadion_app/config/pagina.php';
require_once $carpeta_app . '/models/TorneoRepositorio.php';
require_once $carpeta_app . '/models/ParticipanteRepositorio.php';
require_once $carpeta_app . '/models/FixtureRepositorio.php';
require_once $carpeta_app . '/models/CatalogoRepositorio.php';
require_once $carpeta_app . '/ligas.php';

# =====================================================================
# Inicio - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La portada. Los numeros, las tarjetas de torneos y el recuadro del
# costado salen de la base, igual que en torneos.php y torneo.php: nada
# escrito a mano. Los torneos que organiza una cuenta de muestra llevan
# la marca "De muestra".
#
#   Torneos activos   los que tienen la inscripcion abierta o estan en
#                     curso
#   Participantes     los inscriptos en los torneos publicos
#   Formatos          los del catalogo (liga, eliminacion, suizo)
# Los dos primeros llevan la marca si suman ligas de muestra: "De
# muestra" si todo sale de ellas, "Incluye muestra" si solo una parte.
#
# El recuadro del costado es la fecha en juego de la liga destacada (la
# misma de "Posiciones" en el menu), con sus primeros partidos.
# =====================================================================

$lista = null;
$formatos = null;
$destacada = null;
$fecha_destacada = null;
$conexion = conectarBD();
if ($conexion !== null) {
    $torneos = new TorneoRepositorio($conexion);
    $lista = $torneos->listarPublicos();
    $formatos = (new CatalogoRepositorio($conexion))->cantidadModulos();
    $id_destacada = $torneos->idDestacado();
    if ($id_destacada !== null) {
        $destacada = $torneos->buscarPorId($id_destacada);
        $todos = (new ParticipanteRepositorio($conexion))->listarDeTorneo($id_destacada, true);
        $rondas = ($todos === null) ? null : (new FixtureRepositorio($conexion))->fechasDe($id_destacada, $todos);
        if (is_array($rondas)) {
            foreach ($rondas as $ronda) {
                if ($fecha_destacada === null && !$ronda->estaCerrada()) { $fecha_destacada = $ronda; }
            }
        }
    }
    $conexion->close();
}

$activos = 0;
$activos_muestra = 0;
$participantes = 0;
$participantes_muestra = 0;
$en_vivo_destacada = false;
if (is_array($lista)) {
    foreach ($lista as $fila) {
        $de_muestra = $fila['torneo']->esDeMuestra();
        $participantes += $fila['inscriptos'];
        if ($de_muestra) { $participantes_muestra += $fila['inscriptos']; }
        if ($fila['torneo']->tieneInscripcionAbierta() || $fila['torneo']->estaEnCurso()) {
            $activos++;
            if ($de_muestra) { $activos_muestra++; }
        }
        if ($destacada !== null && (int)$fila['torneo']->getIdTorneo() === (int)$destacada->getIdTorneo()) {
            $en_vivo_destacada = $fila['en_vivo'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Inicio · Stadion</title>
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
<nav><a href="index.php" class="activo" aria-current="page">Inicio</a><a href="torneos.php">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.php">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἀγών · στάδιον</p>
  <h1>Toda competencia merece un <em>estadio.</em></h1>
  <p class="intro">Liga, eliminación directa o sistema suizo. Esports, ajedrez, tenis de mesa o fútbol: inscripciones, enfrentamientos, resultados y posiciones en un solo lugar.</p>
  <div class="fila"><a class="btn btn-primario" href="crear.php">Organizar un torneo</a><a class="btn" href="torneos.php">Ver torneos públicos</a></div>
<?php if (is_array($lista)) { ?>
  <div class="datos"><div><strong><?php echo $activos; ?></strong><span class="etiqueta">Torneos activos</span><?php echo marcaTotal($activos_muestra, $activos); ?></div><div><strong><?php echo $participantes; ?></strong><span class="etiqueta">Participantes</span><?php echo marcaTotal($participantes_muestra, $participantes); ?></div><div><strong><?php echo (int)$formatos; ?></strong><span class="etiqueta">Formatos</span></div></div>
<?php } ?>
</section>
<section>
  <h2>Torneos en marcha</h2>
<?php if ($lista === null) { ?>
  <p>Los torneos no se pueden leer por ahora.</p>
<?php } elseif (empty($lista)) { ?>
  <p>Ninguna competencia a la vista. Toda liga empieza con un nombre y un cupo.</p>
<?php } else { ?>
  <div class="grilla">
<?php   foreach (array_slice($lista, 0, 3) as $fila) {
          $t   = $fila['torneo'];
          $nom = htmlspecialchars($t->getNombre()); ?>
  <article class="tarjeta">
    <div class="fila"><?php echo chipLigaHtml($t, $fila['en_vivo']); ?><span class="etiqueta"><?php echo htmlspecialchars($t->getModulo()->getNombre() . ' · ' . $t->getDisciplina()->getNombre()); ?></span></div>
    <h3><?php echo $nom; ?></h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;"><?php echo htmlspecialchars(avanceLiga($fila)); ?></p>
    <div class="barra"><span style="width:<?php echo porcentajeLiga($fila); ?>%"></span></div>
    <div class="fila" style="justify-content:space-between"><a href="torneo.php?id=<?php echo (int)$t->getIdTorneo(); ?>">Ver torneo<span class="visualmente-oculto"> <?php echo $nom; ?></span> <span aria-hidden="true">→</span></a><?php if ($t->esDeMuestra()) { echo marcaMuestra(); } ?></div>
  </article>
<?php   } ?>
  </div>
<?php } ?>
</section>
<section>
  <h2>Cada contienda sigue su propia ley.</h2>
  <div class="grilla">
    <article class="tarjeta"><h3>Liga</h3><p>Todos contra todos. El calendario completo se genera al abrir el torneo.</p></article>
    <article class="tarjeta"><h3>Eliminación directa</h3><p>Llaves que avanzan solas: el ganador espera en la siguiente ronda.</p></article>
    <article class="tarjeta"><h3>Sistema suizo</h3><p>Emparejamiento por rendimiento, sin repetir rivales.</p></article>
  </div>
</section>
</main>
<aside>
<?php if ($destacada !== null && $fecha_destacada !== null) {
        $partidos = array_slice($fecha_destacada->getEnfrentamientos(), 0, 3); ?>
<div class="tarjeta">
  <div class="fila" style="justify-content:space-between"><span class="etiqueta"><?php echo htmlspecialchars($fecha_destacada->getNombreVisible()); ?></span><?php echo $en_vivo_destacada ? '<span class="estado estado-en-vivo">En vivo</span>' : chipLigaHtml($destacada, false); ?></div>
  <h3><?php echo htmlspecialchars($destacada->getNombre()); ?></h3>
  <table class="en-vivo"><?php foreach ($partidos as $e) {
      $r = $e->getResultado();
      if ($r !== null) { $centro = $r->getPuntajeLocal() . ' – ' . $r->getPuntajeVisitante(); }
      elseif (!$e->estaEnVivo() && horaTexto($e->getFechaHora()) !== '') { $centro = horaTexto($e->getFechaHora()); }
      else { $centro = '—'; } ?><tr><td><?php echo htmlspecialchars($e->getLocal()->getNombreVisible()); ?></td><td class="num marcador"><?php echo $centro; ?></td><td><?php echo $e->esLibre() ? 'libre' : htmlspecialchars($e->getVisitante()->getNombreVisible()); ?></td></tr><?php } ?></table>
  <div class="fila" style="justify-content:space-between"><a href="torneo.php?id=<?php echo (int)$destacada->getIdTorneo(); ?>#posiciones">Tabla completa<span class="visualmente-oculto"> de <?php echo htmlspecialchars($destacada->getNombre()); ?></span> <span aria-hidden="true">→</span></a><?php if ($destacada->esDeMuestra()) { echo marcaMuestra(); } ?></div>
</div>
<?php } ?>
<div class="tarjeta">
  <span class="etiqueta">Por qué olivo</span>
  <p><em>«¿Contra qué clase de hombres nos has traído a luchar? Hombres que no compiten por riquezas, sino por la virtud.»</em></p>
  <span class="etiqueta">Heródoto, Historias VIII</span>
</div>
</aside>
<?php piePagina(true); ?>
</body>
</html>
