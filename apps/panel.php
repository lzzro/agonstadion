<?php
# Vista: panel del organizador

if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
if (!isset($errores_equipo)) { $errores_equipo = array(); }
if (!isset($valor_equipo)) { $valor_equipo = array(); }
if (!isset($campeon_de)) { $campeon_de = array(); }

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/pie.php';
require_once __DIR__ . '/ligas.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/recursos.php';

$cantidad_ligas = is_array($ligas) ? count($ligas) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title><?php if (!empty($errores)) { echo 'Aviso · '; } ?>Panel del organizador · Stadion</title>
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
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $organizador); ?>
</header>
<nav><a href="<?php echo $ruta_publica; ?>/index.php">Inicio</a><a href="<?php echo $ruta_publica; ?>/torneos.php">Torneos</a><a href="<?php echo $ruta_publica; ?>/calendario.php">Calendario</a><a href="<?php echo $ruta_publica; ?>/torneo.php#posiciones">Posiciones</a><a href="<?php echo $ruta_publica; ?>/panel.php" class="activo" aria-current="page">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἀγωνοθέτης</p>
  <h1>Panel del organizador</h1>
  <p class="intro"><?php echo htmlspecialchars($organizador->getNombreCompletoVisible()); ?> · <?php echo plural($cantidad_ligas, 'torneo a cargo', 'torneos a cargo'); ?></p>
  <div class="fila"><a class="btn btn-primario" href="<?php echo htmlspecialchars($ruta_crear); ?>">Nuevo torneo</a><a class="btn" href="<?php echo htmlspecialchars($ruta_reportes); ?>">Reportes</a></div>
</section>

<?php if (!empty($errores)) { ?>
<div role="alert">
<ul class="avisos">
<?php   foreach ($errores as $error) { ?>
  <li><?php echo htmlspecialchars($error); ?></li>
<?php   } ?>
</ul>
</div>
<?php } ?>
<?php if ($mensaje !== '') { ?>
<p class="intro" role="status"><?php echo htmlspecialchars($mensaje); ?></p>
<?php } ?>

<section class="tarjeta">
  <h2>Mis torneos</h2>
<?php if ($ligas === null) { ?>
  <p>La lista de torneos no se puede leer por ahora.</p>
<?php } elseif (empty($ligas)) { ?>
  <p>Ningún torneo a cargo de esta cuenta. Todo torneo empieza con un nombre y un cupo.</p>
  <p><a href="<?php echo htmlspecialchars($ruta_crear); ?>">Nuevo torneo <span aria-hidden="true">→</span></a></p>
<?php } else { ?>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Mis torneos">
  <table>
    <thead><tr><th>Torneo</th><th>Estado</th><th>Inscriptos</th><th>Pedidos</th><th><span class="visualmente-oculto">Acción</span></th></tr></thead>
    <tbody>
<?php   foreach ($ligas as $fila) {
          $t = $fila['torneo']; ?>
      <tr><td><?php echo htmlspecialchars($t->getNombre()); ?></td><td><?php echo chipLigaHtml($t, $fila['en_vivo']); ?></td><td class="num"><?php echo $fila['inscriptos']; ?> de <?php echo $t->getMaxParticipantes(); ?></td><td class="num"><?php echo $fila['pedidos']; ?></td><td><a href="#liga-<?php echo (int)$t->getIdTorneo(); ?>">Administrar<span class="visualmente-oculto"> <?php echo htmlspecialchars($t->getNombre()); ?></span> <span aria-hidden="true">→</span></a></td></tr>
<?php   } ?>
    </tbody>
  </table>
  </div>
<?php } ?>
</section>

<?php if (is_array($ligas)) {
        foreach ($ligas as $fila) {
          $t   = $fila['torneo'];
          $id  = (int)$t->getIdTorneo();
          $nom = htmlspecialchars($t->getNombre());
          $config   = $t->getConfiguracion();
          $equipos  = isset($equipos_de[$id]) ? $equipos_de[$id] : null;
          $pendientes = isset($pendientes_de[$id]) ? $pendientes_de[$id] : array();
          $lleno    = ($fila['inscriptos'] >= $t->getMaxParticipantes());
          $de_equipos = $t->esPorEquipos();
          $quienes  = nombreParticipantes($t);
          $w = palabrasDe($t); ?>
<section class="tarjeta" id="liga-<?php echo $id; ?>">
  <div class="fila" style="justify-content:space-between"><h2><?php echo $nom; ?></h2><?php echo chipLigaHtml($t, $fila['en_vivo']); ?></div>
  <p><?php echo htmlspecialchars($t->getDisciplina()->getNombre()); ?> · <?php echo htmlspecialchars(formatoTexto($t)); ?> · <?php echo $fila['inscriptos']; ?> de <?php echo $t->getMaxParticipantes() . ' ' . $quienes; ?> · <?php echo empty($t->getFechaInicio()) ? 'inicio a definir' : 'desde el ' . htmlspecialchars(fechaTexto($t->getFechaInicio(), true)); ?></p>

  <h3><?php echo $de_equipos ? 'Equipos anotados' : 'Jugadores anotados'; ?></h3>
<?php     if ($equipos === null) { ?>
  <p>La lista de <?php echo $quienes; ?> no se puede leer por ahora.</p>
<?php     } elseif (empty($equipos)) { ?>
  <p><?php echo $de_equipos ? 'Sin equipos anotados.' : 'Sin jugadores anotados.'; ?></p>
<?php     } else { ?>
  <p><?php $nombres = array();
           foreach ($equipos as $p) { $nombres[] = htmlspecialchars($p->getNombreVisible()); }
           echo implode(' · ', $nombres); ?></p>
<?php     } ?>

<?php     if ($t->tieneInscripcionAbierta()) { ?>
<?php       if (!$de_equipos) { ?>
  <p><small>Con jugadores, cada uno se anota con su cuenta, desde la página <?php echo ($t->esLiga() ? 'de la liga' : 'del torneo'); ?><?php echo $lleno ? '. El cupo está completo.' : ', hasta completar el cupo.'; ?></small></p>
<?php       } elseif ($lleno) { ?>
  <p><span class="enlace-apagado" aria-disabled="true">Anotar equipo</span> <small>El cupo está completo.</small></p>
<?php       } else { ?>
  <form class="anotar-equipo" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="agregar">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <div class="campo">
      <label>Nombre del equipo<input type="text" name="equipo" value="<?php echo isset($valor_equipo[$id]) ? htmlspecialchars($valor_equipo[$id]) : ''; ?>" required minlength="2" maxlength="40" aria-describedby="ayuda-equipo-<?php echo $id; ?><?php if (isset($errores_equipo[$id])) { echo ' error-equipo-' . $id; } ?>"<?php if (isset($errores_equipo[$id])) { echo ' aria-invalid="true"'; } ?>></label>
      <p class="ayuda-campo" id="ayuda-equipo-<?php echo $id; ?>">Entre 2 y 40 caracteres. Un equipo con capitán entra con su propio pedido.</p>
<?php         if (isset($errores_equipo[$id])) { ?>
      <p class="error-campo" id="error-equipo-<?php echo $id; ?>"><?php echo htmlspecialchars($errores_equipo[$id]); ?></p>
<?php         } ?>
    </div>
    <button class="btn" type="submit">Anotar equipo<span class="visualmente-oculto"> en <?php echo $nom; ?></span></button>
  </form>
<?php       } ?>

<?php       if ($de_equipos) { ?>
  <h3>Pedidos de lugar</h3>
<?php       if (empty($pendientes)) { ?>
  <p>Ningún pedido en revisión.</p>
<?php       } else { ?>
  <div class="pedidos">
<?php         foreach ($pendientes as $pedido) {
                $equipo = htmlspecialchars($pedido->getEquipo()->getNombre()); ?>
    <div class="pedido">
      <span class="pedido-texto"><strong><?php echo $equipo; ?></strong><span class="pedido-dato">Capitán: <?php echo htmlspecialchars($pedido->getUsuario()->getNombreCompletoVisible()); ?> · <span class="fecha"><?php echo htmlspecialchars(fechaTexto($pedido->getFechaPedido())) . ' · ' . horaTexto($pedido->getFechaPedido()); ?></span></span></span>
      <span class="resolver-pedido">
<?php           if ($lleno) { ?>
        <span class="enlace-apagado" aria-disabled="true">Aceptar<span class="visualmente-oculto"> el pedido de <?php echo $equipo; ?></span></span>
<?php           } else { ?>
        <form action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
          <input type="hidden" name="accion" value="aceptar">
          <input type="hidden" name="id_pedido" value="<?php echo (int)$pedido->getIdPedidoInscripcion(); ?>">
          <?php echo campoCsrf(); ?>
          <button class="btn btn-primario" type="submit" aria-label="Aceptar el pedido de <?php echo $equipo; ?>">Aceptar</button>
        </form>
<?php           } ?>
        <form action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
          <input type="hidden" name="accion" value="rechazar">
          <input type="hidden" name="id_pedido" value="<?php echo (int)$pedido->getIdPedidoInscripcion(); ?>">
          <?php echo campoCsrf(); ?>
          <button class="btn" type="submit" aria-label="Rechazar el pedido de <?php echo $equipo; ?>">Rechazar</button>
        </form>
      </span>
    </div>
<?php         } ?>
  </div>
<?php         if ($lleno) { ?>
  <p><small>Con el cupo completo, un pedido no se acepta: queda para rechazar o para cuando se libere un lugar.</small></p>
<?php         } ?>
<?php       } ?>

<?php       } ?>

  <h3>Inscripción y <?php echo $t->esLiga() ? 'fixture' : str_replace(array('la ', 'el '), '', $w['calendario']); ?></h3>
<?php       if ($fila['inscriptos'] >= 4) { ?>
  <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="cerrar">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <button class="btn" type="submit">Cerrar la inscripción<span class="visualmente-oculto"> de <?php echo $nom; ?></span></button>
  </form>
  <p><small><?php echo $de_equipos ? 'Con la inscripción cerrada, los pedidos pendientes quedan sin lugar y ' . $w['calendario'] . ' se puede armar.'
                                    : 'Con la inscripción cerrada, ' . $w['calendario'] . ' se puede armar.'; ?></small></p>
<?php       } else { ?>
  <p><span class="enlace-apagado" aria-disabled="true">Cerrar la inscripción</span> <small>Con menos de 4 <?php echo $quienes; ?> la inscripción sigue abierta.</small></p>
<?php       } ?>
  <p><span class="enlace-apagado" aria-disabled="true">Armar <?php echo $w['calendario']; ?></span> <small><?php echo mayuscula($w['calendario']); ?> se arma con la inscripción cerrada.</small></p>

<?php     } elseif ($t->estaEnCurso()) { ?>
  <h3><?php echo $w['Calendario']; ?></h3>
<?php       if ($fila['fechas'] === 0) { ?>
  <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="fixture">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <button class="btn btn-primario" type="submit">Armar <?php echo $w['calendario']; ?><span class="visualmente-oculto"> de <?php echo $nom; ?></span></button>
  </form>
<?php         if ($t->esLiga()) { ?>
  <p><small>Todos contra todos, <?php echo vueltasTexto($config); ?>: <?php echo plural($config === null ? 0 : $config->rondasDeLiga($fila['inscriptos']), 'fecha', 'fechas'); ?> con <?php echo participantesDe($t, $fila['inscriptos']); ?>.</small></p>
<?php         } elseif ($t->esEliminacion()) { ?>
  <p><small>Una llave para <?php echo participantesDe($t, $fila['inscriptos']); ?>: <?php echo plural(Llave::cantidadRondas($fila['inscriptos']), 'ronda', 'rondas'); ?>, con <?php echo plural(Llave::tamano($fila['inscriptos']) - $fila['inscriptos'], 'pase libre', 'pases libres'); ?> en la primera. Cada ronda se arma al cerrar la anterior.</small></p>
<?php         } else { ?>
  <p><small>Suizo con <?php echo participantesDe($t, $fila['inscriptos']); ?>. Cada ronda se arma al cerrar la anterior, según la tabla.</small></p>
<?php         } ?>
<?php       } else { ?>
  <p><?php echo ($fila['actual'] === null) ? 'Todas las ' . $w['rondas'] . ' cerradas.'
                : mayuscula($w['ronda']) . ' ' . $fila['actual'] . ' de ' . ($t->esLiga() || $config === null || $config->getRondasPrevistas() === null ? $fila['fechas'] : $config->getRondasPrevistas()) . ' en juego.'; ?>
     Resultados, días y lugares, <?php echo $w['ronda'] . ' por ' . $w['ronda']; ?>.</p>
  <p><a class="btn btn-primario" href="<?php echo htmlspecialchars($ruta_partidos); ?>?id=<?php echo $id; ?>"><?php echo $w['pagina']; ?><span class="visualmente-oculto"> de <?php echo $nom; ?></span></a></p>
  <p><?php echo plural($fila['fechas'], $w['ronda'], $w['rondas']); ?> armadas<?php echo ($fila['resultados'] > 0) ? ' · ' . plural($fila['resultados'], 'resultado cargado', 'resultados cargados') : ''; ?>. <a href="<?php echo $ruta_publica; ?>/torneo.php?id=<?php echo $id; ?>#calendario">Ver el fixture<span class="visualmente-oculto"> de <?php echo $nom; ?></span> <span aria-hidden="true">→</span></a></p>
<?php         if ($fila['resultados'] === 0) { ?>
  <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="rehacer">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <label class="casilla"><input type="checkbox" name="confirmo" value="1" required>Borrar <?php echo $w['calendario']; ?> de <?php echo $nom; ?> y armar <?php echo $t->esLiga() ? 'uno nuevo' : 'de nuevo'; ?></label>
    <button class="btn" type="submit">Rehacer <?php echo $w['calendario']; ?><span class="visualmente-oculto"> de <?php echo $nom; ?></span></button>
  </form>
<?php         } else { ?>
  <p><span class="enlace-apagado" aria-disabled="true">Rehacer <?php echo $w['calendario']; ?></span> <small>Con resultados cargados, <?php echo $w['calendario']; ?> no se rehace.</small></p>
<?php         } ?>
<?php       } ?>
<?php     } elseif ($t->getEstado() === 'finalizado') {
            $campeon = isset($campeon_de[$id]) ? $campeon_de[$id] : null; ?>
  <h3><?php echo $t->esLiga() ? 'Liga terminada' : 'Torneo terminado'; ?></h3>
<?php       if ($campeon !== null) { ?>
  <div class="fila"><?php echo chipVencedor(); ?><strong><?php echo htmlspecialchars($campeon->getNombreVisible()); ?></strong></div>
<?php       } ?>
  <p><?php echo plural($fila['fechas'], $w['ronda'] . ' jugada', $w['rondas'] . ' jugadas'); ?><?php if (!empty($t->getFechaFin())) { echo ' · cierre el ' . htmlspecialchars(fechaTexto($t->getFechaFin(), true)); } ?>: queda como historia. <a href="<?php echo htmlspecialchars($ruta_partidos); ?>?id=<?php echo $id; ?>">Ver las fechas<span class="visualmente-oculto"> de <?php echo $nom; ?></span> <span aria-hidden="true">→</span></a></p>
<?php     } else { ?>
  <p><?php echo $w['El']; ?> queda como historia.</p>
<?php     } ?>
  <p><a href="<?php echo $ruta_publica; ?>/torneo.php?id=<?php echo $id; ?>">Ver la página de <?php echo $nom; ?> <span aria-hidden="true">→</span></a></p>
<?php     if ($t->tieneInscripcionAbierta() || $t->estaEnCurso()) { ?>
  <form class="accion-liga cancelar-liga" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="cancelar">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <label class="casilla"><input type="checkbox" name="confirmo_cancelar" value="1" required>Cancelar <?php echo $nom; ?>: deja de verse en el sitio, y lo jugado queda guardado</label>
    <button class="btn" type="submit">Cancelar <?php echo $w['el']; ?><span class="visualmente-oculto"> <?php echo $nom; ?></span></button>
  </form>
<?php     } ?>
</section>
<?php   }
      } ?>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Cómo avanza un torneo</span>
  <ol class="pasos-liga">
    <li>Inscripción abierta, hasta el cupo: equipos anotados a mano o por pedido de su capitán; con jugadores, cada uno con su cuenta.</li>
    <li>Inscripción cerrada, con 4 o más.</li>
    <li>El calendario: en la liga, todos contra todos; en la eliminación directa, la llave; en el suizo, la primera ronda.</li>
    <li>Resultados, ronda por ronda (en la liga, fecha por fecha), y cada una cerrada para pasar a la siguiente.</li>
    <li>Con la última cerrada, el torneo termina: el primero de la tabla, o el ganador de la final, es el campeón.</li>
  </ol>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
