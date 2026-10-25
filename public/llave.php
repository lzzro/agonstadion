<?php
require __DIR__ . '/../apps/config/pagina.php';
require_once $carpeta_app . '/models/TorneoRepositorio.php';
require_once $carpeta_app . '/models/ParticipanteRepositorio.php';
require_once $carpeta_app . '/models/FixtureRepositorio.php';
require_once $carpeta_app . '/ligas.php';

# =====================================================================
# Llave - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La llave es la de la eliminacion directa (fase 3 del motor de
# torneos). Sin numero, la pagina lista las eliminaciones directas
# publicas, cada una con el enlace a su llave, y las ligas y suizos con
# el de su tabla. llave.php?id=N de una eliminacion directa dibuja su
# llave, leida de la base (llaveHtml, en apps/ligas.php: las rondas que
# faltan van "Por definir"); de una liga o un suizo, dice que no tiene
# llave y lleva a su tabla, que es lo que los ordena.
#
# Marca Torneos en el menu, con aria-current="true": es el detalle de un
# torneo, igual que torneo.php.
# =====================================================================

$lista = null;
$liga  = null;
$rondas_llave = array();
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
        if ($liga !== null && $liga->esEliminacion()) {
            $todos = (new ParticipanteRepositorio($conexion))->listarDeTorneo($liga->getIdTorneo(), true);
            $rondas_llave = ($todos === null) ? array() : (new FixtureRepositorio($conexion))->fechasDe($liga->getIdTorneo(), $todos);
            if ($rondas_llave === null) { $rondas_llave = array(); }
        }
    }
    $conexion->close();
}

# Los torneos publicos: los de eliminacion directa (con llave) y los
# demas (con tabla).
$con_llave = array();
$ligas = array();
if (is_array($lista)) {
    foreach ($lista as $fila) {
        if ($fila['torneo']->esEliminacion()) {
            $con_llave[] = $fila['torneo'];
        } else {
            $ligas[] = $fila['torneo'];
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
  <title><?php echo ($liga !== null && $liga->esEliminacion()) ? 'Llave · ' . htmlspecialchars($liga->getNombre()) : 'Llave'; ?> · Stadion</title>
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
<?php if ($liga !== null && $liga->esEliminacion()) { ?>
  <p class="intro"><?php echo htmlspecialchars($liga->getNombre()); ?> · <?php echo participantesDe($liga, count(array_filter($todos, function ($p) { return $p->estaEnCompetencia(); }))); ?>. El ganador de cada cruce pasa a la ronda siguiente.</p>
  <p><a href="torneo.php?id=<?php echo (int)$liga->getIdTorneo(); ?>">Ver la página de <?php echo htmlspecialchars($liga->getNombre()); ?> <span aria-hidden="true">→</span></a></p>
<?php } elseif ($liga !== null) { ?>
  <p class="intro"><?php echo htmlspecialchars($liga->getNombre()); ?> <?php echo $liga->esSuizo() ? 'se juega por sistema suizo' : 'es una liga'; ?>: no tiene llave. Su orden lo da la tabla de posiciones.</p>
  <p><a href="torneo.php?id=<?php echo (int)$liga->getIdTorneo(); ?>#posiciones">Ver la tabla de <?php echo htmlspecialchars($liga->getNombre()); ?> <span aria-hidden="true">→</span></a></p>
<?php } elseif ($lista === null) { ?>
  <p class="intro">Los torneos no se pueden leer por ahora.</p>
<?php } else { ?>
  <p class="intro">La llave es la de la eliminación directa: el ganador de cada cruce pasa a la ronda siguiente. <?php echo empty($con_llave) ? 'Ninguna competencia en juego la usa todavía.' : plural(count($con_llave), 'competencia la usa.', 'competencias la usan.'); ?></p>
<?php } ?>
</section>
<?php if ($liga !== null && $liga->esEliminacion()) { ?>
<section class="tarjeta">
  <h2>Llave</h2>
<?php   if (empty($rondas_llave)) { ?>
  <p>La llave se arma al cerrar la inscripción.</p>
<?php   } else {
          $config = $liga->getConfiguracion(); ?>
  <?php echo llaveHtml($rondas_llave, ($config !== null && $config->getRondasPrevistas() !== null) ? $config->getRondasPrevistas() : count($rondas_llave)); ?>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em">El que pasa de cada cruce va en olivo · un pase libre pasa sin jugar</span>
<?php   } ?>
</section>
<?php } ?>
<?php if ($liga === null && !empty($con_llave)) { ?>
<section class="tarjeta" id="llaves">
  <h2>Las llaves</h2>
  <ul class="lista-apilada">
<?php   foreach ($con_llave as $t) { ?>
    <li><a href="llave.php?id=<?php echo (int)$t->getIdTorneo(); ?>">Llave de <?php echo htmlspecialchars($t->getNombre()); ?> <span aria-hidden="true">→</span></a><small><?php echo htmlspecialchars($t->getOrganizador()->getNombreCompletoVisible()); ?></small><?php if ($t->esDeMuestra()) { echo marcaMuestra(); } ?></li>
<?php   } ?>
  </ul>
</section>
<?php } ?>
<?php if ($liga === null && !empty($ligas)) { ?>
<section class="tarjeta" id="tablas">
  <h2>Las ligas y los suizos, en su tabla</h2>
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
  <p>Liga: todos contra todos, con tabla. Eliminación directa: una llave, y quien pierde queda afuera. Suizo: rondas fijas entre rivales de puntaje parecido, con tabla.</p>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
