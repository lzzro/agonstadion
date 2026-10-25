<?php
# =====================================================================
# Vista: fechas y resultados de una liga
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La incluye partidosController.php. Recibe:
#   $organizador   la cuenta de la sesion (Usuario, con el rol)
#   $torneo        la liga, o null si no es de esta cuenta
#   $sin_liga      por que no hay nada que mostrar ('' si hay)
#   $rondas        las fechas, con sus partidos y resultados
#   $en_juego      la fecha en juego (null con todas cerradas)
#   $abierta       la fecha que se muestra
#   $tabla         la tabla ordenada, solo con la competencia terminada
#   $campeon       su campeon (Participante), o null
#   $mensaje, $errores, $valores_partido   resultado de la ultima accion
#   $ruta_publica, $ruta_perfil, $ruta_panel, $ruta_partidos
#
# Una fecha por vez: arriba la lista de fechas (enlaces, la abierta
# marcada) y debajo la fecha abierta, con un bloque por partido
# (id="partido-N"). Lo que se puede hacer depende de la fecha:
#   en juego   resultado (o walkover), dia y lugar, en vivo; y al pie,
#              cerrar la fecha, con todos los resultados cargados
#   por jugar  dia y lugar; los resultados esperan su turno
#   cerrada    nada: sus partidos quedan como estan
# Lo que todavia no se puede va como texto apagado con la nota que dice
# por que (DESIGN.md, "Accion no disponible").
#
# Usa el menu compartido, con Organizadores marcado ("true"): la pagina
# depende del panel, como "Nueva liga". Todo formulario lleva el token
# y cada boton que se repite dice de que partido es.
# =====================================================================

if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
if (!isset($valores_partido)) { $valores_partido = array(); }

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/pie.php';
require_once __DIR__ . '/ligas.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/recursos.php';

$config = ($torneo === null) ? null : $torneo->getConfiguracion();
$nom    = ($torneo === null) ? '' : htmlspecialchars($torneo->getNombre());
$terminada = ($torneo !== null && $torneo->getEstado() === 'finalizado');
if (!isset($campeon)) { $campeon = null; }
# Las palabras del formato: fecha o ronda, liga o torneo (ver ligas.php).
$w = ($torneo === null) ? array('pagina' => 'Fechas y resultados', 'ronda' => 'fecha', 'rondas' => 'fechas', 'el' => 'la liga')
                        : palabrasDe($torneo);

# Como se juega cada partido, en pocas palabras.
$formato = 'Partido único';
if ($config !== null && $config->esSerie()) {
    $formato = 'Al mejor de ' . $config->getMejorDe();
} elseif ($config !== null && !$config->admiteEmpate()) {
    $formato = 'Partido único, sin empates';
}
# La ayuda del marcador, segun la liga.
$ayuda_marcador = 'De 0 a ' . ConfiguracionTorneo::MARCADOR_MAXIMO . ' de cada lado.';
if ($config !== null && $config->esSerie()) {
    $ayuda_marcador = 'Al mejor de ' . $config->getMejorDe() . ': ' . $config->marcadoresDeSerie() . ', para cualquiera de los dos.';
} elseif ($config !== null && !$config->admiteEmpate()) {
    $ayuda_marcador .= ' Sin empates.';
}
$maximo = ($config !== null && $config->esSerie()) ? $config->ganaSerieCon() : ConfiguracionTorneo::MARCADOR_MAXIMO;

# Donde esta la liga.
$avance = '';
if ($torneo !== null && !empty($rondas)) {
    $total = ($torneo->esLiga() || $config === null || $config->getRondasPrevistas() === null)
           ? count($rondas) : $config->getRondasPrevistas();
    if ($en_juego === null) {
        $avance = plural(count($rondas), $w['ronda'] . ' jugada', $w['rondas'] . ' jugadas');
    } else {
        $avance = mayuscula($w['ronda']) . ' ' . $en_juego->getNumero() . ' de ' . $total . ' en juego';
    }
}

# Lo que se puede hacer en la fecha abierta.
$es_en_juego = ($abierta !== null && $en_juego !== null && $abierta === $en_juego);
$es_cerrada  = ($abierta !== null && $abierta->estaCerrada());

# El estado de una fecha: array(clase del chip, texto).
function chipFecha($ronda, $en_juego)
{
    if ($ronda->estaCerrada()) {
        return array('estado-cerrado', 'Cerrada');
    }
    if ($ronda === $en_juego) {
        return array('estado-en-juego', 'En juego');
    }
    return array('estado-inscripcion', 'Por jugar');
}

# El dia, la hora y el lugar de un partido, en palabras.
function cuandoYDonde(Enfrentamiento $partido)
{
    $texto = ($partido->getFechaHora() === null) ? 'Día y hora a confirmar'
           : mayuscula(fechaConDia($partido->getFechaHora())) . ', ' . horaTexto($partido->getFechaHora());
    if (!empty($partido->getLugar())) {
        $texto .= ' · ' . $partido->getLugar();
    }
    return $texto;
}

# El valor del campo datetime-local: AAAA-MM-DDTHH:MM.
function valorFechaHora($fecha_hora)
{
    return ($fecha_hora === null) ? '' : substr($fecha_hora, 0, 10) . 'T' . substr($fecha_hora, 11, 5);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title><?php if (!empty($errores) || $sin_liga !== '') { echo 'Aviso · '; } ?><?php echo ($torneo === null) ? 'Fechas y resultados' : $w['pagina'] . ' · ' . $nom; ?> · Stadion</title>
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
<nav><a href="<?php echo $ruta_publica; ?>/index.php">Inicio</a><a href="<?php echo $ruta_publica; ?>/torneos.php">Torneos</a><a href="<?php echo $ruta_publica; ?>/calendario.php">Calendario</a><a href="<?php echo $ruta_publica; ?>/torneo.php#posiciones">Posiciones</a><a href="<?php echo $ruta_publica; ?>/panel.php" class="activo" aria-current="true">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="etiqueta">Panel / <?php echo $w['pagina']; ?></p>
  <h1><?php echo ($torneo === null) ? 'Fechas y resultados' : $nom; ?></h1>
<?php if ($torneo !== null) { ?>
  <p class="intro"><?php echo htmlspecialchars($torneo->getDisciplina()->getNombre()); ?> · <?php echo htmlspecialchars(formatoTexto($torneo)); ?> · <?php echo htmlspecialchars($formato); ?><?php if ($avance !== '') { echo ' · ' . htmlspecialchars($avance); } ?></p>
<?php } ?>
  <div class="fila"><a class="btn" href="<?php echo htmlspecialchars($ruta_panel); ?><?php if ($torneo !== null) { echo '#liga-' . (int)$torneo->getIdTorneo(); } ?>"><span aria-hidden="true">←</span> Volver al panel</a><?php if ($torneo !== null) { ?><a href="<?php echo $ruta_publica; ?>/torneo.php?id=<?php echo (int)$torneo->getIdTorneo(); ?>">Ver la página de la liga <span aria-hidden="true">→</span></a><?php } ?></div>
</section>

<?php if (!empty($errores) || $sin_liga !== '') { ?>
<div role="alert">
<ul class="avisos">
<?php   foreach ($errores as $error) { ?>
  <li><?php echo htmlspecialchars($error); ?></li>
<?php   } ?>
<?php   if ($sin_liga !== '') { ?>
  <li><?php echo htmlspecialchars($sin_liga); ?></li>
<?php   } ?>
</ul>
</div>
<?php } ?>
<?php if ($mensaje !== '') { ?>
<p class="intro" role="status"><?php echo htmlspecialchars($mensaje); ?></p>
<?php } ?>

<?php if ($torneo !== null && $terminada && $campeon !== null) {
        # En la liga y el suizo, su fila de la tabla; en la llave, la final.
        $fila_campeon = null;
        foreach ($tabla as $fila) {
            if ((int)$fila->getParticipante()->getIdParticipante() === (int)$campeon->getIdParticipante()) { $fila_campeon = $fila; }
        }
        $cierre = empty($torneo->getFechaFin()) ? '' : ' · cierre el ' . fechaTexto($torneo->getFechaFin(), true); ?>
<section class="tarjeta tarjeta-olivo">
  <div class="fila" style="justify-content:space-between"><span class="etiqueta"><?php echo $torneo->esLiga() ? 'Liga terminada' : 'Torneo terminado'; ?></span><?php echo chipVencedor(); ?></div>
  <h2><?php echo htmlspecialchars($campeon->getNombreVisible()); ?></h2>
<?php   if ($torneo->esEliminacion()) { ?>
  <p>Ganador de la final<?php echo htmlspecialchars($cierre); ?></p>
<?php   } elseif ($fila_campeon !== null) { ?>
  <p><?php echo plural($fila_campeon->getPuntos($config), 'punto', 'puntos'); ?> en <?php echo plural($fila_campeon->getPartidosJugados(), 'partido', 'partidos'); ?> · <?php echo conSigno($fila_campeon->getDiferencia()); ?> de diferencia<?php echo htmlspecialchars($cierre); ?></p>
<?php   } ?>
</section>
<?php } ?>

<?php if ($torneo !== null && !empty($rondas) && $abierta !== null) {
        $chip = chipFecha($abierta, $en_juego); ?>
<nav class="fechas-liga" aria-label="<?php echo mayuscula($w['rondas']); ?> de <?php echo $nom; ?>">
<?php   foreach ($rondas as $ronda) {
          $marca = ($ronda === $abierta); ?>
  <a class="chip<?php if ($marca) { echo ' activo'; } ?>" href="<?php echo htmlspecialchars($ruta_partidos); ?>?id=<?php echo (int)$torneo->getIdTorneo(); ?>&amp;fecha=<?php echo $ronda->getNumero(); ?>#fecha"<?php if ($marca) { echo ' aria-current="page"'; } ?>><?php echo htmlspecialchars($ronda->getNombreVisible()); ?><?php if ($ronda === $en_juego) { ?><span class="visualmente-oculto"> · en juego</span><?php } elseif ($ronda->estaCerrada()) { ?><span class="visualmente-oculto"> · cerrada</span><?php } ?></a>
<?php   } ?>
</nav>

<section class="tarjeta" id="fecha">
  <div class="fila" style="justify-content:space-between"><h2><?php echo htmlspecialchars($abierta->getNombreVisible()); ?></h2><span class="estado <?php echo $chip[0]; ?>"><?php echo $chip[1]; ?></span></div>
<?php   if ($es_cerrada) { ?>
  <p><?php echo mayuscula($w['ronda']); ?> cerrada: sus resultados quedan como están.</p>
<?php   } elseif (!$es_en_juego) { ?>
  <p>Los resultados se cargan cuando llega su turno, con la <?php echo htmlspecialchars($en_juego->getNombreVisible()); ?> cerrada. El día y el lugar ya se pueden programar.</p>
<?php   } ?>

<?php   foreach ($abierta->getEnfrentamientos() as $partido) {
          $id_p   = (int)$partido->getIdEnfrentamiento();
          $local  = htmlspecialchars($partido->getLocal()->getNombreVisible());
          $res    = $partido->getResultado();
          $jugado = ($res !== null && $partido->estaJugado());
          $valor  = isset($valores_partido[$id_p]) ? $valores_partido[$id_p] : array();
          if ($partido->esLibre()) { ?>
  <div class="partido-carga" id="partido-<?php echo $id_p; ?>">
    <h3><?php echo $local; ?></h3>
    <p class="pedido-dato"><?php echo $torneo->esEliminacion() ? 'Pase libre: pasa a la ronda siguiente sin jugar.'
                                    : ($torneo->esSuizo() ? 'Queda libre en esta ronda: suma como partido ganado.' : 'Queda libre en esta fecha: no juega.'); ?></p>
  </div>
<?php       continue;
          }
          $visit  = htmlspecialchars($partido->getVisitante()->getNombreVisible());
          $cruce  = $local . ' vs ' . $visit;
          if ($jugado) {
              $estado_texto = 'Jugado · ' . marcadorTexto($res);
              if ($res->esWalkover() && $res->getGanador() !== null) {
                  $estado_texto .= ' a favor de ' . $res->getGanador()->getNombreVisible();
              }
          } elseif ($partido->estaEnVivo()) {
              $estado_texto = '';
          } else {
              $estado_texto = 'Por jugar';
          } ?>
  <div class="partido-carga" id="partido-<?php echo $id_p; ?>">
    <div class="fila" style="justify-content:space-between"><h3><?php echo $local; ?> <span class="vs">vs</span> <?php echo $visit; ?></h3><?php if ($partido->estaEnVivo()) { ?><span class="estado estado-en-vivo">En vivo</span><?php } ?></div>
    <p class="pedido-dato"><?php if ($estado_texto !== '') { echo '<span class="marcador-carga">' . htmlspecialchars($estado_texto) . '</span> · '; } ?><?php echo htmlspecialchars(cuandoYDonde($partido)); ?></p>
<?php       if ($es_en_juego) {
              # El formulario del resultado: lo que se escribio, si hubo
              # un error; si no, lo guardado.
              $desenlace = isset($valor['desenlace']) ? $valor['desenlace'] : 'marcador';
              if (!isset($valor['desenlace']) && $jugado && $res->esWalkover() && $res->getGanador() !== null) {
                  $desenlace = ($res->getGanador() === $partido->getLocal()) ? 'wo-local' : 'wo-visitante';
              }
              $v_local = isset($valor['local']) ? $valor['local'] : (($jugado && !$res->esWalkover()) ? (string)$res->getPuntajeLocal() : '');
              $v_visit = isset($valor['visitante']) ? $valor['visitante'] : (($jugado && !$res->esWalkover()) ? (string)$res->getPuntajeVisitante() : ''); ?>
    <form class="cargar-resultado" action="<?php echo htmlspecialchars($ruta_partidos); ?>" method="post">
      <input type="hidden" name="accion" value="resultado">
      <input type="hidden" name="id_torneo" value="<?php echo (int)$torneo->getIdTorneo(); ?>">
      <input type="hidden" name="id_enfrentamiento" value="<?php echo $id_p; ?>">
      <?php echo campoCsrf(); ?>
      <fieldset>
        <legend><?php echo $jugado ? 'Corregir el resultado' : 'Resultado'; ?><span class="visualmente-oculto"> de <?php echo $cruce; ?></span></legend>
        <div class="campo">
          <label>Cómo termina<select name="desenlace" aria-describedby="ayuda-desenlace-<?php echo $id_p; ?>">
            <option value="marcador"<?php if ($desenlace === 'marcador') { echo ' selected'; } ?>>Con marcador</option>
            <option value="wo-local"<?php if ($desenlace === 'wo-local') { echo ' selected'; } ?>>Walkover: gana <?php echo $local; ?></option>
            <option value="wo-visitante"<?php if ($desenlace === 'wo-visitante') { echo ' selected'; } ?>>Walkover: gana <?php echo $visit; ?></option>
          </select></label>
          <p class="ayuda-campo" id="ayuda-desenlace-<?php echo $id_p; ?>">Con walkover gana el que se presenta, sin marcador.</p>
        </div>
        <div class="marcador-campos">
          <div class="campo">
            <label><?php echo $local; ?><input type="number" name="local" value="<?php echo htmlspecialchars($v_local); ?>" min="0" max="<?php echo $maximo; ?>" inputmode="numeric" aria-describedby="ayuda-marcador-<?php echo $id_p; ?>"></label>
          </div>
          <div class="campo">
            <label><?php echo $visit; ?><input type="number" name="visitante" value="<?php echo htmlspecialchars($v_visit); ?>" min="0" max="<?php echo $maximo; ?>" inputmode="numeric" aria-describedby="ayuda-marcador-<?php echo $id_p; ?>"></label>
          </div>
        </div>
        <p class="ayuda-campo" id="ayuda-marcador-<?php echo $id_p; ?>"><?php echo htmlspecialchars($ayuda_marcador); ?></p>
      </fieldset>
      <button class="btn btn-primario" type="submit"><?php echo $jugado ? 'Corregir el resultado' : 'Guardar el resultado'; ?><span class="visualmente-oculto"> de <?php echo $cruce; ?></span></button>
    </form>
<?php       }
            if (!$es_cerrada && !$jugado) {
              $v_cuando = isset($valor['fecha_hora']) ? $valor['fecha_hora'] : valorFechaHora($partido->getFechaHora());
              $v_donde  = isset($valor['lugar']) ? $valor['lugar'] : (string)$partido->getLugar(); ?>
    <form class="programar-partido" action="<?php echo htmlspecialchars($ruta_partidos); ?>" method="post">
      <input type="hidden" name="accion" value="programar">
      <input type="hidden" name="id_torneo" value="<?php echo (int)$torneo->getIdTorneo(); ?>">
      <input type="hidden" name="id_enfrentamiento" value="<?php echo $id_p; ?>">
      <?php echo campoCsrf(); ?>
      <fieldset>
        <legend>Día y lugar<span class="visualmente-oculto"> de <?php echo $cruce; ?></span></legend>
        <div class="grilla">
          <div class="campo">
            <label>Día y hora<input type="datetime-local" name="fecha_hora" value="<?php echo htmlspecialchars($v_cuando); ?>" aria-describedby="ayuda-cuando-<?php echo $id_p; ?>"></label>
            <p class="ayuda-campo" id="ayuda-cuando-<?php echo $id_p; ?>">Vacío: a confirmar.</p>
          </div>
          <div class="campo">
            <label>Lugar<input type="text" name="lugar" value="<?php echo htmlspecialchars($v_donde); ?>" maxlength="80" aria-describedby="ayuda-lugar-<?php echo $id_p; ?>"></label>
            <p class="ayuda-campo" id="ayuda-lugar-<?php echo $id_p; ?>">Hasta 80 caracteres. Opcional.</p>
          </div>
        </div>
      </fieldset>
      <button class="btn" type="submit">Guardar día y lugar<span class="visualmente-oculto"> de <?php echo $cruce; ?></span></button>
    </form>
<?php       }
            if ($es_en_juego && !$jugado) { ?>
    <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_partidos); ?>" method="post">
      <input type="hidden" name="accion" value="en_vivo">
      <input type="hidden" name="id_torneo" value="<?php echo (int)$torneo->getIdTorneo(); ?>">
      <input type="hidden" name="id_enfrentamiento" value="<?php echo $id_p; ?>">
      <input type="hidden" name="en_vivo" value="<?php echo $partido->estaEnVivo() ? '0' : '1'; ?>">
      <?php echo campoCsrf(); ?>
      <button class="btn" type="submit"><?php echo $partido->estaEnVivo() ? 'Quitar de en vivo' : 'Marcar en vivo'; ?><span class="visualmente-oculto"> <?php echo $cruce; ?></span></button>
    </form>
<?php       } ?>
  </div>
<?php   } ?>

<?php   if ($es_en_juego) {
          $faltan = ResultadoRepositorio::faltanResultados($abierta);
          if ($torneo->esEliminacion()) {
              $ultima = ($abierta->getCantidadEnfrentamientos() === 1);
          } elseif ($torneo->esSuizo()) {
              $ultima = ($abierta->getNumero() >= (int)$config->getRondasPrevistas());
          } else {
              $ultima = ($abierta === $rondas[count($rondas) - 1]);
          }
          $cerrar = $torneo->esLiga() ? 'Cerrar la ' . htmlspecialchars($abierta->getNombreVisible()) : 'Cerrar la ronda'; ?>
  <h3>Cierre de la <?php echo $w['ronda']; ?></h3>
<?php     if ($faltan === 0) { ?>
  <form class="accion-liga" action="<?php echo htmlspecialchars($ruta_partidos); ?>" method="post">
    <input type="hidden" name="accion" value="cerrar_fecha">
    <input type="hidden" name="id_torneo" value="<?php echo (int)$torneo->getIdTorneo(); ?>">
    <input type="hidden" name="id_ronda" value="<?php echo (int)$abierta->getIdRonda(); ?>">
    <?php echo campoCsrf(); ?>
    <button class="btn btn-primario" type="submit"><?php echo $cerrar; ?><span class="visualmente-oculto"><?php echo $torneo->esLiga() ? '' : ' (' . htmlspecialchars($abierta->getNombreVisible()) . ')'; ?> de <?php echo $nom; ?></span></button>
  </form>
<?php       if ($torneo->esEliminacion()) { ?>
  <p><small><?php echo $ultima ? 'Es la final: al cerrarla, el torneo termina y su ganador es el campeón.'
                                : 'Con la ronda cerrada, sus resultados quedan fijos y se arma la siguiente con los que pasan.'; ?></small></p>
<?php       } elseif ($torneo->esSuizo()) { ?>
  <p><small><?php echo $ultima ? 'Es la última ronda: al cerrarla, el torneo termina y el primero de la tabla es el campeón.'
                                : 'Con la ronda cerrada, sus resultados quedan fijos y se arma la siguiente, según la tabla.'; ?></small></p>
<?php       } else { ?>
  <p><small><?php echo $ultima ? 'Es la última fecha: al cerrarla, la liga termina y el primero de la tabla es el campeón.'
                                : 'Con la fecha cerrada, sus resultados quedan fijos y la siguiente queda en juego.'; ?></small></p>
<?php       } ?>
<?php     } else { ?>
  <p><span class="enlace-apagado" aria-disabled="true"><?php echo $cerrar; ?></span> <small><?php echo ($faltan === 1) ? 'Falta 1 resultado.' : 'Faltan ' . $faltan . ' resultados.'; ?></small></p>
<?php     } ?>
<?php   } ?>
</section>
<?php } ?>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Cómo avanza una <?php echo $w['ronda']; ?></span>
  <ol class="pasos-liga">
    <li>Día, hora y lugar de cada partido, en cualquier <?php echo $w['ronda']; ?> sin cerrar.</li>
    <li>En vivo mientras se juega, y el resultado al terminar: el marcador o el walkover. La tabla se rehace con cada carga.</li>
    <li>Con todos los resultados, la <?php echo $w['ronda']; ?> se cierra y la siguiente queda en juego<?php echo ($torneo !== null && !$torneo->esLiga()) ? ', armada en ese momento' : ''; ?>. Hasta el cierre, un resultado se corrige.</li>
    <li>La última cerrada termina <?php echo $w['el']; ?>: <?php echo ($torneo !== null && $torneo->esEliminacion()) ? 'el ganador de la final' : 'el primero de la tabla'; ?> es el campeón.</li>
  </ol>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
