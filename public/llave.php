<?php
require __DIR__ . '/../apps/config/pagina.php';
require_once $carpeta_app . '/models/TorneoRepositorio.php';
require_once $carpeta_app . '/ligas.php';

# =====================================================================
# Llave - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La llave es la de la eliminacion directa, y en la fase 2 del motor de
# torneos solo hay ligas: la eliminacion directa y el suizo son de la
# tercera entrega. Esta pagina ya no muestra la llave de muestra escrita
# a mano (la Copa Interliceal de Ajedrez, que queda en el historial del
# repositorio): lee de la base los torneos que usarian una llave, y como
# no hay ninguno, lo dice.
#
# llave.php?id=N de una liga dice que una liga no tiene llave, y lleva a
# su tabla, que es lo que ordena una liga.
#
# Marca Torneos en el menu, con aria-current="true": es el detalle de un
# torneo, igual que torneo.php.
# =====================================================================

$lista = null;
$liga  = null;
$conexion = conectarBD();
if ($conexion !== null) {
    $torneos = new TorneoRepositorio($conexion);
    $lista = $torneos->listarPublicos();
    if (isset($_GET['id']) && is_string($_GET['id']) && (int)$_GET['id'] > 0) {
        $liga = $torneos->buscarPorId((int)$_GET['id']);
        # Solo un torneo publico: un borrador o uno cancelado no se nombra
        # (torneo.php tampoco lo muestra).
        if ($liga !== null && !in_array($liga->getEstado(), array('inscripcion', 'en_curso', 'finalizado'))) {
            $liga = null;
        }
    }
    $conexion->close();
}

# Los torneos publicos que no son ligas: los que tendrian llave.
$con_llave = 0;
$ligas = array();
if (is_array($lista)) {
    foreach ($lista as $fila) {
        if ($fila['torneo']->esLiga()) {
            $ligas[] = $fila['torneo'];
        } else {
            $con_llave++;
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
  <title>Llave · Stadion</title>
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
<nav><a href="index.php">Inicio</a><a href="torneos.php" class="activo" aria-current="true">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.php">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">κλῆρος</p>
  <p class="etiqueta">Torneos / Llave</p>
  <h1>Llave</h1>
<?php if ($liga !== null && $liga->esLiga()) { ?>
  <p class="intro"><?php echo htmlspecialchars($liga->getNombre()); ?> es una liga: no tiene llave. Su orden lo da la tabla de posiciones.</p>
  <p><a href="torneo.php?id=<?php echo (int)$liga->getIdTorneo(); ?>#posiciones">Ver la tabla de <?php echo htmlspecialchars($liga->getNombre()); ?> <span aria-hidden="true">→</span></a></p>
<?php } elseif ($lista === null) { ?>
  <p class="intro">Los torneos no se pueden leer por ahora.</p>
<?php } else { ?>
  <p class="intro">La llave es la de la eliminación directa: el ganador de cada cruce pasa a la ronda siguiente. <?php echo ($con_llave === 0) ? 'Ninguna competencia en juego la usa: por ahora, todas son ligas.' : plural($con_llave, 'competencia la usa.', 'competencias la usan.'); ?></p>
<?php } ?>
</section>
<?php if (!empty($ligas)) { ?>
<section class="tarjeta">
  <h2>Las ligas, en su tabla</h2>
  <ul class="lista-apilada">
<?php   foreach ($ligas as $t) { ?>
    <li><a href="torneo.php?id=<?php echo (int)$t->getIdTorneo(); ?>#posiciones">Tabla de <?php echo htmlspecialchars($t->getNombre()); ?> <span aria-hidden="true">→</span></a><small><?php echo htmlspecialchars($t->getOrganizador()->getNombreCompletoVisible()); ?></small><?php if ($t->esDeMuestra()) { echo marcaMuestra(); } ?></li>
<?php   } ?>
  </ul>
</section>
<?php } ?>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Formatos</span>
  <p>Liga: todos contra todos, con tabla. La eliminación directa y el sistema suizo, con su llave y sus rondas, están en preparación.</p>
</div>
</aside>
<?php piePagina(true); ?>
</body>
</html>
