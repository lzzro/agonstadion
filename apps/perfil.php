<?php
# =====================================================================
# Vista de perfil
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La incluye perfilController.php despues de cargar al usuario. Recibe:
#   $usuario   objeto Usuario con sus roles ya cargados
#   $mensaje   texto de resultado, si se acaba de guardar
#   $errores   arreglo de mensajes, si algo fallo
#   $ruta_publica  desde donde alcanzar css, js e imagenes
#
# Reemplaza a public/perfil.html, que tenia datos inventados. Lo que se
# muestra sale de la base: no hay ni un dato escrito a mano.
#
# Por eso tampoco estan los contadores de torneos, finales y kotinos
# que tenia la maqueta: todavia no hay torneos en la base, y un numero
# inventado en una pantalla que dice mostrar datos reales es peor que
# no mostrarlo.
#
# Todo lo que viene de la base se imprime con htmlspecialchars: sin
# eso, una presentacion con etiquetas HTML se ejecutaria en la pagina.
# =====================================================================

if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }

# La fecha llega de la base como 2026-09-20 14:32:05. En pantalla va en
# castellano y sin la hora, que a nadie le importa.
$meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
               'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Perfil · Stadion</title>
  <link rel="icon" href="<?php echo $ruta_publica; ?>/img/stadion.png">
  <link rel="stylesheet" href="<?php echo $ruta_publica; ?>/css/style.css">
  <script src="<?php echo $ruta_publica; ?>/js/tema.js"></script>
</head>
<body>
<div class="pagina">
<header>
  <a class="marca" href="<?php echo $ruta_publica; ?>/index.html"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <div class="acciones"><a class="btn btn-primario" href="<?php echo $ruta_publica; ?>/crear.html">Crear torneo</a></div>
</header>
<nav><a href="<?php echo $ruta_publica; ?>/index.html">Inicio</a><a href="<?php echo $ruta_publica; ?>/torneos.html">Torneos</a><a href="<?php echo $ruta_publica; ?>/torneo.html">Calendario</a><a href="<?php echo $ruta_publica; ?>/torneo.html">Posiciones</a><a href="<?php echo $ruta_publica; ?>/crear.html">Organizadores</a></nav>
<main>
<section class="perfil-cabecera">
  <div class="avatar" role="img" aria-label="Foto de perfil"></div>
  <div>
    <p class="epigrafe">ἀθλητής</p>
    <h1 style="font-size:36px"><?php echo htmlspecialchars($usuario->getNombreCompleto()); ?></h1>
    <p class="intro"><?php
      $linea = array();
      if ($usuario->getAlias() !== null && $usuario->getAlias() !== '') {
          $linea[] = $usuario->getAlias();
      }
      if ($desde !== '') { $linea[] = $desde; }
      echo htmlspecialchars(implode(' · ', $linea));
    ?></p>
  </div>
</section>

<?php if (!empty($errores)) { ?>
<ul class="avisos">
<?php   foreach ($errores as $error) { ?>
  <li><?php echo htmlspecialchars($error); ?></li>
<?php   } ?>
</ul>
<?php } ?>

<?php if ($mensaje !== '') { ?>
<p class="intro"><?php echo htmlspecialchars($mensaje); ?></p>
<?php } ?>

<section class="tarjeta">
  <h2>Datos del perfil</h2>
  <form action="" method="post">
    <div class="grilla">
      <label>Nombre<input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario->getNombre()); ?>" required minlength="2" maxlength="40"></label>
      <label>Apellido<input type="text" name="apellido" value="<?php echo htmlspecialchars($usuario->getApellido()); ?>" required minlength="2" maxlength="40"></label>
      <label>Alias en juego<input type="text" name="alias" value="<?php echo htmlspecialchars($usuario->getAlias()); ?>" maxlength="20" pattern="[A-Za-z0-9_]+" title="Letras, números y guion bajo"></label>
    </div>
    <label>Presentación<textarea name="presentacion" rows="3" maxlength="300"><?php echo htmlspecialchars($usuario->getPresentacion()); ?></textarea></label>
    <div class="fila" style="justify-content:flex-end"><button class="btn" type="reset">Descartar</button><button class="btn btn-primario" type="submit">Guardar cambios</button></div>
  </form>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">La cuenta</span>
  <table>
    <tr><td>Correo</td><td class="num"><?php echo htmlspecialchars($usuario->getCorreo()); ?></td></tr>
<?php if ($desde !== '') { ?>
    <tr><td>Alta</td><td class="num"><?php echo htmlspecialchars(ucfirst(str_replace('miembro desde ', '', $desde))); ?></td></tr>
<?php } ?>
  </table>
  <p><small>El correo identifica la cuenta y no se cambia desde acá.</small></p>
</div>
<div class="tarjeta">
  <span class="etiqueta">Roles</span>
<?php
  $roles = $usuario->getRoles();
  if (empty($roles)) { ?>
  <p>Sin roles asignados.</p>
<?php } else { ?>
  <table>
<?php   foreach ($roles as $rol) { ?>
    <tr><td><?php echo htmlspecialchars($rol->getNombre()); ?></td><td><small><?php echo htmlspecialchars($rol->getDescripcion()); ?></small></td></tr>
<?php   } ?>
  </table>
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
</body>
</html>
