<?php
require __DIR__ . '/../stadion_app/config/pagina.php';
require_once $carpeta_app . '/config/contacto.php';

# Pagina fija: el correo de Agon.
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Contacto de Agón, la empresa detrás de Stadion.">
  <title>Contacto · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="<?php echo recurso($ruta_publica, 'css/style.css'); ?>">
  <script src="<?php echo recurso($ruta_publica, 'js/tema.js'); ?>"></script>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="pagina">
<header>
  <a class="marca" href="index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $persona_sesion); ?>
</header>
<nav><a href="index.php">Inicio</a><a href="torneos.php">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.php">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἀγγελία</p>
  <h1>Contacto</h1>
  <p class="intro">Consultas, problemas con una cuenta, pedidos sobre los datos personales y propuestas para Stadion: un solo correo.</p>
</section>
<section>
  <h2>El correo de Agón</h2>
  <p><strong><a href="mailto:<?php echo CORREO_CONTACTO; ?>"><?php echo CORREO_CONTACTO; ?></a></strong></p>
  <p>El enlace abre el programa de correo. Stadion no tiene un formulario de contacto, porque el sitio no manda correos.</p>
</section>
<section>
  <h2>Qué conviene escribir</h2>
  <ul>
    <li>Si la consulta es sobre una cuenta, el pedido sale desde el correo de esa cuenta.</li>
    <li>Si es sobre un torneo, su nombre y la fecha o ronda.</li>
    <li>Si algo falla, qué página estaba abierta y qué aviso apareció.</li>
  </ul>
  <p>Agón nunca pide contraseñas por correo ni por mensaje. Un pedido así no sale de Agón.</p>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Antes de escribir</span>
  <p>Crear una cuenta, pedir lugar, organizar un torneo o cargar un resultado: las respuestas están en la ayuda.</p>
  <p><a href="ayuda.php">Ayuda <span aria-hidden="true">→</span></a></p>
  <p><a href="terminos.php">Términos y privacidad <span aria-hidden="true">→</span></a></p>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
