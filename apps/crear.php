<?php
# =====================================================================
# Vista: nueva liga
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La incluye crearController.php. Recibe:
#   $organizador    la cuenta de la sesion (Usuario, con el rol)
#   $disciplinas    arreglo de Disciplina, indexado por id
#   $valores        lo que muestra cada campo (lo que llego, o los
#                   valores de una liga nueva)
#   $hoy            la fecha de hoy en Montevideo (AAAA-MM-DD), el
#                   minimo de la fecha de inicio
#   $errores        avisos generales (arreglo)
#   $errores_campo  campo => mensaje, para mostrar al lado del campo
#   $ruta_publica, $ruta_perfil, $ruta_crear, $ruta_panel   direcciones
#
# Un campo con error lleva aria-invalid="true" (que el CSS dibuja con el
# borde punteado, igual que :user-invalid), y su mensaje debajo, con un
# id que el campo nombra en aria-describedby junto con su ayuda. Arriba
# va la lista de los errores, cada uno con un enlace a su campo, dentro
# de role="alert"; y el titulo empieza con "Aviso ·".
#
# Todo lo que viene del formulario se imprime con htmlspecialchars.
# =====================================================================

if (!isset($errores)) { $errores = array(); }
if (!isset($errores_campo)) { $errores_campo = array(); }

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/pie.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/recursos.php';
require_once __DIR__ . '/models/ConfiguracionTorneo.php';

# Los atributos de accesibilidad de un campo: su ayuda (si tiene) y su
# error (si tiene). Devuelve el texto listo para poner en la etiqueta.
function atributosCampo($campo, $con_ayuda, $errores_campo)
{
    $describe = array();
    if ($con_ayuda) {
        $describe[] = 'ayuda-' . $campo;
    }
    $atributos = '';
    if (isset($errores_campo[$campo])) {
        $describe[] = 'error-' . $campo;
        $atributos .= ' aria-invalid="true"';
    }
    if (!empty($describe)) {
        $atributos .= ' aria-describedby="' . implode(' ', $describe) . '"';
    }
    return $atributos;
}

function errorCampo($campo, $errores_campo)
{
    if (!isset($errores_campo[$campo])) {
        return '';
    }
    return '<p class="error-campo" id="error-' . $campo . '">' . htmlspecialchars($errores_campo[$campo]) . '</p>';
}

$v = array();
foreach ($valores as $campo => $valor) {
    $v[$campo] = htmlspecialchars($valor);
}

# El nombre de cada campo en la lista de errores de arriba.
$nombres_campo = array('nombre' => 'Nombre', 'disciplina' => 'Juego o deporte', 'cupo' => 'Cupo',
                       'vueltas' => 'Vueltas', 'victoria' => 'Puntos por victoria',
                       'empate' => 'Puntos por empate', 'derrota' => 'Puntos por derrota',
                       'desempate' => 'Desempate', 'inicio' => 'Fecha de inicio');
# El primer control de cada campo, para el enlace de la lista.
$destino_campo = array('vueltas' => 'vueltas-una');

# Lo que arma la liga con el cupo completo, con los valores del
# formulario tal como estan (ver ConfiguracionTorneo).
$cupo = ctype_digit($valores['cupo']) ? (int)$valores['cupo'] : 16;
if ($cupo < 4 || $cupo > 32) { $cupo = 16; }
$muestra_config = new ConfiguracionTorneo(null, 3, 1, 0, 1, 0, ($valores['vueltas'] === 'dos') ? 1 : 0);
$fechas_previstas  = $muestra_config->rondasDeLiga($cupo);
$partidos_previstos = $muestra_config->enfrentamientosDeLiga($cupo);

$hay_errores = !empty($errores) || !empty($errores_campo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title><?php if ($hay_errores) { echo 'Aviso · '; } ?>Nueva liga · Stadion</title>
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
  <p class="etiqueta">Panel / Nueva liga</p>
  <h1>Nueva liga</h1>
  <p class="intro">Todos contra todos. La liga nace con la inscripción abierta; el fixture se arma al cerrarla.</p>
</section>
<?php if ($hay_errores) { ?>
<div role="alert">
<ul class="avisos">
<?php   foreach ($errores as $error) { ?>
  <li><?php echo htmlspecialchars($error); ?></li>
<?php   } ?>
<?php   foreach ($errores_campo as $campo => $error) {
          $destino = isset($destino_campo[$campo]) ? $destino_campo[$campo] : 'campo-' . $campo; ?>
  <li><a href="#<?php echo $destino; ?>"><?php echo htmlspecialchars($nombres_campo[$campo]); ?></a>: <?php echo htmlspecialchars($error); ?></li>
<?php   } ?>
</ul>
</div>
<?php } ?>
<form action="<?php echo htmlspecialchars($ruta_crear); ?>" method="post">
  <?php echo campoCsrf(); ?>
  <fieldset>
    <legend>Datos de la liga</legend>
    <div class="campo">
      <label>Nombre<input type="text" id="campo-nombre" name="nombre" value="<?php echo $v['nombre']; ?>" required minlength="4" maxlength="80"<?php echo atributosCampo('nombre', true, $errores_campo); ?>></label>
      <p class="ayuda-campo" id="ayuda-nombre">Entre 4 y 80 caracteres.</p>
      <?php echo errorCampo('nombre', $errores_campo); ?>
    </div>
    <div class="grilla">
      <div class="campo">
        <label>Juego o deporte<select id="campo-disciplina" name="disciplina" required<?php echo atributosCampo('disciplina', false, $errores_campo); ?>>
          <option value="">Elegir…</option>
<?php foreach ($disciplinas as $id => $disciplina) { ?>
          <option value="<?php echo (int)$id; ?>"<?php if ((string)$id === $valores['disciplina']) { echo ' selected'; } ?>><?php echo htmlspecialchars($disciplina->getNombre()); ?></option>
<?php } ?>
        </select></label>
        <?php echo errorCampo('disciplina', $errores_campo); ?>
      </div>
      <div class="campo">
        <label>Cupo<input type="number" id="campo-cupo" name="cupo" value="<?php echo $v['cupo']; ?>" min="4" max="32" required<?php echo atributosCampo('cupo', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-cupo">De 4 a 32 equipos.</p>
        <?php echo errorCampo('cupo', $errores_campo); ?>
      </div>
      <div class="campo">
        <label>Fecha de inicio<input type="date" id="campo-inicio" name="inicio" value="<?php echo $v['inicio']; ?>" min="<?php echo htmlspecialchars($hoy); ?>"<?php echo atributosCampo('inicio', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-inicio">De hoy en adelante. Opcional: puede quedar a definir.</p>
        <?php echo errorCampo('inicio', $errores_campo); ?>
      </div>
    </div>
  </fieldset>
  <fieldset<?php if (isset($errores_campo['vueltas'])) { echo ' aria-describedby="error-vueltas"'; } ?>>
    <legend>Vueltas</legend>
    <div class="opciones">
      <label class="opcion"><input type="radio" id="vueltas-una" name="vueltas" value="una"<?php if ($valores['vueltas'] !== 'dos') { echo ' checked'; } ?>><span><strong>Una vuelta</strong><br>Cada equipo se cruza una vez con cada rival.</span></label>
      <label class="opcion"><input type="radio" id="vueltas-dos" name="vueltas" value="dos"<?php if ($valores['vueltas'] === 'dos') { echo ' checked'; } ?>><span><strong>Ida y vuelta</strong><br>Dos cruces con cada rival, con la localía al revés en la vuelta.</span></label>
    </div>
    <?php echo errorCampo('vueltas', $errores_campo); ?>
  </fieldset>
  <fieldset>
    <legend>Puntaje y desempate</legend>
    <div class="grilla">
      <div class="campo">
        <label>Puntos por victoria<input type="number" id="campo-victoria" name="victoria" value="<?php echo $v['victoria']; ?>" min="1" max="10" required<?php echo atributosCampo('victoria', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-victoria">De 1 a 10.</p>
        <?php echo errorCampo('victoria', $errores_campo); ?>
      </div>
      <div class="campo">
        <label>Puntos por empate<input type="number" id="campo-empate" name="empate" value="<?php echo $v['empate']; ?>" min="0" max="10" required<?php echo atributosCampo('empate', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-empate">De 0 a 10, sin pasar la victoria.</p>
        <?php echo errorCampo('empate', $errores_campo); ?>
      </div>
      <div class="campo">
        <label>Puntos por derrota<input type="number" id="campo-derrota" name="derrota" value="<?php echo $v['derrota']; ?>" min="0" max="10" required<?php echo atributosCampo('derrota', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-derrota">De 0 a 10, sin pasar el empate.</p>
        <?php echo errorCampo('derrota', $errores_campo); ?>
      </div>
    </div>
    <div class="campo">
      <label>Desempate<select id="campo-desempate" name="desempate" required<?php echo atributosCampo('desempate', true, $errores_campo); ?>>
<?php foreach (ConfiguracionTorneo::criterios() as $clave => $texto) { ?>
        <option value="<?php echo $clave; ?>"<?php if ($clave === $valores['desempate']) { echo ' selected'; } ?>><?php echo htmlspecialchars($texto); ?></option>
<?php } ?>
      </select></label>
      <p class="ayuda-campo" id="ayuda-desempate">Entre dos con los mismos puntos. Los tantos son los goles, los mapas o los puntos de cada partido.</p>
      <?php echo errorCampo('desempate', $errores_campo); ?>
    </div>
  </fieldset>
  <div class="fila" style="justify-content:space-between"><a class="btn" href="<?php echo $ruta_publica; ?>/panel.php"><span aria-hidden="true">←</span> Volver al panel</a><button class="btn btn-primario" type="submit">Crear liga</button></div>
</form>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">La liga, en breve</span>
  <table><tr><td>Formato</td><td>Liga · <?php echo ($valores['vueltas'] === 'dos') ? 'ida y vuelta' : 'una vuelta'; ?></td></tr><tr><td>Equipos</td><td><?php echo $cupo; ?> como máximo</td></tr><tr><td>Inicio</td><td><?php echo ($valores['inicio'] === '') ? 'a definir' : htmlspecialchars($valores['inicio']); ?></td></tr></table>
</div>
<div class="tarjeta tarjeta-olivo">
  <span class="etiqueta">Con el cupo completo</span>
  <p style="margin:0;font-family:var(--serif);font-size:34px;font-weight:500;line-height:1.1"><?php echo $partidos_previstos; ?></p>
  <p>enfrentamientos, en <?php echo $fechas_previstas; ?> fechas, con <?php echo $cupo; ?> equipos. El fixture se arma en el panel, con la inscripción cerrada.</p>
</div>
</aside>
<?php piePagina(true); ?>
</body>
</html>
