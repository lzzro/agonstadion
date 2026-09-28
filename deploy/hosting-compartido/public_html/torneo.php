<?php
require __DIR__ . '/../stadion_app/config/pagina.php';
require_once $carpeta_app . '/models/TorneoRepositorio.php';
require_once $carpeta_app . '/models/ParticipanteRepositorio.php';
require_once $carpeta_app . '/models/FixtureRepositorio.php';
require_once $carpeta_app . '/models/PosicionRepositorio.php';
require_once $carpeta_app . '/models/EquipoRepositorio.php';
require_once $carpeta_app . '/models/PedidoInscripcionRepositorio.php';
require_once $carpeta_app . '/ligas.php';

# =====================================================================
# Detalle de una liga - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# torneo.php?id=N muestra la liga N; sin numero, la destacada
# (TorneoRepositorio::idDestacado: la que tiene un partido en vivo, o la
# que esta en curso desde hace mas tiempo). Es tambien el destino de
# "Posiciones" en el menu: torneo.php#posiciones. Todo sale de la base:
# los equipos, las fechas con sus partidos y resultados, la tabla (con
# los puntajes y el desempate de la liga) y las reglas.
#
# Las cinco pestanas son las de siempre, sin JavaScript (:target):
# Resumen, la de por defecto, va ultima (ver style.css). Lo que acompana
# a la vista abierta va dos veces, con data-vista y data-fuera: "Saltar
# al contenido" y, en la liga destacada, la entrada marcada del menu
# (Torneos o Posiciones). En otra liga, Posiciones del menu lleva a la
# destacada, asi que ahi el menu marca siempre Torneos.
#
# Con la inscripcion abierta, un capitan con sesion pide lugar para su
# equipo (formulario a inscripcionController.php, con el token). La
# vuelta trae un codigo (?aviso=...) que se traduce aca a su mensaje.
#
# Lo que organiza una cuenta de muestra lleva la marca "De muestra".
# =====================================================================

$torneo = null;
$todos = array();          # participantes, con las bajas (figuran en lo que jugaron)
$rondas = array();
$tabla = array();
$organizados = null;
$equipos_propios = array();
$ultimos = array();
$destacado = null;
$sin_base = false;

# Solo un texto: un ?id[]=x (un arreglo) no es un numero de liga.
$pedido_id = (isset($_GET['id']) && is_string($_GET['id'])) ? (int)$_GET['id'] : 0;
$conexion = conectarBD();
if ($conexion === null) {
    $sin_base = true;
} else {
    $torneos   = new TorneoRepositorio($conexion);
    $destacado = $torneos->idDestacado();
    $id        = ($pedido_id > 0) ? $pedido_id : (int)$destacado;
    $torneo    = ($id > 0) ? $torneos->buscarPorId($id) : null;
    # Un borrador o un torneo cancelado no son publicos.
    if ($torneo !== null && !in_array($torneo->getEstado(), array('inscripcion', 'en_curso', 'finalizado'))) {
        $torneo = null;
    }
    if ($torneo !== null) {
        $todos = (new ParticipanteRepositorio($conexion))->listarDeTorneo($id, true);
        if ($todos === null) { $todos = array(); }
        $rondas = (new FixtureRepositorio($conexion))->fechasDe($id, $todos);
        if ($rondas === null) { $rondas = array(); }
        if ($torneo->getConfiguracion() !== null) {
            $tabla = (new PosicionRepositorio($conexion))->tablaDe($id, $todos, $torneo->getConfiguracion());
            if ($tabla === null) { $tabla = array(); }
        }
        $organizados = $torneos->cantidadDeOrganizador($torneo->getOrganizador()->getIdUsuario());
        if ($persona_sesion !== null && $torneo->tieneInscripcionAbierta()) {
            $equipos_propios = (new EquipoRepositorio($conexion))->listarDeCapitan($persona_sesion);
            if ($equipos_propios === null) { $equipos_propios = array(); }
            $ultimos = (new PedidoInscripcionRepositorio($conexion))
                           ->ultimosDeCapitan($id, $persona_sesion->getIdUsuario());
        }
    }
    $conexion->close();
}

if ($torneo === null) {
    http_response_code($sin_base ? 503 : 404);
}

# --- Lo que se calcula de lo leido ---------------------------------------
$en_competencia = array();
foreach ($todos as $p) {
    if ($p->estaEnCompetencia()) { $en_competencia[(int)$p->getIdParticipante()] = $p; }
}
$en_vivo = null;       # el partido en vivo, si hay
$proximo = null;       # el primero por jugar con dia y hora, de ahora en adelante
$ahora   = date('Y-m-d H:i:s');   # Montevideo (apps/fechas.php), como la base
$cerradas = 0;
$actual = null;        # la primera fecha sin cerrar
foreach ($rondas as $ronda) {
    if ($ronda->estaCerrada()) { $cerradas++; } elseif ($actual === null) { $actual = $ronda; }
    foreach ($ronda->getEnfrentamientos() as $e) {
        if ($e->estaEnVivo() && $en_vivo === null) { $en_vivo = $e; }
        # Un programado con la hora ya pasada no es "proximo": su
        # resultado todavia no esta cargado. Las dos fechas van como
        # AAAA-MM-DD HH:MM:SS, asi que comparar el texto es comparar
        # las fechas.
        if ($e->getEstado() === 'programado' && $e->getFechaHora() !== null && !$e->esLibre()
            && $e->getFechaHora() >= $ahora
            && ($proximo === null || $e->getFechaHora() < $proximo['partido']->getFechaHora())) {
            $proximo = array('partido' => $e, 'ronda' => $ronda);
        }
    }
}

$es_destacado = ($torneo !== null && (int)$torneo->getIdTorneo() === (int)$destacado);
$config  = ($torneo === null) ? null : $torneo->getConfiguracion();
$unidad  = ($torneo === null) ? 'tantos' : $torneo->getDisciplina()->getUnidad();
$fechas  = count($rondas);

# El aviso de la vuelta de "Pedir lugar": array(es error, texto).
$avisos_lugar = array(
    'pedido-enviado' => array(false, 'El pedido queda en revisión. Lo resuelve quien organiza la liga.'),
    'sin-equipo'     => array(true, 'El lugar lo pide el capitán del equipo, con su propia cuenta.'),
    'cerrada'        => array(true, 'La inscripción de esta liga está cerrada.'),
    'cupo'           => array(true, 'La liga ya tiene su cupo completo.'),
    'ya-juega'       => array(true, 'Ese equipo ya juega esta liga.'),
    'en-revision'    => array(true, 'Ese equipo ya tiene un pedido en revisión en esta liga.'),
    'no-disponible'  => array(true, 'El pedido no se puede registrar por ahora.'),
    'muestra'        => array(true, 'Una liga de muestra no recibe pedidos.')
);
$aviso = (isset($_GET['aviso']) && is_string($_GET['aviso']) && isset($avisos_lugar[$_GET['aviso']]))
       ? $avisos_lugar[$_GET['aviso']] : null;

# Lo que dice la etiqueta de arriba del nombre.
$avance = '';
if ($torneo !== null) {
    if ($torneo->tieneInscripcionAbierta()) {
        $avance = count($en_competencia) . ' de ' . $torneo->getMaxParticipantes() . ' equipos';
    } elseif ($fechas === 0) {
        $avance = 'fixture por armar';
    } elseif ($actual === null) {
        $avance = plural($fechas, 'fecha', 'fechas');
    } else {
        $avance = 'Fecha ' . $actual->getNumero() . ' de ' . $fechas;
    }
}

# Las fechas de la liga, en palabras.
$periodo = '';
if ($torneo !== null) {
    if (!empty($torneo->getFechaInicio()) && !empty($torneo->getFechaFin())) {
        $periodo = fechaTexto($torneo->getFechaInicio()) . ' – ' . fechaTexto($torneo->getFechaFin());
    } elseif (!empty($torneo->getFechaInicio())) {
        $periodo = 'desde el ' . fechaTexto($torneo->getFechaInicio(), true);
    } else {
        $periodo = 'inicio a definir';
    }
}

# Los equipos del capitan de la sesion: cuales pueden pedir lugar, y en
# que quedo cada uno.
$puede_pedir = array();
$situacion = array();
foreach ($equipos_propios as $equipo) {
    $id_e = (int)$equipo->getIdEquipo();
    $juega = false;
    foreach ($en_competencia as $p) {
        if ($p->esEquipo() && (int)$p->getEquipo()->getIdEquipo() === $id_e) { $juega = true; }
    }
    $ultimo = isset($ultimos[$id_e]) ? $ultimos[$id_e] : null;
    if ($juega) {
        $situacion[] = $equipo->getNombre() . ': juega esta liga.';
    } elseif ($ultimo !== null && $ultimo->estaPendiente()) {
        $situacion[] = $equipo->getNombre() . ': pedido en revisión desde el ' . fechaTexto($ultimo->getFechaPedido()) . '.';
    } else {
        if ($ultimo !== null && $ultimo->estaRechazado()) {
            $situacion[] = $equipo->getNombre() . ': pedido rechazado el ' . fechaTexto($ultimo->getFechaResolucion()) . '. Puede pedirse otra vez.';
        }
        $puede_pedir[] = $equipo;
    }
}
$cupo_lleno = ($torneo !== null && count($en_competencia) >= $torneo->getMaxParticipantes());

# El titulo: "Aviso ·" si hay un aviso de error.
$titulo_pagina = ($torneo === null) ? 'Torneo no encontrado' : $torneo->getNombre();
$nom = ($torneo === null) ? '' : htmlspecialchars($torneo->getNombre());
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title><?php if ($aviso !== null && $aviso[0]) { echo 'Aviso · '; } ?><?php echo htmlspecialchars($titulo_pagina); ?> · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="<?php echo recurso($ruta_publica, 'css/style.css'); ?>">
  <script src="<?php echo recurso($ruta_publica, 'js/tema.js'); ?>"></script>
</head>
<body>
<?php if ($torneo === null) { ?>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<?php } else { ?>
<a class="saltar" href="#contenido" data-vista="resumen">Saltar al contenido</a>
<a class="saltar" href="#calendario" data-vista="calendario">Saltar al contenido</a>
<a class="saltar" href="#posiciones" data-vista="posiciones">Saltar al contenido</a>
<a class="saltar" href="#participantes" data-vista="participantes">Saltar al contenido</a>
<a class="saltar" href="#reglas" data-vista="reglas">Saltar al contenido</a>
<?php } ?>
<div class="pagina">
<header>
  <a class="marca" href="index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $persona_sesion); ?>
</header>
<?php if ($es_destacado) { ?>
<nav><a href="index.php">Inicio</a><a href="torneos.php" class="activo" aria-current="true" data-fuera="posiciones">Torneos</a><a href="torneos.php" data-vista="posiciones">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones" data-fuera="posiciones">Posiciones</a><a href="torneo.php#posiciones" class="activo" aria-current="page" data-vista="posiciones">Posiciones</a><a href="panel.php">Organizadores</a></nav>
<?php } else { ?>
<nav><a href="index.php">Inicio</a><a href="torneos.php" class="activo" aria-current="true">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.php">Organizadores</a></nav>
<?php } ?>
<main id="contenido">
<?php if ($torneo === null) { ?>
<section>
  <p class="etiqueta">Torneos</p>
<?php   if ($sin_base) { ?>
  <h1>Torneo fuera de alcance</h1>
  <p class="intro">Los torneos no se pueden leer por ahora.</p>
<?php   } else { ?>
  <h1>Sin torneo con ese número</h1>
  <p class="intro">Ninguna competencia pública lleva ese número.</p>
<?php   } ?>
  <p><a href="torneos.php">Ver los torneos públicos <span aria-hidden="true">→</span></a></p>
</section>
<?php } else { ?>
<div class="torneo-zona">
<section>
  <p class="etiqueta">Torneos / <?php echo htmlspecialchars($torneo->getDisciplina()->getNombre()); ?></p>
  <div class="fila"><span class="etiqueta"><?php echo htmlspecialchars($torneo->getModulo()->getNombre() . ' · ' . $torneo->getDisciplina()->getNombre() . ' · ' . $avance); ?></span><?php echo chipLigaHtml($torneo, $en_vivo !== null); ?></div>
  <h1><?php echo $nom; ?></h1>
  <p class="intro">Organiza <strong><?php echo htmlspecialchars($torneo->getOrganizador()->getNombreCompletoVisible()); ?></strong> · <?php echo plural(count($en_competencia), 'equipo', 'equipos'); ?> · <?php echo htmlspecialchars($periodo); ?><?php if (!empty($torneo->getSede())) { echo ' · ' . htmlspecialchars($torneo->getSede()); } ?></p>
  <div class="fila"><span class="enlace-apagado" aria-disabled="true">Seguir torneo</span><?php if ($torneo->esDeMuestra()) { echo marcaMuestra(); } ?></div>
<?php   if ($aviso !== null) { ?>
<?php     if ($aviso[0]) { ?>
  <div role="alert"><ul class="avisos"><li><?php echo htmlspecialchars($aviso[1]); ?></li></ul></div>
<?php     } else { ?>
  <p class="intro" role="status"><?php echo htmlspecialchars($aviso[1]); ?></p>
<?php     } ?>
<?php   } ?>
<?php   if ($torneo->tieneInscripcionAbierta()) { ?>
  <div class="pedir-lugar">
<?php     if ($torneo->esDeMuestra()) { ?>
    <p><span class="enlace-apagado" aria-disabled="true">Pedir lugar</span> <small>Una liga de muestra no recibe pedidos.</small></p>
<?php     } elseif ($cupo_lleno) { ?>
    <p><span class="enlace-apagado" aria-disabled="true">Pedir lugar</span> <small>La liga tiene su cupo completo.</small></p>
<?php     } elseif ($persona_sesion === null) { ?>
    <p><a class="btn" href="login.php">Iniciar sesión para pedir lugar</a></p>
<?php     } elseif (empty($equipos_propios)) { ?>
    <p><a class="btn" href="<?php echo htmlspecialchars($ruta_perfil); ?>#mis-torneos">Armar un equipo para pedir lugar</a></p>
    <p><small>El lugar lo pide el capitán, con un equipo armado en su perfil.</small></p>
<?php     } else { ?>
<?php       foreach ($situacion as $linea) { ?>
    <p><small><?php echo htmlspecialchars($linea); ?></small></p>
<?php       } ?>
<?php       if (!empty($puede_pedir)) { ?>
    <form class="pedir-lugar" action="<?php echo htmlspecialchars($ruta_inscripcion); ?>" method="post">
      <input type="hidden" name="id_torneo" value="<?php echo (int)$torneo->getIdTorneo(); ?>">
      <?php echo campoCsrf(); ?>
      <div class="campo">
        <label>Equipo<select name="id_equipo" required aria-describedby="ayuda-pedir-lugar">
<?php         foreach ($puede_pedir as $equipo) { ?>
          <option value="<?php echo (int)$equipo->getIdEquipo(); ?>"><?php echo htmlspecialchars($equipo->getNombre()); ?></option>
<?php         } ?>
        </select></label>
        <p class="ayuda-campo" id="ayuda-pedir-lugar">Lo acepta o lo rechaza quien organiza la liga.</p>
      </div>
      <button class="btn btn-primario" type="submit">Pedir lugar<span class="visualmente-oculto"> en <?php echo $nom; ?></span></button>
    </form>
<?php       } ?>
<?php     } ?>
  </div>
<?php   } ?>
</section>
<div class="vista" id="calendario">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario" class="activo" aria-current="page">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Calendario del torneo</h2>
<?php   if (empty($rondas)) { ?>
  <p><?php echo $torneo->tieneInscripcionAbierta() ? 'El fixture se arma al cerrar la inscripción.' : 'El fixture está por armarse.'; ?></p>
<?php   } else { ?>
  <div class="agenda">
<?php     foreach ($rondas as $ronda) {
            $como = $ronda->estaCerrada() ? 'cerrada' : (($ronda->getEstado() === 'en_curso') ? 'en curso' : 'por jugar'); ?>
  <div class="agenda-dia">
    <p class="etiqueta dia"><?php echo htmlspecialchars($ronda->getNombreVisible()) . ' · ' . $como; ?></p>
<?php       foreach ($ronda->getEnfrentamientos() as $e) {
              if ($e->getFechaHora() !== null) {
                  $arriba = mayuscula(fechaConDia($e->getFechaHora()));
              } elseif (!empty($ronda->getFechaFin())) {
                  $arriba = mayuscula(fechaConDia($ronda->getFechaFin())) . ' · Horario a confirmar';
              } else {
                  $arriba = 'Día y hora a confirmar';
              }
              filaPartido($e, htmlspecialchars($arriba));
            } ?>
  </div>
<?php     } ?>
  </div>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Las fechas jugadas quedan en la tabla de posiciones.</span>
<?php   } ?>
  </section>
</div>
<div class="vista" id="posiciones">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones" class="activo" aria-current="page">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Tabla de posiciones</h2>
<?php   if (empty($tabla)) { ?>
  <p>La tabla se arma con el fixture.</p>
<?php   } else {
          # Cuantos clasifican sale de la configuracion de la liga (0 =
          # ninguno), nunca de un numero escrito aca. La columna E va solo
          # en las ligas que admiten empate. G, E y P (.detalle) no se ven
          # en el telefono: ahi la tabla deja lo que ordena (tantos, Dif y
          # Pts), ver style.css.
          $clasifican = ($config === null) ? 0 : $config->getClasificanPlayoffs();
          $con_empate = ($config === null) || $config->admiteEmpate();
          $tantos     = mayuscula($unidad); ?>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Tabla de posiciones">
  <table class="posiciones">
    <thead><tr><th class="pos">#</th><th>Equipo</th><th class="cifra">PJ</th><th class="cifra detalle">G</th><?php if ($con_empate) { ?><th class="cifra detalle">E</th><?php } ?><th class="cifra detalle">P</th><th class="cifra"><?php echo htmlspecialchars($tantos); ?></th><th class="cifra">Dif</th><th class="num">Pts</th></tr></thead>
    <tbody><?php foreach ($tabla as $i => $fila) {
      $clasifica = ($i < $clasifican); ?><tr<?php if ($clasifica) { echo ' class="clasifica"'; } ?>><td class="pos num"><?php echo $i + 1; ?></td><td><?php echo htmlspecialchars($fila->getNombreVisible()); ?><?php if ($clasifica) { ?><span class="visualmente-oculto"> · clasifica a playoffs</span><?php } ?></td><td class="cifra"><?php echo $fila->getPartidosJugados(); ?></td><td class="cifra detalle"><?php echo $fila->getGanados(); ?></td><?php if ($con_empate) { ?><td class="cifra detalle"><?php echo $fila->getEmpatados(); ?></td><?php } ?><td class="cifra detalle"><?php echo $fila->getPerdidos(); ?></td><td class="cifra"><?php echo $fila->getFavor() . ':' . $fila->getContra(); ?></td><td class="cifra"><?php echo conSigno($fila->getDiferencia()); ?></td><td class="num"><?php echo $fila->getPuntos($config); ?></td></tr><?php } ?></tbody>
  </table>
  </div>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em"><?php if ($clasifican > 0) { ?><span class="marca-clasifica" aria-hidden="true"></span>Clasifican a playoffs: los <?php echo $clasifican; ?> primeros · <?php } ?>Victoria <?php echo $config->getPuntosVictoria(); ?> pts · <?php if ($config->admiteEmpate()) { ?>Empate <?php echo $config->getPuntosEmpate(); ?> pt<?php echo ($config->getPuntosEmpate() === 1) ? '' : 's'; ?><?php } else { ?>Sin empates<?php } ?> · <?php echo htmlspecialchars($tantos); ?>: a favor y en contra · Dif: diferencia de <?php echo $unidad; ?></span>
<?php   } ?>
  </section>
</div>
<div class="vista" id="participantes">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes" class="activo" aria-current="page">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Participantes</h2>
<?php   if (empty($en_competencia)) { ?>
  <p>Sin equipos anotados.</p>
<?php   } else {
          $con_capitan = 0;
          foreach ($en_competencia as $p) { if ($p->esEquipo() && $p->getEquipo()->getCapitan() !== null) { $con_capitan++; } } ?>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Participantes">
  <table>
    <thead><tr><th>Equipo</th><th>Ciudad</th><?php if ($con_capitan > 0) { ?><th>Capitán</th><?php } ?></tr></thead>
    <tbody><?php foreach ($en_competencia as $p) {
      $equipo = $p->getEquipo();
      $ciudad = ($equipo === null || $equipo->getCiudad() === null || $equipo->getCiudad() === '') ? '—' : $equipo->getCiudad(); ?><tr><td><?php echo htmlspecialchars($p->getNombreVisible()); ?></td><td><?php echo htmlspecialchars($ciudad); ?></td><?php if ($con_capitan > 0) { ?><td><?php echo ($equipo !== null && $equipo->getCapitan() !== null) ? htmlspecialchars($equipo->getCapitan()->getNombreCompletoVisible()) : '—'; ?></td><?php } ?></tr><?php } ?></tbody>
  </table>
  </div>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em"><?php echo mayuscula(plural(count($en_competencia), 'equipo', 'equipos')); ?><?php if ($torneo->tieneInscripcionAbierta()) { echo ' de ' . $torneo->getMaxParticipantes() . ' lugares'; } ?></span>
<?php   } ?>
  </section>
</div>
<div class="vista" id="reglas">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas" class="activo" aria-current="page">Reglas</a></div>
  <section class="tarjeta">
  <h2>Reglas</h2>
<?php   if ($config === null) { ?>
  <p>Las reglas de esta liga no se pueden leer por ahora.</p>
<?php   } else {
          $reglas = array();
          $reglas['Formato'] = 'Liga de ' . ($torneo->tieneInscripcionAbierta() ? 'hasta ' . $torneo->getMaxParticipantes() : count($en_competencia))
                             . ' equipos, todos contra todos, ' . vueltasTexto($config) . '.';
          # Una liga sin empates (admite_empate = 0, como las de series al
          # mejor de un numero impar) no nombra los puntos del empate.
          if ($config->admiteEmpate()) {
              $reglas['Puntaje'] = $config->getPuntosVictoria() . ' puntos la victoria, ' . $config->getPuntosEmpate()
                                 . ' el empate y ' . $config->getPuntosDerrota() . ' la derrota.';
          } else {
              $reglas['Puntaje'] = $config->getPuntosVictoria() . ' puntos la victoria y ' . $config->getPuntosDerrota()
                                 . ' la derrota. Ningún partido termina empatado.';
          }
          $reglas['Desempate'] = $config->textoDesempate($unidad);
          if ($config->getClasificanPlayoffs() > 0) {
              $reglas['Playoffs'] = 'Clasifican los ' . $config->getClasificanPlayoffs() . ' primeros de la tabla.';
          }
          if (!empty($config->getReglas())) {
              $reglas['Partidos'] = $config->getReglas();
          } ?>
  <table>
    <tbody>
<?php     foreach ($reglas as $rotulo => $texto) { ?>
      <tr><th scope="row" class="etiqueta"><?php echo $rotulo; ?></th><td><?php echo htmlspecialchars($texto); ?></td></tr>
<?php     } ?>
    </tbody>
  </table>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em"><?php echo mayuscula(plural(count($reglas), 'regla', 'reglas')); ?> · las mismas para toda la liga</span>
<?php   } ?>
  </section>
</div>
<div class="vista" id="resumen">
  <div class="pestanas"><a href="#resumen" class="activo" aria-current="page">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Resumen</h2>
  <p class="intro"><?php
    if ($torneo->tieneInscripcionAbierta()) {
        echo 'Todos contra todos, ' . vueltasTexto($config) . '. La inscripción sigue abierta: '
           . count($en_competencia) . ' de ' . $torneo->getMaxParticipantes() . ' lugares ocupados. '
           . (empty($torneo->getFechaInicio()) ? 'La fecha de inicio queda a definir.'
                                              : 'La primera fecha, el ' . htmlspecialchars(fechaConDia($torneo->getFechaInicio())) . '.');
    } elseif ($fechas === 0) {
        echo 'Inscripción cerrada con ' . plural(count($en_competencia), 'equipo', 'equipos') . '. El fixture está por armarse.';
    } else {
        echo plural($fechas, 'fecha', 'fechas') . ', ' . vueltasTexto($config) . ': cada equipo se cruza '
           . ($config !== null && $config->esIdaYVuelta() ? 'dos veces' : 'una vez') . ' con cada rival. ';
        if ($actual === null) {
            echo 'Todas las fechas quedan atrás.';
        } elseif ($cerradas === 0) {
            echo 'La primera fecha está en juego.';
        } else {
            echo plural($cerradas, 'fecha cerrada', 'fechas cerradas') . '; la ' . $actual->getNumero() . ' en juego.';
        }
        if ($config !== null && $config->getClasificanPlayoffs() > 0) {
            echo ' Los ' . $config->getClasificanPlayoffs() . ' primeros de la tabla llegan a los playoffs.';
        }
    } ?></p>
  </section>
<?php   if ($en_vivo !== null) { ?>
  <section class="tarjeta">
  <div class="fila" style="justify-content:space-between"><span class="etiqueta">Ahora</span><span class="estado estado-en-vivo">En vivo</span></div>
  <h3><?php echo htmlspecialchars($en_vivo->getTitulo()); ?></h3>
  </section>
<?php   } ?>
<?php   if ($proximo !== null) { ?>
  <section class="tarjeta">
  <span class="etiqueta">Próximo enfrentamiento</span>
  <h3><?php echo htmlspecialchars($proximo['partido']->getTitulo()); ?></h3>
  <p><?php echo htmlspecialchars($proximo['ronda']->getNombreVisible() . ' · ' . fechaConDia($proximo['partido']->getFechaHora()) . ', ' . horaTexto($proximo['partido']->getFechaHora())); ?></p>
  <p><a href="#calendario">Ver el calendario del torneo <span aria-hidden="true">→</span></a></p>
  </section>
<?php   } ?>
<?php   if (!empty($tabla) && $tabla[0]->getPartidosJugados() > 0) {
          $primero = $tabla[0];
          $marca = array();
          if ($primero->getGanados() > 0)   { $marca[] = plural($primero->getGanados(), 'ganado', 'ganados'); }
          if ($primero->getEmpatados() > 0) { $marca[] = plural($primero->getEmpatados(), 'empatado', 'empatados'); }
          if ($primero->getPerdidos() > 0)  { $marca[] = plural($primero->getPerdidos(), 'perdido', 'perdidos'); } ?>
  <section class="tarjeta">
  <span class="etiqueta">Al frente de la tabla</span>
  <h3><?php echo htmlspecialchars($primero->getNombreVisible()); ?></h3>
  <p><?php echo plural($primero->getPuntos($config), 'punto', 'puntos'); ?> en <?php echo plural($primero->getPartidosJugados(), 'fecha', 'fechas'); ?> · <?php echo implode(', ', $marca); ?> · <?php echo conSigno($primero->getDiferencia()); ?> de diferencia de <?php echo $unidad; ?></p>
  <p><a href="#posiciones">Ver la tabla completa <span aria-hidden="true">→</span></a></p>
  </section>
<?php   } ?>
</div>
</div>
<?php } ?>
</main>
<?php if ($torneo !== null) { ?>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Reglas en breve</span>
  <p><?php
    $breve = 'Todos contra todos, ' . vueltasTexto($config) . '.';
    if ($config !== null) {
        if (!empty($config->getReglas())) {
            $oraciones = explode('. ', $config->getReglas());
            $breve .= ' ' . rtrim($oraciones[0], '.') . '.';
        }
        if ($config->admiteEmpate()) {
            $breve .= ' Victoria ' . $config->getPuntosVictoria() . ' pts, empate ' . $config->getPuntosEmpate()
                    . ' pt' . (($config->getPuntosEmpate() === 1) ? '' : 's') . '.';
        } else {
            $breve .= ' Victoria ' . $config->getPuntosVictoria() . ' pts, sin empates.';
        }
        if ($config->getClasificanPlayoffs() > 0) {
            $breve .= ' Los ' . $config->getClasificanPlayoffs() . ' primeros clasifican a playoffs.';
        }
    }
    echo htmlspecialchars($breve); ?></p>
</div>
<div class="tarjeta tarjeta-olivo">
  <div class="fila" style="justify-content:space-between"><span class="etiqueta">Organizador</span><?php if ($torneo->esDeMuestra()) { echo marcaMuestra(); } ?></div>
  <h3><?php echo htmlspecialchars($torneo->getOrganizador()->getNombreCompletoVisible()); ?></h3>
  <p><?php if ($organizados !== null) { echo plural($organizados, 'torneo organizado', 'torneos organizados'); } ?><?php
     $alta = $torneo->getOrganizador()->getFechaAlta();
     if (!empty($alta)) { echo ' · desde ' . substr($alta, 0, 4); } ?></p>
</div>
</aside>
<?php } ?>
<?php piePagina(true); ?>
</body>
</html>
