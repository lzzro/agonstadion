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
#                 presentacion, foto y portada
#   Mis torneos   de la base: los torneos en los que compite la persona,
#                 sola o con un equipo
#   Rendimiento   DE MUESTRA: todavia no hay tabla de mediciones. Son
#                 los valores de la maqueta, marcados en pantalla
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

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/models/ImagenSubida.php';

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
  <title><?php if (!empty($errores)) { echo 'Aviso · '; } ?>Perfil · Stadion</title>
  <link rel="icon" href="<?php echo $ruta_publica; ?>/img/stadion.png">
  <link rel="stylesheet" href="<?php echo $ruta_publica; ?>/css/style.css">
  <script src="<?php echo $ruta_publica; ?>/js/tema.js"></script>
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
<nav><a href="<?php echo $ruta_publica; ?>/index.php">Inicio</a><a href="<?php echo $ruta_publica; ?>/torneos.php">Torneos</a><a href="<?php echo $ruta_publica; ?>/calendario.php">Calendario</a><a href="<?php echo $ruta_publica; ?>/torneo.php#posiciones">Posiciones</a><a href="<?php echo $ruta_publica; ?>/panel.html">Organizadores</a></nav>
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
  <div><strong>3</strong><span class="etiqueta">Finales</span><span class="muestra">De muestra</span></div>
  <div><strong style="color:var(--olivo)">2</strong><span class="etiqueta">Kotinos · victorias</span><span class="muestra">De muestra</span></div>
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
</div>

<div class="vista" id="rendimiento">
  <div class="pestanas"><a href="#datos">Datos</a><a href="#mis-torneos">Mis torneos</a><a href="#rendimiento" class="activo" aria-current="page">Rendimiento</a></div>
  <section class="tarjeta">
  <div class="fila" style="justify-content:space-between"><h2>Rendimiento físico</h2><span class="muestra">De muestra</span></div>
  <div class="grilla">
<div class="tarjeta"><span class="etiqueta">Cinemática · caída libre</span><h3>Tiempo de reacción</h3><p class="medida"><span class="valor">187</span><span class="unidad">milisegundos</span></p></div>
<div class="tarjeta"><span class="etiqueta">Dinámica · fuerza neta</span><h3>Aceleración de salida</h3><p class="medida"><span class="valor">3.8</span><span class="unidad">metros / s²</span></p></div>
<div class="tarjeta"><span class="etiqueta">Trabajo y energía</span><h3>Potencia de salto</h3><p class="medida"><span class="valor">612</span><span class="unidad">watts</span></p></div>
  </div>
  </section>
  <section class="tarjeta">
  <div class="fila" style="justify-content:space-between"><div></div><span class="muestra">De muestra</span></div>
  <h3>Tiempo de reacción</h3>
  <span class="etiqueta">Seis mediciones · milisegundos</span>
    <svg class="grafico" viewBox="0 0 560 212" role="img" aria-label="Tiempo de reacción de marzo a septiembre: baja de 230 a 187 milisegundos en seis mediciones.">
      <line class="reja" x1="52" y1="30" x2="530" y2="30"/>
      <text class="rotulo" x="44" y="34" text-anchor="end">240</text>
      <line class="reja" x1="52" y1="80" x2="530" y2="80"/>
      <text class="rotulo" x="44" y="84" text-anchor="end">220</text>
      <line class="reja" x1="52" y1="130" x2="530" y2="130"/>
      <text class="rotulo" x="44" y="134" text-anchor="end">200</text>
      <line class="reja" x1="52" y1="180" x2="530" y2="180"/>
      <text class="rotulo" x="44" y="184" text-anchor="end">180</text>
      <polyline class="trazo" points="60,55.0 152,77.5 244,100.0 336,117.5 428,145.0 520,162.5"/>
      <circle class="punto" cx="60" cy="55.0" r="4"><title>Mar: 230 ms</title></circle>
      <circle class="punto" cx="152" cy="77.5" r="4"><title>Abr: 221 ms</title></circle>
      <circle class="punto" cx="244" cy="100.0" r="4"><title>May: 212 ms</title></circle>
      <circle class="punto" cx="336" cy="117.5" r="4"><title>Jun: 205 ms</title></circle>
      <circle class="punto" cx="428" cy="145.0" r="4"><title>Ago: 194 ms</title></circle>
      <circle class="punto" cx="520" cy="162.5" r="4"><title>Sep: 187 ms</title></circle>
      <text class="rotulo-dato" x="72" y="50" text-anchor="start">230</text>
      <text class="rotulo-dato" x="508" y="151" text-anchor="end">187</text>
      <text class="rotulo" x="60" y="200" text-anchor="middle">Mar</text>
      <text class="rotulo" x="152" y="200" text-anchor="middle">Abr</text>
      <text class="rotulo" x="244" y="200" text-anchor="middle">May</text>
      <text class="rotulo" x="336" y="200" text-anchor="middle">Jun</text>
      <text class="rotulo" x="428" y="200" text-anchor="middle">Ago</text>
      <text class="rotulo" x="520" y="200" text-anchor="middle">Sep</text>
    </svg>
  </section>
  <section class="tarjeta">
  <div class="fila" style="justify-content:space-between"><div></div><span class="muestra">De muestra</span></div>
  <span class="etiqueta">Tiempo de reacción · comparado</span>
  <table>
    <tr><td>M. Ferreira</td><td class="num">164</td></tr>
    <tr class="clasifica"><td><?php echo htmlspecialchars($usuario->getNombreCompletoVisible()); ?></td><td class="num">187</td></tr>
    <tr><td>N. Suárez</td><td class="num">201</td></tr>
    <tr><td>D. Acosta</td><td class="num">219</td></tr>
  </table>
  </section>
  <section class="tarjeta">
  <span class="etiqueta">Materia</span>
  <p>Instrumentos: Cámara lenta del celular (120–240 fps), Tracker para videoanálisis y Phyphox para los sensores de acelerómetro y cronómetro acústico.</p>
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
<div class="tarjeta">
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
