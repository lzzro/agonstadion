<?php
# =====================================================================
# Vista de resultado
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Es la vista que los controladores vuelven a incluir despues de
# procesar, como en la estructura de clase. Recibe tres variables, que
# el controlador deja preparadas:
#   $titulo   encabezado de la pagina
#   $mensaje  texto de resultado, si salio bien
#   $errores  arreglo de mensajes, si algo fallo
#
# Todo lo que viene del formulario se imprime con htmlspecialchars: sin
# eso, un nombre con etiquetas HTML se ejecutaria en la pagina.
#
# Las rutas del CSS y del JS son relativas a la direccion del
# controlador (apps/controllers/), que es la que queda en el navegador.
# =====================================================================

if (!isset($titulo))  { $titulo  = 'Stadion'; }
if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($titulo); ?> · Stadion</title>
  <link rel="icon" href="../../public/img/stadion.png">
  <link rel="stylesheet" href="../../public/css/style.css">
  <script src="../../public/js/tema.js"></script>
</head>
<body>
<div class="pagina">
<header>
  <a class="marca" href="../../public/index.html"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
</header>
<main>
<section>
  <p class="etiqueta">Acceso</p>
  <h1><?php echo htmlspecialchars($titulo); ?></h1>

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

  <div class="fila">
    <a class="btn" href="../../public/login.html">Volver al acceso</a>
    <a class="btn" href="../../public/index.html">Ir al inicio</a>
  </div>
</section>
</main>
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
