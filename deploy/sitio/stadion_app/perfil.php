<?php
# =====================================================================
# Vista de perfil
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La incluye perfilController.php despues de cargar al usuario. Recibe:
#   $usuario        objeto Usuario con sus roles ya cargados
#   $inscripciones  torneos de la persona (ver TorneoRepositorio), o
#                   null si la consulta no se pudo hacer
#   $mensaje        texto de resultado, si se acaba de guardar algo
#   $errores        arreglo de mensajes, si algo fallo
#   $pedido_organizador  el ultimo pedido del rol de organizador
#                   (PedidoRol), o null si nunca lo pidio o ya lo tiene
#   $ruta_publica, $ruta_perfil, $ruta_salir, $ruta_admin   direcciones
#                   (ver apps/index.php). La de salida es para "Cerrar
#                   sesion", que vive aca, debajo del nombre; la de
#                   administracion solo se muestra a quien tiene el rol.
#   $carpeta_subidas  la carpeta de las imagenes en el disco, para leer
#                   las medidas de la portada (la deja el controlador)
#   $equipos        los equipos que capitanea la persona (Equipo), o
#                   null si no se pudieron leer
#   $errores_equipo, $valores_equipo   el formulario de equipo nuevo
#   $aviso_organizador  true si llega de crear.php o del panel sin el
#                   rol: la tarjeta de los roles lo explica
#   $integrantes_de id de equipo propio => sus integrantes (RF-12)
#   $equipos_ajenos los equipos que integra sin ser capitan
#   $errores_integrante, $valores_integrante   el formulario de sumar
#                   un integrante, por equipo
#   $mis_partidos   los partidos jugados de la persona (RF-26; ver
#                   EnfrentamientoRepositorio::jugadosDe), o null
#   $finales, $kotinos  las finales jugadas y los torneos ganados, o null
#   $mediciones, $comparaciones, $errores_medicion, $valores_medicion,
#   $hoy            el rendimiento fisico (RF-29)
#
# Todo formulario lleva el token de config/csrf.php (campoCsrf()).
#
# El nombre y el apellido se muestran con getNombreCompletoVisible():
# primera letra en mayuscula si estan guardados en minuscula. Los campos
# del formulario, en cambio, muestran lo guardado tal cual.
#
# Tres pestanas, con la misma mecanica sin JavaScript que torneo.php:
# cada vista es un bloque con su id y su propia barra, y :target
# muestra la que coincide con el ancla. Datos es la vista por defecto y
# por eso va ultima (la regla esta explicada en style.css).
#
#   Datos         lo que sale de la base, y se edita: nombre, alias,
#                 presentacion, foto y portada; y el cambio de la
#                 contrasena (tercera entrega)
#   Mis torneos   de la base: los torneos en los que compite la persona,
#                 sola o con un equipo
#   Rendimiento   de la base (RF-29, desde la tercera entrega): el ultimo
#                 valor de cada indicador, su evolucion (con dos
#                 mediciones o mas), la comparacion con las otras
#                 cuentas, el formulario de cada prueba y la lista de
#                 mediciones, cada una con "Borrar". Solo el nombre del
#                 indicador y su valor: como se mide va en el documento
#                 de Fisica, no en la pantalla (salvo la nota de los
#                 instrumentos, al pie).
#
# Lo real y lo de muestra no se mezclan sin aviso: todo numero que no
# sale de la base lleva al lado la marca "De muestra".
#
# Todo lo que viene de la base se imprime con htmlspecialchars: sin
# eso, una presentacion con etiquetas HTML se ejecutaria en la pagina.
# =====================================================================

if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
if (!isset($inscripciones)) { $inscripciones = null; }
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_salir))   { $ruta_salir   = 'salirController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }
if (!isset($pedido_organizador)) { $pedido_organizador = null; }
if (!isset($carpeta_subidas)) { $carpeta_subidas = __DIR__ . '/../public/subidas'; }
if (!isset($equipos)) { $equipos = null; }
if (!isset($mediciones)) { $mediciones = null; }
if (!isset($comparaciones)) { $comparaciones = array(); }
if (!isset($errores_medicion)) { $errores_medicion = array(); }
if (!isset($errores_clave)) { $errores_clave = array(); }
if (!isset($valores_medicion)) { $valores_medicion = array(); }
if (!isset($hoy)) { $hoy = date('Y-m-d'); }
require_once __DIR__ . '/rendimiento.php';
require_once __DIR__ . '/ligas.php';

# Las mediciones de cada prueba, de la mas vieja a la mas nueva.
$por_prueba = array('reaccion' => array(), 'sprint' => array(), 'salto' => array());
if (is_array($mediciones)) {
    foreach ($mediciones as $m) { $por_prueba[$m->getPrueba()][] = $m; }
}
# Lo que muestra cada formulario: lo que se escribio, si hubo un error.
$vm = function ($prueba, $campo) use ($valores_medicion, $hoy) {
    if (isset($valores_medicion[$prueba][$campo])) { return htmlspecialchars($valores_medicion[$prueba][$campo]); }
    return ($campo === 'fecha') ? $hoy : '';
};
if (!isset($errores_equipo)) { $errores_equipo = array(); }
if (!isset($integrantes_de)) { $integrantes_de = array(); }
if (!isset($equipos_ajenos)) { $equipos_ajenos = array(); }
if (!isset($errores_integrante)) { $errores_integrante = array(); }
if (!isset($valores_integrante)) { $valores_integrante = array(); }
if (!isset($valores_equipo)) { $valores_equipo = array('nombre' => '', 'ciudad' => ''); }
if (!isset($aviso_organizador)) { $aviso_organizador = false; }

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/models/ImagenSubida.php';
require_once __DIR__ . '/config/recursos.php';

# La fecha llega de la base como 2026-09-20 14:32:05. En pantalla va en
# castellano y sin la hora, que a nadie le importa.
$meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
               'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
function fechaLarga($fecha, $meses)
{
    $partes = explode('-', substr((string)$fecha, 0, 10));
    if (count($partes) !== 3 || !isset($meses[(int)$partes[1]])) {
        return '';
    }
    return (int)$partes[2] . ' de ' . $meses[(int)$partes[1]] . ' de ' . $partes[0];
}
$alta = $usuario->getFechaAlta();
$desde = '';
if (!empty($alta)) {
    $partes = explode('-', substr($alta, 0, 10));
    if (count($partes) === 3) {
        $mes = (int)$partes[1];
        if (isset($meses[$mes])) {
            $desde = 'miembro desde ' . $meses[$mes] . ' de ' . $partes[0];
        }
    }
}

# La portada solo se muestra si el nombre tiene la forma de los que
# genera el sistema. La foto la resuelve circuloPersona(), con el mismo
# control (ver apps/cabecera.php).
$portada = ImagenSubida::nombreValido($usuario->getFotoPortada()) ? $usuario->getFotoPortada() : null;
# Sus medidas reales, para el width y el height de la etiqueta: con
# ellos el navegador reserva el lugar antes de bajar la imagen. Si el
# archivo no se puede leer, la etiqueta va sin medidas, como antes.
$medidas_portada = '';
if ($portada !== null) {
    $datos_portada = @getimagesize($carpeta_subidas . '/' . $portada);
    if ($datos_portada !== false) {
        $medidas_portada = ' width="' . (int)$datos_portada[0] . '" height="' . (int)$datos_portada[1] . '"';
    }
}


# Cantidad de torneos: la misma lista de la pestana, contada. Un torneo
# en el que la persona aparece dos veces (dos equipos) cuenta una vez.
$cantidad_torneos = 0;
if (is_array($inscripciones)) {
    $vistos = array();
    foreach ($inscripciones as $inscripcion) {
        $vistos[$inscripcion['torneo']->getIdTorneo()] = true;
    }
    $cantidad_torneos = count($vistos);
}

# Como se nombra cada estado de torneo en pantalla, y con que chip.
$estados = array(
    'borrador'    => array('estado-cerrado',     'En preparación'),
    'inscripcion' => array('estado-inscripcion', 'Inscripción abierta'),
    'en_curso'    => array('estado-en-juego',    'En juego'),
    'finalizado'  => array('estado-cerrado',     'Finalizado'),
    'cancelado'   => array('estado-cerrado',     'Cancelado')
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title><?php if (!empty($errores) || $aviso_organizador) { echo 'Aviso · '; } ?>Perfil · Stadion</title>
  <link rel="icon" href="<?php echo $ruta_publica; ?>/img/stadion.png">
  <link rel="stylesheet" href="<?php echo recurso($ruta_publica, 'css/style.css'); ?>">
  <script src="<?php echo recurso($ruta_publica, 'js/tema.js'); ?>"></script>
</head>
<body>
<a class="saltar" href="#contenido" data-vista="datos">Saltar al contenido</a>
<a class="saltar" href="#mis-torneos" data-vista="mis-torneos">Saltar al contenido</a>
<a class="saltar" href="#rendimiento" data-vista="rendimiento">Saltar al contenido</a>
<div class="pagina">
<header>
  <a class="marca" href="<?php echo $ruta_publica; ?>/index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $usuario); ?>
</header>
<nav><a href="<?php echo $ruta_publica; ?>/index.php">Inicio</a><a href="<?php echo $ruta_publica; ?>/torneos.php">Torneos</a><a href="<?php echo $ruta_publica; ?>/calendario.php">Calendario</a><a href="<?php echo $ruta_publica; ?>/torneo.php#posiciones">Posiciones</a><a href="<?php echo $ruta_publica; ?>/panel.php">Organizadores</a></nav>
<main id="contenido">
<section>
<?php if ($portada !== null) { ?>
  <div class="portada portada-con-imagen"><img src="<?php echo $ruta_publica; ?>/subidas/<?php echo $portada; ?>"<?php echo $medidas_portada; ?> alt=""></div>
<?php } else { ?>
  <div class="portada" aria-hidden="true"></div>
<?php } ?>
  <div class="perfil-cabecera">
    <?php echo circuloPersona($usuario, $ruta_publica, 'avatar', false); ?>
    <div>
      <p class="epigrafe" lang="grc">ἀθλητής</p>
      <h1 style="font-size:36px"><?php echo htmlspecialchars($usuario->getNombreCompletoVisible()); ?></h1>
      <p class="intro"><?php
        $linea = array();
        if ($usuario->getAlias() !== null && $usuario->getAlias() !== '') {
            $linea[] = $usuario->getAlias();
        }
        foreach ($usuario->getRoles() as $rol) { $linea[] = $rol->getNombre(); }
        if ($desde !== '') { $linea[] = $desde; }
        echo htmlspecialchars(implode(' · ', $linea));
      ?></p>
      <?php # Cerrar sesion: el mismo formulario con POST de siempre, al
            # mismo controlador. Vive aca y no en la cabecera. ?>
      <form class="salir-perfil" action="<?php echo htmlspecialchars($ruta_salir); ?>" method="post">
        <?php echo campoCsrf(); ?>
        <button type="submit">Cerrar sesión</button>
      </form>
    </div>
  </div>
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

<div class="datos">
  <div><strong><?php echo $cantidad_torneos; ?></strong><span class="etiqueta">Torneos</span></div>
  <div><strong><?php echo ($finales === null) ? '—' : $finales; ?></strong><span class="etiqueta">Finales</span></div>
  <div><strong style="color:var(--olivo)"><?php echo ($kotinos === null) ? '—' : $kotinos; ?></strong><span class="etiqueta">Kotinos · victorias</span></div>
</div>

<div class="vista" id="mis-torneos">
  <div class="pestanas"><a href="#datos">Datos</a><a href="#mis-torneos" class="activo" aria-current="page">Mis torneos</a><a href="#rendimiento">Rendimiento</a></div>
  <section class="tarjeta">
  <h2>Mis torneos</h2>
<?php if ($inscripciones === null) { ?>
  <p>La lista de torneos no se puede leer por ahora.</p>
<?php } elseif (empty($inscripciones)) { ?>
  <p>Ningún torneo a nombre de esta cuenta.</p>
  <p><a href="<?php echo $ruta_publica; ?>/torneos.php">Ver los torneos públicos <span aria-hidden="true">→</span></a></p>
<?php } else { ?>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Mis torneos">
  <table>
    <thead><tr><th>Torneo</th><th>Disciplina</th><th>Estado</th><th>Participación</th><th>Inicio</th></tr></thead>
    <tbody>
<?php   foreach ($inscripciones as $inscripcion) {
          $t = $inscripcion['torneo'];
          $p = $inscripcion['participante'];
          $estado = isset($estados[$t->getEstado()]) ? $estados[$t->getEstado()] : array('estado-cerrado', $t->getEstado());
          $como = $p->esEquipo() ? 'Equipo · ' . $p->getEquipo()->getNombre() : 'Individual';
          if ($p->getEstado() === 'descalificado') { $como .= ' · descalificación'; } ?>
      <tr><td><?php echo htmlspecialchars($t->getNombre()); ?></td><td><?php echo htmlspecialchars($t->getDisciplina()->getNombre()); ?></td><td><span class="estado <?php echo $estado[0]; ?>"><?php echo htmlspecialchars($estado[1]); ?></span></td><td><?php echo htmlspecialchars($como); ?></td><td><?php echo htmlspecialchars(fechaLarga($t->getFechaInicio(), $meses)); ?></td></tr>
<?php   } ?>
    </tbody>
  </table>
  </div>
<?php } ?>
  </section>
  <section class="tarjeta" id="mis-resultados">
  <h2>Mis resultados</h2>
<?php if ($mis_partidos === null) { ?>
  <p>Los resultados no se pueden leer por ahora.</p>
<?php } elseif (empty($mis_partidos)) { ?>
  <p>Ningún partido jugado a nombre de esta cuenta.</p>
<?php } else {
        $vistos_desde = array();
        $cuenta = array('G' => 0, 'E' => 0, 'P' => 0);
        foreach ($mis_partidos as $partido) {
            $desde = partidoDesde($partido['enfrentamiento'], $partido['propio']);
            $vistos_desde[] = $desde;
            $cuenta[$desde['desenlace']]++;
        }
        $palabra = array('G' => 'Victoria', 'E' => 'Empate', 'P' => 'Derrota'); ?>
  <p><?php echo plural(count($mis_partidos), 'partido jugado', 'partidos jugados'); ?> · <?php echo $cuenta['G']; ?> <?php echo ($cuenta['G'] === 1) ? 'ganado' : 'ganados'; ?> · <?php echo $cuenta['E']; ?> <?php echo ($cuenta['E'] === 1) ? 'empatado' : 'empatados'; ?> · <?php echo $cuenta['P']; ?> <?php echo ($cuenta['P'] === 1) ? 'perdido' : 'perdidos'; ?></p>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Mis resultados">
  <table class="mis-resultados">
    <thead><tr><th>Fecha</th><th>Torneo</th><th>Rival</th><th class="num">Marcador</th><th>Resultado</th></tr></thead>
    <tbody>
<?php   foreach ($mis_partidos as $i => $partido) {
          $t = $partido['torneo'];
          $desde = $vistos_desde[$i];
          $cuando = ($partido['dia'] !== null) ? $partido['dia'] : substr((string)$partido['enfrentamiento']->getResultado()->getFechaCarga(), 0, 10);
          $publico = in_array($t->getEstado(), array('inscripcion', 'en_curso', 'finalizado'), true); ?>
      <tr><td class="fecha"><?php echo htmlspecialchars(diaMes($cuando) . '/' . substr($cuando, 0, 4)); ?></td><td><?php if ($publico) { ?><a href="<?php echo $ruta_publica; ?>/torneo.php?id=<?php echo (int)$t->getIdTorneo(); ?>"><?php echo htmlspecialchars($t->getNombre()); ?></a><?php } else { echo htmlspecialchars($t->getNombre()); } ?><br><small><?php echo htmlspecialchars($partido['ronda']->getNombre()); ?></small></td><td><?php echo htmlspecialchars($desde['rival']->getNombreVisible()); ?></td><td class="num marcador"><?php echo htmlspecialchars($desde['marcador']); ?></td><td><?php echo $palabra[$desde['desenlace']] . ($desde['walkover'] ? ' por W.O.' : ''); ?></td></tr>
<?php   } ?>
    </tbody>
  </table>
  </div>
<?php } ?>
  </section>
  <section class="tarjeta" id="mis-equipos">
  <h2>Mis equipos</h2>
<?php if ($equipos === null) { ?>
  <p>La lista de equipos no se puede leer por ahora.</p>
<?php } elseif (empty($equipos)) { ?>
  <p>Ningún equipo con esta cuenta de capitán. Con un equipo, se pide lugar en una liga desde su página.</p>
<?php } else { ?>
<?php   foreach ($equipos as $equipo) {
          $id_e  = (int)$equipo->getIdEquipo();
          $nom_e = htmlspecialchars($equipo->getNombre());
          $integ = isset($integrantes_de[$id_e]) ? $integrantes_de[$id_e] : null;
          $error_i = isset($errores_integrante[$id_e]) ? $errores_integrante[$id_e] : null;
          $valor_i = isset($valores_integrante[$id_e]) ? $valores_integrante[$id_e] : array('correo' => '', 'dorsal' => ''); ?>
  <div class="equipo-propio" id="equipo-<?php echo $id_e; ?>">
    <h3><?php echo $nom_e; ?></h3>
    <p><small><?php echo ($equipo->getCiudad() === null || $equipo->getCiudad() === '') ? 'Capitanía de esta cuenta' : htmlspecialchars($equipo->getCiudad()) . ' · capitanía de esta cuenta'; ?></small></p>
<?php     if ($integ === null) { ?>
    <p>Los integrantes no se pueden leer por ahora.</p>
<?php     } else { ?>
    <ul class="lista-apilada integrantes">
<?php       foreach ($integ as $integrante) {
              $persona_i = $integrante->getUsuario();
              $id_p = (int)$persona_i->getIdUsuario();
              $nom_p = htmlspecialchars($persona_i->getNombreCompletoVisible());
              $es_capitan = ($id_p === (int)$usuario->getIdUsuario()); ?>
      <li>
        <span><strong><?php echo $nom_p; ?></strong><?php if ($es_capitan) { echo ' · capitán'; } ?> · <?php echo ($integrante->getDorsal() === null) ? 'sin dorsal' : 'dorsal ' . (int)$integrante->getDorsal(); ?></span>
        <span class="acciones-integrante">
          <form class="dorsal-integrante" action="<?php echo htmlspecialchars($ruta_perfil); ?>#mis-torneos" method="post">
            <input type="hidden" name="accion" value="dorsal_integrante">
            <input type="hidden" name="id_equipo" value="<?php echo $id_e; ?>">
            <input type="hidden" name="id_usuario" value="<?php echo $id_p; ?>">
            <?php echo campoCsrf(); ?>
            <label>Dorsal<span class="visualmente-oculto"> de <?php echo $nom_p; ?></span><input type="number" name="dorsal" value="<?php echo ($integrante->getDorsal() === null) ? '' : (int)$integrante->getDorsal(); ?>" min="0" max="99" inputmode="numeric"></label>
            <button class="btn" type="submit">Guardar<span class="visualmente-oculto"> el dorsal de <?php echo $nom_p; ?></span></button>
          </form>
<?php         if (!$es_capitan) { ?>
          <form class="baja-integrante" action="<?php echo htmlspecialchars($ruta_perfil); ?>#mis-torneos" method="post">
            <input type="hidden" name="accion" value="baja_integrante">
            <input type="hidden" name="id_equipo" value="<?php echo $id_e; ?>">
            <input type="hidden" name="id_usuario" value="<?php echo $id_p; ?>">
            <?php echo campoCsrf(); ?>
            <button class="btn" type="submit">Dar de baja<span class="visualmente-oculto"> a <?php echo $nom_p; ?> de <?php echo $nom_e; ?></span></button>
          </form>
<?php         } ?>
        </span>
      </li>
<?php       } ?>
    </ul>
<?php     } ?>
    <form class="sumar-integrante" action="<?php echo htmlspecialchars($ruta_perfil); ?>#mis-torneos" method="post">
      <input type="hidden" name="accion" value="sumar_integrante">
      <input type="hidden" name="id_equipo" value="<?php echo $id_e; ?>">
      <?php echo campoCsrf(); ?>
      <div class="grilla">
        <div class="campo">
          <label>Correo de la cuenta<input type="email" name="correo" value="<?php echo htmlspecialchars($valor_i['correo']); ?>" required maxlength="120" spellcheck="false" autocapitalize="off" aria-describedby="ayuda-correo-<?php echo $id_e; ?><?php if ($error_i !== null) { echo ' error-integrante-' . $id_e; } ?>"<?php if ($error_i !== null) { echo ' aria-invalid="true"'; } ?>></label>
          <p class="ayuda-campo" id="ayuda-correo-<?php echo $id_e; ?>">La de quien se suma: una cuenta abierta en Stadion.</p>
        </div>
        <div class="campo">
          <label>Dorsal<input type="number" name="dorsal" value="<?php echo htmlspecialchars($valor_i['dorsal']); ?>" min="0" max="99" inputmode="numeric" aria-describedby="ayuda-dorsal-<?php echo $id_e; ?>"></label>
          <p class="ayuda-campo" id="ayuda-dorsal-<?php echo $id_e; ?>">De 0 a 99, sin repetir en el equipo. Opcional.</p>
        </div>
      </div>
<?php     if ($error_i !== null) { ?>
      <p class="error-campo" id="error-integrante-<?php echo $id_e; ?>"><?php echo htmlspecialchars($error_i); ?></p>
<?php     } ?>
      <button class="btn" type="submit">Sumar integrante<span class="visualmente-oculto"> a <?php echo $nom_e; ?></span></button>
    </form>
  </div>
<?php   } ?>
  <p><a href="<?php echo $ruta_publica; ?>/torneos.php">Ver las ligas con la inscripción abierta <span aria-hidden="true">→</span></a></p>
<?php } ?>
<?php if (!empty($equipos_ajenos)) { ?>
  <h3>Integrante de</h3>
  <ul class="lista-apilada">
<?php   foreach ($equipos_ajenos as $ajeno) {
          $cap = $ajeno['equipo']->getCapitan(); ?>
    <li><span><?php echo htmlspecialchars($ajeno['equipo']->getNombre()); ?></span><small><?php echo ($ajeno['dorsal'] === null) ? 'Sin dorsal' : 'Dorsal ' . (int)$ajeno['dorsal']; ?><?php if ($cap !== null) { echo ' · capitanía de ' . htmlspecialchars($cap->getNombreCompletoVisible()); } ?></small></li>
<?php   } ?>
  </ul>
<?php } ?>
  <form class="crear-equipo" action="<?php echo htmlspecialchars($ruta_perfil); ?>#mis-torneos" method="post">
    <input type="hidden" name="accion" value="crear_equipo">
    <?php echo campoCsrf(); ?>
    <div class="grilla">
      <div class="campo">
        <label>Nombre del equipo<input type="text" name="nombre_equipo" value="<?php echo htmlspecialchars($valores_equipo['nombre']); ?>" required minlength="2" maxlength="40" aria-describedby="ayuda-nombre-equipo<?php if (isset($errores_equipo['nombre'])) { echo ' error-nombre-equipo'; } ?>"<?php if (isset($errores_equipo['nombre'])) { echo ' aria-invalid="true"'; } ?>></label>
        <p class="ayuda-campo" id="ayuda-nombre-equipo">Entre 2 y 40 caracteres, distinto de los equipos que ya existen.</p>
<?php if (isset($errores_equipo['nombre'])) { ?>
        <p class="error-campo" id="error-nombre-equipo"><?php echo htmlspecialchars($errores_equipo['nombre']); ?></p>
<?php } ?>
      </div>
      <div class="campo">
        <label>Ciudad<input type="text" name="ciudad_equipo" value="<?php echo htmlspecialchars($valores_equipo['ciudad']); ?>" maxlength="40" aria-describedby="ayuda-ciudad-equipo<?php if (isset($errores_equipo['ciudad'])) { echo ' error-ciudad-equipo'; } ?>"<?php if (isset($errores_equipo['ciudad'])) { echo ' aria-invalid="true"'; } ?>></label>
        <p class="ayuda-campo" id="ayuda-ciudad-equipo">Opcional.</p>
<?php if (isset($errores_equipo['ciudad'])) { ?>
        <p class="error-campo" id="error-ciudad-equipo"><?php echo htmlspecialchars($errores_equipo['ciudad']); ?></p>
<?php } ?>
      </div>
    </div>
    <button class="btn" type="submit">Armar el equipo</button>
  </form>
  </section>
</div>

<div class="vista" id="rendimiento">
  <div class="pestanas"><a href="#datos">Datos</a><a href="#mis-torneos">Mis torneos</a><a href="#rendimiento" class="activo" aria-current="page">Rendimiento</a></div>
  <section class="tarjeta">
  <h2>Rendimiento físico</h2>
<?php if ($mediciones === null) { ?>
  <p>Las mediciones no se pueden leer por ahora.</p>
<?php } ?>
  <div class="grilla">
<?php foreach (Medicion::pruebas() as $prueba => $d) {
        $lista  = $por_prueba[$prueba];
        $ultima = empty($lista) ? null : $lista[count($lista) - 1]; ?>
<div class="tarjeta"><span class="etiqueta"><?php echo htmlspecialchars($d['modelo']); ?></span><h3><?php echo htmlspecialchars($d['indicador']); ?></h3><p class="medida"><span class="valor"><?php echo ($ultima === null) ? '—' : Medicion::formatear($prueba, $ultima->indicador()); ?></span><span class="unidad"><?php echo htmlspecialchars($d['unidad']); ?></span></p><p><small><?php echo empty($lista) ? 'Sin mediciones' : plural(count($lista), 'medición', 'mediciones') . ' · la última, el ' . htmlspecialchars(fechaTexto($ultima->getFecha(), true)); ?></small></p></div>
<?php } ?>
  </div>
  </section>
<?php foreach (Medicion::pruebas() as $prueba => $d) {
        if (count($por_prueba[$prueba]) < 2) { continue; } ?>
  <section class="tarjeta">
  <h3><?php echo htmlspecialchars($d['indicador']); ?></h3>
  <span class="etiqueta"><?php echo plural(count($por_prueba[$prueba]), 'medición', 'mediciones'); ?> · <?php echo htmlspecialchars($d['unidad']); ?></span>
  <?php echo graficoEvolucion($por_prueba[$prueba], $prueba); ?>
  </section>
<?php } ?>
<?php $con_comparacion = false;
      foreach ($comparaciones as $c) { if (!empty($c)) { $con_comparacion = true; } }
      if ($con_comparacion) { ?>
  <section class="tarjeta">
  <h3>Comparado</h3>
  <p><small>La mejor marca de cada cuenta.</small></p>
  <div class="grilla">
<?php   foreach (Medicion::pruebas() as $prueba => $d) {
          $lista = isset($comparaciones[$prueba]) ? $comparaciones[$prueba] : null;
          if (empty($lista)) { continue; }
          # Los 5 primeros, y esta cuenta si queda mas abajo.
          $mostrar = array();
          foreach ($lista as $i => $fila) {
              $propia = ((int)$fila['usuario']->getIdUsuario() === (int)$usuario->getIdUsuario());
              if ($i < 5 || $propia) { $mostrar[$i] = $fila; }
          } ?>
  <div>
  <span class="etiqueta"><?php echo htmlspecialchars($d['indicador']); ?> · <?php echo htmlspecialchars($d['corta']); ?></span>
  <table>
    <tbody>
<?php     foreach ($mostrar as $i => $fila) {
            $propia = ((int)$fila['usuario']->getIdUsuario() === (int)$usuario->getIdUsuario()); ?>
      <tr<?php if ($propia) { echo ' class="clasifica"'; } ?>><td class="pos num"><?php echo $i + 1; ?></td><td><?php echo htmlspecialchars(nombreCorto($fila['usuario'])); ?><?php if ($propia) { ?><span class="visualmente-oculto"> · esta cuenta</span><?php } ?><?php if ($fila['usuario']->esDeMuestra()) { echo ' ' . marcaMuestra(); } ?></td><td class="num"><?php echo Medicion::formatear($prueba, $fila['mejor']->indicador()); ?></td></tr>
<?php     } ?>
    </tbody>
  </table>
  </div>
<?php   } ?>
  </div>
  </section>
<?php } ?>
  <section class="tarjeta" id="cargar-medicion">
  <h3>Cargar una medición</h3>
  <div class="grilla">
<?php foreach (Medicion::pruebas() as $prueba => $d) {
        $error = isset($errores_medicion[$prueba]) ? $errores_medicion[$prueba] : null;
        $describe = $error === null ? '' : ' aria-describedby="error-medicion-' . $prueba . '"';
        $invalido = $error === null ? '' : ' aria-invalid="true"'; ?>
  <form class="medicion" action="<?php echo htmlspecialchars($ruta_perfil); ?>#rendimiento" method="post">
    <input type="hidden" name="accion" value="medicion">
    <input type="hidden" name="prueba" value="<?php echo $prueba; ?>">
    <?php echo campoCsrf(); ?>
    <fieldset>
      <legend><?php echo htmlspecialchars($d['indicador']); ?></legend>
      <label>Fecha<input type="date" name="fecha" value="<?php echo $vm($prueba, 'fecha'); ?>" max="<?php echo htmlspecialchars($hoy); ?>" required<?php echo $describe . $invalido; ?>></label>
<?php   if ($prueba === 'reaccion') { ?>
      <label>Regla (cm)<input type="number" name="distancia" value="<?php echo $vm($prueba, 'distancia'); ?>" min="0.5" max="30" step="0.1" inputmode="decimal" required<?php echo $describe . $invalido; ?>></label>
<?php   } else { ?>
      <label><?php echo ($prueba === 'sprint') ? 'Tiempo en 10 m (s)' : 'Tiempo de vuelo (s)'; ?><input type="number" name="tiempo" value="<?php echo $vm($prueba, 'tiempo'); ?>" min="<?php echo ($prueba === 'sprint') ? '1' : '0.1'; ?>" max="<?php echo ($prueba === 'sprint') ? '6' : '1'; ?>" step="0.01" inputmode="decimal" required<?php echo $describe . $invalido; ?>></label>
      <label>Masa (kg)<input type="number" name="masa" value="<?php echo $vm($prueba, 'masa'); ?>" min="20" max="200" step="0.1" inputmode="decimal" required<?php echo $describe . $invalido; ?>></label>
<?php   } ?>
<?php   if ($error !== null) { ?>
      <p class="error-campo" id="error-medicion-<?php echo $prueba; ?>"><?php echo htmlspecialchars($error); ?></p>
<?php   } ?>
    </fieldset>
    <button class="btn" type="submit">Guardar<span class="visualmente-oculto"> la medición de <?php echo htmlspecialchars(mb_strtolower($d['indicador'], 'UTF-8')); ?></span></button>
  </form>
<?php } ?>
  </div>
  </section>
<?php if (!empty($mediciones)) { ?>
  <section class="tarjeta" id="mis-mediciones">
  <h3>Mediciones</h3>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Mediciones">
  <table>
    <thead><tr><th>Fecha</th><th>Indicador</th><th class="num">Valor</th><th>Datos</th><th><span class="visualmente-oculto">Acción</span></th></tr></thead>
    <tbody>
<?php   $pruebas = Medicion::pruebas();
        foreach (array_reverse($mediciones) as $m) {
          $d = $pruebas[$m->getPrueba()]; ?>
      <tr><td class="fecha"><?php echo htmlspecialchars(diaMes($m->getFecha()) . '/' . substr($m->getFecha(), 0, 4)); ?></td><td><?php echo htmlspecialchars($d['indicador']); ?></td><td class="num"><?php echo Medicion::formatear($m->getPrueba(), $m->indicador()) . ' ' . htmlspecialchars($d['corta']); ?></td><td><?php echo datoCrudo($m); ?></td><td><form class="borrar-medicion" action="<?php echo htmlspecialchars($ruta_perfil); ?>#rendimiento" method="post"><input type="hidden" name="accion" value="borrar_medicion"><input type="hidden" name="id_medicion" value="<?php echo (int)$m->getIdMedicion(); ?>"><?php echo campoCsrf(); ?><button class="btn" type="submit" aria-label="Borrar la medición de <?php echo htmlspecialchars(mb_strtolower($d['indicador'], 'UTF-8')); ?> del <?php echo htmlspecialchars(fechaTexto($m->getFecha(), true)); ?>">Borrar</button></form></td></tr>
<?php   } ?>
    </tbody>
  </table>
  </div>
  </section>
<?php } ?>
  <section class="tarjeta">
  <span class="etiqueta">Instrumentos</span>
  <p>Regla de 30 cm, cronómetro del celular y balanza.</p>
  </section>
</div>

<div class="vista" id="datos">
  <div class="pestanas"><a href="#datos" class="activo" aria-current="page">Datos</a><a href="#mis-torneos">Mis torneos</a><a href="#rendimiento">Rendimiento</a></div>
  <section class="tarjeta">
  <h2>Datos del perfil</h2>
  <form class="datos-perfil" action="<?php echo htmlspecialchars($ruta_perfil); ?>" method="post">
    <input type="hidden" name="accion" value="datos">
    <?php echo campoCsrf(); ?>
    <div class="grilla">
      <label>Nombre<input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario->getNombre()); ?>" required minlength="2" maxlength="40"></label>
      <label>Apellido<input type="text" name="apellido" value="<?php echo htmlspecialchars($usuario->getApellido()); ?>" required minlength="2" maxlength="40"></label>
      <div class="campo">
        <label>Alias en juego<input type="text" name="alias" value="<?php echo htmlspecialchars((string)$usuario->getAlias()); ?>" maxlength="20" pattern="[A-Za-z0-9_]+" title="Letras, números y guion bajo" aria-describedby="ayuda-alias" spellcheck="false" autocapitalize="off"></label>
        <p class="ayuda-campo" id="ayuda-alias">Letras, números y guion bajo; hasta 20 caracteres.</p>
      </div>
    </div>
    <label>Presentación<textarea name="presentacion" rows="3" maxlength="300"><?php echo htmlspecialchars((string)$usuario->getPresentacion()); ?></textarea></label>
    <div class="fila" style="justify-content:flex-end"><button class="btn" type="reset">Descartar</button><button class="btn btn-primario" type="submit">Guardar cambios</button></div>
  </form>
  </section>
  <section class="tarjeta">
  <h2>Imágenes</h2>
  <p class="intro">JPG, PNG o WEBP, hasta 2 MB y 4000 píxeles de lado. Cada imagen nueva reemplaza a la anterior.</p>
  <div class="grilla">
    <form class="subida" action="<?php echo htmlspecialchars($ruta_perfil); ?>" method="post" enctype="multipart/form-data">
      <input type="hidden" name="accion" value="foto">
      <?php echo campoCsrf(); ?>
      <label>Foto de perfil<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required></label>
      <button class="btn" type="submit">Subir foto</button>
    </form>
    <form class="subida" action="<?php echo htmlspecialchars($ruta_perfil); ?>" method="post" enctype="multipart/form-data">
      <input type="hidden" name="accion" value="portada">
      <?php echo campoCsrf(); ?>
      <label>Portada<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required></label>
      <button class="btn" type="submit">Subir portada</button>
    </form>
  </div>
  </section>
<?php # El cambio de contrasena (tercera entrega): pide la actual, y la
      # nueva pasa por las reglas del alta. Los errores van debajo del
      # formulario, y cada campo los nombra en aria-describedby. ?>
<?php
  $describe_clave = empty($errores_clave) ? '' : ' error-clave';
  $invalido_clave = empty($errores_clave) ? '' : ' aria-invalid="true"'; ?>
  <section class="tarjeta" id="contrasena">
  <h2>Contraseña</h2>
  <form class="cambiar-clave" action="<?php echo htmlspecialchars($ruta_perfil); ?>" method="post">
    <input type="hidden" name="accion" value="clave">
    <?php echo campoCsrf(); ?>
    <div class="grilla">
      <label>Contraseña actual<input type="password" name="clave_actual" required autocomplete="current-password"<?php echo empty($errores_clave) ? '' : ' aria-describedby="error-clave"'; ?><?php echo $invalido_clave; ?>></label>
      <div class="campo">
        <label>Contraseña nueva<input type="password" name="clave_nueva" required minlength="10" autocomplete="new-password" aria-describedby="ayuda-clave-nueva<?php echo $describe_clave; ?>"<?php echo $invalido_clave; ?>></label>
        <p class="ayuda-campo" id="ayuda-clave-nueva">Mínimo 10 caracteres, con una mayúscula, una minúscula y un número; sin el nombre, el apellido ni el correo.</p>
      </div>
      <label>Repetir la nueva<input type="password" name="clave_repetir" required minlength="10" autocomplete="new-password"<?php echo empty($errores_clave) ? '' : ' aria-describedby="error-clave"'; ?><?php echo $invalido_clave; ?>></label>
    </div>
<?php if (!empty($errores_clave)) { ?>
    <p class="error-campo" id="error-clave"><?php echo htmlspecialchars(implode(' ', $errores_clave)); ?></p>
<?php } ?>
    <div class="fila" style="justify-content:flex-end"><button class="btn btn-primario" type="submit">Cambiar la contraseña</button></div>
  </form>
  </section>
</div>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">La cuenta</span>
<?php # Dato arriba y valor abajo, como los roles: en la columna lateral
      # un correo largo no entra al lado de su nombre. ?>
  <ul class="lista-apilada">
    <li><small>Correo</small><span class="valor-cuenta"><?php echo htmlspecialchars($usuario->getCorreo()); ?></span></li>
<?php if ($desde !== '') { ?>
    <li><small>Alta</small><span class="valor-cuenta"><?php echo htmlspecialchars(ucfirst(str_replace('miembro desde ', '', $desde))); ?></span></li>
<?php } ?>
  </ul>
  <p><small>El correo identifica la cuenta y no se cambia desde acá.</small></p>
</div>
<div class="tarjeta" id="roles">
  <span class="etiqueta">Roles</span>
<?php
  $roles = $usuario->getRoles();
  if (empty($roles)) { ?>
  <p>Sin roles asignados.</p>
<?php } else { ?>
<?php   # Nombre arriba y descripcion abajo, no en dos columnas: en la
        # columna lateral no entran las dos una al lado de la otra. ?>
  <ul class="lista-apilada">
<?php   foreach ($roles as $rol) { ?>
    <li><span><?php echo htmlspecialchars($rol->getNombre()); ?></span><small><?php echo htmlspecialchars($rol->getDescripcion()); ?></small></li>
<?php   } ?>
  </ul>
<?php } ?>
<?php # El rol de organizador se pide desde aca y lo aprueba la
      # administracion. Quien ya lo tiene no ve nada. ?>
<?php if (!$usuario->tieneRol('organizador')) { ?>
  <div class="pedido-rol">
<?php   # Llega de crear.php o del panel sin el rol: el aviso va aca, al
        # lado del boton, que es a donde lleva el ancla #roles. ?>
<?php   if ($aviso_organizador) { ?>
    <p class="aviso-rol" role="alert">Crear y organizar ligas pide el rol de organizador. Se pide acá, y lo aprueba la administración.</p>
<?php   } ?>
<?php   if ($pedido_organizador !== null && $pedido_organizador->estaPendiente()) { ?>
    <p><strong>Organizador</strong> · pedido en revisión desde el <?php echo htmlspecialchars(fechaLarga($pedido_organizador->getFechaPedido(), $meses)); ?>.</p>
<?php   } else { ?>
<?php     if ($pedido_organizador !== null && $pedido_organizador->estaRechazado()) { ?>
    <p><small>El último pedido de organizador queda rechazado el <?php echo htmlspecialchars(fechaLarga($pedido_organizador->getFechaResolucion(), $meses)); ?>. Puede pedirse otra vez.</small></p>
<?php     } ?>
    <form class="pedir-rol" action="<?php echo htmlspecialchars($ruta_perfil); ?>" method="post">
      <input type="hidden" name="accion" value="pedir_rol">
      <?php echo campoCsrf(); ?>
      <button class="btn" type="submit">Pedir el rol de organizador</button>
    </form>
    <p><small>Lo aprueba la administración.</small></p>
<?php   } ?>
  </div>
<?php } ?>
<?php if ($usuario->tieneRol('administrador')) { ?>
  <p><a href="<?php echo htmlspecialchars($ruta_admin); ?>">Administración <span aria-hidden="true">→</span></a></p>
<?php } ?>
</div>
</aside>
<footer>
  <div class="marca-agon"><svg width="26" height="26" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <mask id="lente-mask">
    <path d="M 100,34 A 81.06 81.06 0 0 1 100,166 A 81.06 81.06 0 0 1 100,34 Z" fill="white"/>
    <rect x="94" y="87" width="12" height="26" rx="6" fill="black"/>
  </mask>
  <rect x="0" y="0" width="200" height="200" fill="currentColor" mask="url(#lente-mask)"/>
</svg><span>Stadion es un producto de Agón · Montevideo, 2026</span></div>
</footer>
</div>
<button type="button" class="interruptor-tema" id="interruptor-tema" aria-label="Modo noche" aria-pressed="false">
<svg width="66" height="66" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <circle cx="33" cy="33" r="32" fill="#FBF9F4"/>
  <circle cx="33" cy="33" r="32" fill="none" stroke="#D6CFC1" stroke-width="1"/>
  <circle cx="33" cy="33" r="27" fill="none" stroke="#E3DDD0" stroke-width="1"/>
  <g stroke="#8A8478" stroke-width="1.1">
    <path d="M33,2.6 v4"/><path d="M33,59.4 v4"/><path d="M2.6,33 h4"/><path d="M59.4,33 h4"/>
    <path d="M11.5,11.5 l2.8,2.8"/><path d="M54.5,54.5 l-2.8,-2.8"/><path d="M11.5,54.5 l2.8,-2.8"/><path d="M54.5,11.5 l-2.8,2.8"/>
  </g>
  <path d="M33,17 A16,16 0 0,0 33,49 Z" fill="#1E1C18"/>
  <circle cx="33" cy="33" r="16" fill="none" stroke="#1E1C18" stroke-width="1.6"/>
  <path d="M41,25.5 l1.6,3.2 l3.2,1.6 l-3.2,1.6 l-1.6,3.2 l-1.6,-3.2 l-3.2,-1.6 l3.2,-1.6 Z" fill="#4F5F35"/>
</svg>
<svg width="66" height="66" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <circle cx="33" cy="33" r="32" fill="#14130F"/>
  <circle cx="33" cy="33" r="32" fill="none" stroke="#3A362E" stroke-width="1"/>
  <circle cx="33" cy="33" r="27" fill="none" stroke="#2A2822" stroke-width="1"/>
  <g stroke="#6E675A" stroke-width="1.1">
    <path d="M33,2.6 v4"/><path d="M33,59.4 v4"/><path d="M2.6,33 h4"/><path d="M59.4,33 h4"/>
    <path d="M11.5,11.5 l2.8,2.8"/><path d="M54.5,54.5 l-2.8,-2.8"/><path d="M11.5,54.5 l2.8,-2.8"/><path d="M54.5,11.5 l2.8,2.8"/>
  </g>
  <path d="M33,17 A16,16 0 0,1 33,49 Z" fill="#EDE7DA"/>
  <circle cx="33" cy="33" r="16" fill="none" stroke="#EDE7DA" stroke-width="1.6"/>
  <path d="M25,25.5 l1.6,3.2 l3.2,1.6 l-3.2,1.6 l-1.6,3.2 l-1.6,-3.2 l-3.2,-1.6 l3.2,-1.6 Z" fill="#8CA368"/>
</svg>
</button>
</body>
</html>
