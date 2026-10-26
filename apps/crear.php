<?php
# Vista: nuevo torneo (liga, eliminacion directa o suizo)

if (!isset($errores)) { $errores = array(); }
if (!isset($errores_campo)) { $errores_campo = array(); }

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/pie.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/recursos.php';
require_once __DIR__ . '/models/ConfiguracionTorneo.php';
require_once __DIR__ . '/models/Suizo.php';
require_once __DIR__ . '/models/Llave.php';

# Los atributos de accesibilidad de un campo: su ayuda (si tiene) y su error (si tiene).
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
$nombres_campo = array('formato' => 'Formato', 'rondas' => 'Rondas del suizo', 'nombre' => 'Nombre', 'disciplina' => 'Juego o deporte', 'compite' => 'Compiten', 'cupo' => 'Cupo',
                       'vueltas' => 'Vueltas', 'partido' => 'Partidos', 'victoria' => 'Puntos por victoria',
                       'empate' => 'Puntos por empate', 'derrota' => 'Puntos por derrota',
                       'desempate' => 'Desempate', 'inicio' => 'Fecha de inicio', 'sede' => 'Sede',
                       'clasifican' => 'Clasifican a playoffs');
# El primer control de cada campo, para el enlace de la lista.
if (!isset($formatos_habilitados)) { $formatos_habilitados = array_keys(formatosDeTorneo()); }
$formatos_torneo = array();
foreach (formatosDeTorneo() as $clave => $f) {
    if (in_array($clave, $formatos_habilitados, true)) {
        $formatos_torneo[$clave] = $f;
    }
}
$primer_formato = empty($formatos_torneo) ? 'liga' : array_keys($formatos_torneo)[0];
# "Liga, eliminación directa o suizo", con los que estan habilitados.
$en_minuscula = array('liga' => 'liga', 'eliminacion' => 'eliminación directa', 'suizo' => 'suizo');
$nombres_formatos = array();
foreach (array_keys($formatos_torneo) as $clave) {
    $nombres_formatos[] = $en_minuscula[$clave];
}
$ultimo_formato = array_pop($nombres_formatos);
$lista_formatos = empty($nombres_formatos) ? (string)$ultimo_formato : implode(', ', $nombres_formatos) . ' o ' . $ultimo_formato;
$destino_campo = array('vueltas' => 'vueltas-una', 'partido' => 'partido-empate', 'compite' => 'compite-equipos',
                       'formato' => 'formato-' . $primer_formato);
$formato_elegido = isset($formatos_torneo[$valores['formato']]) ? $valores['formato'] : $primer_formato;
$jugadores = ($valores['compite'] === 'jugadores');
$formatos = formatosDePartido();
$partido_elegido = isset($formatos[$valores['partido']]) ? $valores['partido'] : 'empate';

$cupo = ctype_digit($valores['cupo']) ? (int)$valores['cupo'] : 16;
if ($cupo < 4 || $cupo > 32) { $cupo = 16; }
$muestra_config = new ConfiguracionTorneo(null, 3, 1, 0, 1, 0, ($valores['vueltas'] === 'dos') ? 1 : 0);
$fechas_previstas  = $muestra_config->rondasDeLiga($cupo);
$partidos_previstos = $muestra_config->enfrentamientosDeLiga($cupo);
$palabra_rondas = 'fechas';
if ($formato_elegido === 'eliminacion') {
    # Cada partido deja a uno afuera: hasta el campeon, uno menos que los inscriptos.
    $fechas_previstas  = Llave::cantidadRondas($cupo);
    $partidos_previstos = $cupo - 1;
    $palabra_rondas = 'rondas';
} elseif ($formato_elegido === 'suizo') {
    $fechas_previstas  = ctype_digit($valores['rondas']) ? min((int)$valores['rondas'], Suizo::rondasMaximas($cupo)) : Suizo::rondasSugeridas($cupo);
    $partidos_previstos = $fechas_previstas * intdiv($cupo, 2);
    $palabra_rondas = 'rondas';
}

$hay_errores = !empty($errores) || !empty($errores_campo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title><?php if ($hay_errores) { echo 'Aviso · '; } ?>Nuevo torneo · Stadion</title>
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
  <p class="etiqueta">Panel / Nuevo torneo</p>
  <h1>Nuevo torneo</h1>
  <p class="intro"><?php echo htmlspecialchars(ucfirst($lista_formatos)); ?>. El torneo nace con la inscripción abierta; el calendario se arma al cerrarla.</p>
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
  <fieldset<?php if (isset($errores_campo['formato'])) { echo ' aria-describedby="error-formato"'; } ?>>
    <legend>Formato</legend>
    <div class="opciones">
<?php foreach ($formatos_torneo as $clave => $f) { ?>
      <label class="opcion"><input type="radio" id="formato-<?php echo $clave; ?>" name="formato" value="<?php echo $clave; ?>"<?php if ($clave === $formato_elegido) { echo ' checked'; } ?>><span><strong><?php echo htmlspecialchars($f[0]); ?></strong><br><?php echo htmlspecialchars($f[2]); ?></span></label>
<?php } ?>
    </div>
    <?php echo errorCampo('formato', $errores_campo); ?>
  </fieldset>
  <fieldset>
    <legend>Datos del torneo</legend>
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
        <p class="ayuda-campo" id="ayuda-cupo">De 4 a 32 equipos o jugadores.</p>
        <?php echo errorCampo('cupo', $errores_campo); ?>
      </div>
      <div class="campo">
        <label>Sede<input type="text" id="campo-sede" name="sede" value="<?php echo $v['sede']; ?>" maxlength="80"<?php echo atributosCampo('sede', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-sede">Dónde se juega. Opcional.</p>
        <?php echo errorCampo('sede', $errores_campo); ?>
      </div>
      <div class="campo">
        <label>Fecha de inicio<input type="date" id="campo-inicio" name="inicio" value="<?php echo $v['inicio']; ?>" min="<?php echo htmlspecialchars($hoy); ?>"<?php echo atributosCampo('inicio', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-inicio">De hoy en adelante. Opcional: puede quedar a definir.</p>
        <?php echo errorCampo('inicio', $errores_campo); ?>
      </div>
    </div>
  </fieldset>
  <fieldset<?php if (isset($errores_campo['compite'])) { echo ' aria-describedby="error-compite"'; } ?>>
    <legend>Compiten</legend>
    <div class="opciones">
      <label class="opcion"><input type="radio" id="compite-equipos" name="compite" value="equipos"<?php if (!$jugadores) { echo ' checked'; } ?>><span><strong>Equipos</strong><br>Anotados a mano en el panel, o por pedido de su capitán.</span></label>
      <label class="opcion"><input type="radio" id="compite-jugadores" name="compite" value="jugadores"<?php if ($jugadores) { echo ' checked'; } ?>><span><strong>Jugadores</strong><br>Un torneo individual: cada jugador se anota con su cuenta, desde la página del torneo.</span></label>
    </div>
    <?php echo errorCampo('compite', $errores_campo); ?>
  </fieldset>
  <fieldset<?php if (isset($errores_campo['vueltas'])) { echo ' aria-describedby="error-vueltas"'; } ?>>
    <legend>Vueltas (solo en la liga)</legend>
    <div class="opciones">
      <label class="opcion"><input type="radio" id="vueltas-una" name="vueltas" value="una"<?php if ($valores['vueltas'] !== 'dos') { echo ' checked'; } ?>><span><strong>Una vuelta</strong><br>Cada equipo se cruza una vez con cada rival.</span></label>
      <label class="opcion"><input type="radio" id="vueltas-dos" name="vueltas" value="dos"<?php if ($valores['vueltas'] === 'dos') { echo ' checked'; } ?>><span><strong>Ida y vuelta</strong><br>Dos cruces con cada rival, con la localía al revés en la vuelta.</span></label>
    </div>
    <?php echo errorCampo('vueltas', $errores_campo); ?>
  </fieldset>
  <fieldset<?php if (isset($errores_campo['partido'])) { echo ' aria-describedby="error-partido"'; } ?>>
    <legend>Partidos</legend>
    <div class="opciones">
<?php foreach ($formatos as $clave => $formato) { ?>
      <label class="opcion"><input type="radio" id="partido-<?php echo $clave; ?>" name="partido" value="<?php echo $clave; ?>"<?php if ($clave === $partido_elegido) { echo ' checked'; } ?>><span><strong><?php echo htmlspecialchars($formato[0]); ?></strong><br><?php echo htmlspecialchars($formato[1]); ?></span></label>
<?php } ?>
    </div>
    <p class="ayuda-campo">En una eliminación directa, siempre con ganador o al mejor de 3, 5 o 7.</p>
    <?php echo errorCampo('partido', $errores_campo); ?>
  </fieldset>
  <fieldset>
    <legend>Playoffs (solo en la liga)</legend>
    <div class="campo">
      <label>Clasifican a playoffs<select id="campo-clasifican" name="clasifican"<?php echo atributosCampo('clasifican', true, $errores_campo); ?>>
<?php foreach (array('0' => 'Ninguno', '2' => 'Los 2 primeros', '4' => 'Los 4 primeros', '8' => 'Los 8 primeros') as $clave => $texto) { ?>
        <option value="<?php echo $clave; ?>"<?php if ($valores['clasifican'] === (string)$clave) { echo ' selected'; } ?>><?php echo $texto; ?></option>
<?php } ?>
      </select></label>
      <p class="ayuda-campo" id="ayuda-clasifican">Quedan marcados en la tabla. Los playoffs se juegan aparte, como una eliminación directa nueva.</p>
      <?php echo errorCampo('clasifican', $errores_campo); ?>
    </div>
  </fieldset>
  <fieldset>
    <legend>Rondas (solo en el suizo)</legend>
    <div class="campo">
      <label>Rondas<select id="campo-rondas" name="rondas"<?php echo atributosCampo('rondas', true, $errores_campo); ?>>
        <option value=""<?php if ($valores['rondas'] === '') { echo ' selected'; } ?>>Según los inscriptos</option>
<?php for ($r = 3; $r <= 7; $r++) { ?>
        <option value="<?php echo $r; ?>"<?php if ($valores['rondas'] === (string)$r) { echo ' selected'; } ?>><?php echo $r; ?> rondas</option>
<?php } ?>
      </select></label>
      <p class="ayuda-campo" id="ayuda-rondas">Según los inscriptos: 3 con hasta 8, 4 con hasta 16, 5 con hasta 32. Nunca más rondas que rivales posibles.</p>
      <?php echo errorCampo('rondas', $errores_campo); ?>
    </div>
  </fieldset>
  <fieldset>
    <legend>Puntaje y desempate (liga y suizo)</legend>
    <div class="grilla">
      <div class="campo">
        <label>Puntos por victoria<input type="number" id="campo-victoria" name="victoria" value="<?php echo $v['victoria']; ?>" min="1" max="10" required<?php echo atributosCampo('victoria', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-victoria">De 1 a 10.</p>
        <?php echo errorCampo('victoria', $errores_campo); ?>
      </div>
      <div class="campo">
        <label>Puntos por empate<input type="number" id="campo-empate" name="empate" value="<?php echo $v['empate']; ?>" min="0" max="10" required<?php echo atributosCampo('empate', true, $errores_campo); ?>></label>
        <p class="ayuda-campo" id="ayuda-empate">De 0 a 10, sin pasar la victoria. En una liga sin empates no cuenta.</p>
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
  <div class="fila" style="justify-content:space-between"><a class="btn" href="<?php echo $ruta_publica; ?>/panel.php"><span aria-hidden="true">←</span> Volver al panel</a><button class="btn btn-primario" type="submit">Crear torneo</button></div>
</form>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">El torneo, en breve</span>
  <table><tr><td>Formato</td><td><?php echo htmlspecialchars($formatos_torneo[$formato_elegido][0]); ?><?php if ($formato_elegido === 'liga') { echo ($valores['vueltas'] === 'dos') ? ' · ida y vuelta' : ' · una vuelta'; } ?></td></tr><tr><td>Partidos</td><td><?php echo htmlspecialchars($formatos[$partido_elegido][0]); ?></td></tr><tr><td><?php echo $jugadores ? 'Jugadores' : 'Equipos'; ?></td><td><?php echo $cupo; ?> como máximo</td></tr><tr><td>Inicio</td><td><?php echo ($valores['inicio'] === '') ? 'a definir' : htmlspecialchars($valores['inicio']); ?></td></tr><?php if ($valores['sede'] !== '') { ?><tr><td>Sede</td><td><?php echo $v['sede']; ?></td></tr><?php } ?></table>
</div>
<div class="tarjeta tarjeta-olivo">
  <span class="etiqueta">Con el cupo completo</span>
  <p style="margin:0;font-family:var(--serif);font-size:34px;font-weight:500;line-height:1.1"><?php echo $partidos_previstos; ?></p>
  <p>enfrentamientos, en <?php echo $fechas_previstas . ' ' . $palabra_rondas; ?>, con <?php echo $cupo . ($jugadores ? ' jugadores' : ' equipos'); ?>. El calendario se arma en el panel, con la inscripción cerrada.</p>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
