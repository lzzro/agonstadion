<?php
# =====================================================================
# Vista: panel del organizador
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La incluye panelController.php. Recibe:
#   $organizador     la cuenta de la sesion (Usuario, con el rol)
#   $ligas           TorneoRepositorio::listarDeOrganizador(), o null si
#                    no se pudo leer
#   $equipos_de      id de liga => participantes anotados
#   $pendientes_de   id de liga => pedidos de lugar pendientes
#   $mensaje, $errores           resultado de la ultima accion
#   $errores_equipo, $valor_equipo   el campo "equipo" de cada liga
#   $ruta_publica, $ruta_perfil, $ruta_crear, $ruta_panel   direcciones
#
# Una sola pagina que se recorre: arriba la lista de ligas, y debajo una
# tarjeta por liga (id="liga-N") con lo que se puede hacer en su estado:
#   inscripcion  anotar equipos, resolver pedidos, cerrar la inscripcion
#   en curso     armar el fixture, o rehacerlo si no tiene resultados
# Lo que el estado todavia no permite va como texto apagado, con la nota
# que dice por que (DESIGN.md, "Accion no disponible").
#
# Usa el menu compartido, con Organizadores marcado: es su destino.
# Todo formulario lleva el token (campoCsrf) y cada boton que se repite
# dice de que liga o de que equipo es.
# =====================================================================

if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
if (!isset($errores_equipo)) { $errores_equipo = array(); }
if (!isset($valor_equipo)) { $valor_equipo = array(); }

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
  <p class="intro"><?php echo htmlspecialchars($organizador->getNombreCompletoVisible()); ?> · <?php echo plural($cantidad_ligas, 'liga a cargo', 'ligas a cargo'); ?></p>
  <div class="fila"><a class="btn btn-primario" href="<?php echo htmlspecialchars($ruta_crear); ?>">Nueva liga</a></div>
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
  <h2>Mis ligas</h2>
<?php if ($ligas === null) { ?>
  <p>La lista de ligas no se puede leer por ahora.</p>
<?php } elseif (empty($ligas)) { ?>
  <p>Ninguna liga a cargo de esta cuenta. Toda liga empieza con un nombre y un cupo.</p>
  <p><a href="<?php echo htmlspecialchars($ruta_crear); ?>">Nueva liga <span aria-hidden="true">→</span></a></p>
<?php } else { ?>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Mis ligas">
  <table>
    <thead><tr><th>Liga</th><th>Estado</th><th>Equipos</th><th>Pedidos</th><th><span class="visualmente-oculto">Acción</span></th></tr></thead>
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
          $lleno    = ($fila['inscriptos'] >= $t->getMaxParticipantes()); ?>
<section class="tarjeta" id="liga-<?php echo $id; ?>">
  <div class="fila" style="justify-content:space-between"><h2><?php echo $nom; ?></h2><?php echo chipLigaHtml($t, $fila['en_vivo']); ?></div>
  <p><?php echo htmlspecialchars($t->getDisciplina()->getNombre()); ?> · <?php echo vueltasTexto($config); ?> · <?php echo $fila['inscriptos']; ?> de <?php echo $t->getMaxParticipantes(); ?> equipos · <?php echo empty($t->getFechaInicio()) ? 'inicio a definir' : 'desde el ' . htmlspecialchars(fechaTexto($t->getFechaInicio(), true)); ?></p>

  <h3>Equipos anotados</h3>
<?php     if ($equipos === null) { ?>
  <p>La lista de equipos no se puede leer por ahora.</p>
<?php     } elseif (empty($equipos)) { ?>
  <p>Sin equipos anotados.</p>
<?php     } else { ?>
  <p><?php $nombres = array();
           foreach ($equipos as $p) { $nombres[] = htmlspecialchars($p->getNombreVisible()); }
           echo implode(' · ', $nombres); ?></p>
<?php     } ?>

<?php     if ($t->tieneInscripcionAbierta()) { ?>
<?php       if ($lleno) { ?>
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

  <h3>Inscripción y fixture</h3>
<?php       if ($fila['inscriptos'] >= 4) { ?>
  <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="cerrar">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <button class="btn" type="submit">Cerrar la inscripción<span class="visualmente-oculto"> de <?php echo $nom; ?></span></button>
  </form>
  <p><small>Con la inscripción cerrada, los pedidos pendientes quedan sin lugar y el fixture se puede armar.</small></p>
<?php       } else { ?>
  <p><span class="enlace-apagado" aria-disabled="true">Cerrar la inscripción</span> <small>Con menos de 4 equipos la inscripción sigue abierta.</small></p>
<?php       } ?>
  <p><span class="enlace-apagado" aria-disabled="true">Armar el fixture</span> <small>El fixture se arma con la inscripción cerrada.</small></p>

<?php     } elseif ($t->estaEnCurso()) { ?>
  <h3>Fixture</h3>
<?php       if ($fila['fechas'] === 0) { ?>
  <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="fixture">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <button class="btn btn-primario" type="submit">Armar el fixture<span class="visualmente-oculto"> de <?php echo $nom; ?></span></button>
  </form>
  <p><small>Todos contra todos, <?php echo vueltasTexto($config); ?>: <?php echo plural($config === null ? 0 : $config->rondasDeLiga($fila['inscriptos']), 'fecha', 'fechas'); ?> con <?php echo plural($fila['inscriptos'], 'equipo', 'equipos'); ?>.</small></p>
<?php       } else { ?>
  <p><?php echo plural($fila['fechas'], 'fecha', 'fechas'); ?> armadas<?php echo ($fila['resultados'] > 0) ? ' · ' . plural($fila['resultados'], 'resultado cargado', 'resultados cargados') : ''; ?>. <a href="<?php echo $ruta_publica; ?>/torneo.php?id=<?php echo $id; ?>#calendario">Ver el fixture<span class="visualmente-oculto"> de <?php echo $nom; ?></span> <span aria-hidden="true">→</span></a></p>
<?php         if ($fila['resultados'] === 0) { ?>
  <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_panel); ?>" method="post">
    <input type="hidden" name="accion" value="rehacer">
    <input type="hidden" name="id_torneo" value="<?php echo $id; ?>">
    <?php echo campoCsrf(); ?>
    <label class="casilla"><input type="checkbox" name="confirmo" value="1" required>Borrar el fixture de <?php echo $nom; ?> y armar uno nuevo</label>
    <button class="btn" type="submit">Rehacer el fixture<span class="visualmente-oculto"> de <?php echo $nom; ?></span></button>
  </form>
<?php         } else { ?>
  <p><span class="enlace-apagado" aria-disabled="true">Rehacer el fixture</span> <small>Con resultados cargados, el fixture no se rehace.</small></p>
<?php         } ?>
<?php       } ?>
<?php     } else { ?>
  <p>Liga <?php echo ($t->getEstado() === 'finalizado') ? 'finalizada' : 'cerrada'; ?>: queda como historia.</p>
<?php     } ?>
  <p><a href="<?php echo $ruta_publica; ?>/torneo.php?id=<?php echo $id; ?>">Ver la página de <?php echo $nom; ?> <span aria-hidden="true">→</span></a></p>
</section>
<?php   }
      } ?>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Cómo avanza una liga</span>
  <ol class="pasos-liga">
    <li>Inscripción abierta: equipos anotados a mano o por pedido de su capitán, hasta el cupo.</li>
    <li>Inscripción cerrada, con 4 equipos o más.</li>
    <li>Fixture: todos contra todos, fecha por fecha.</li>
  </ol>
</div>
</aside>
<?php piePagina(true); ?>
</body>
</html>
