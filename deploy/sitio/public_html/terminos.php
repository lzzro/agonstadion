<?php
require __DIR__ . '/../stadion_app/config/pagina.php';
require_once $carpeta_app . '/config/contacto.php';

# =====================================================================
# Terminos y privacidad - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Pagina fija (tercera entrega): las condiciones de uso y lo que se hace
# con los datos de cada cuenta. No lee de la base. La casilla "Conforme
# con los terminos" del registro trae aca, en otra pestana, para no
# perder lo escrito en el formulario. Se llega tambien desde el pie.
# El menu no marca ninguna entrada: la pagina no depende de ninguna.
# Lo que dice tiene que coincidir con lo que el sistema hace: si cambia
# un dato que se guarda, cambia esta pagina.
# =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Términos de uso y privacidad de Stadion, la plataforma de torneos de Agón.">
  <title>Términos y privacidad · Stadion</title>
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
  <p class="epigrafe" lang="grc">νόμοι</p>
  <h1>Términos y privacidad</h1>
  <p class="intro">Las condiciones de uso de Stadion y el destino de los datos de cada cuenta. Vigentes desde el 25 de octubre de 2026.</p>
</section>

<section id="servicio">
  <h2>El servicio</h2>
  <p>Stadion es la plataforma de torneos de Agón. Los torneos, los calendarios, las tablas y las llaves se consultan sin cuenta. Jugar u organizar pide una cuenta. El uso no tiene costo y dentro del sitio no se cobra ni se paga nada.</p>
</section>

<section id="cuenta">
  <h2>La cuenta</h2>
  <ul>
    <li>Una cuenta por persona, con datos verdaderos.</li>
    <li>La contraseña es personal: no se presta ni se comparte. Agón nunca la pide por correo ni por mensaje.</li>
    <li>Quien tiene menos de 18 años usa Stadion con la autorización de una persona adulta responsable.</li>
    <li>La sesión se cierra sola después de 30 minutos sin actividad. En una computadora compartida, se cierra al terminar.</li>
  </ul>
</section>

<section id="uso">
  <h2>Reglas de uso</h2>
  <ul>
    <li>Nombres, alias, presentaciones, fotos y nombres de equipos sin contenido ofensivo, discriminatorio ni ajeno.</li>
    <li>Los resultados los carga quien organiza cada torneo, y corresponden a lo jugado.</li>
    <li>Ningún intento de entrar a una cuenta ajena ni de forzar el sitio.</li>
    <li>La administración puede dar de baja una cuenta que no cumple estas reglas. Lo jugado queda en los torneos.</li>
  </ul>
</section>

<section id="publico">
  <h2>Lo que se ve en público</h2>
  <p>Sin cuenta se ven los torneos, sus calendarios, tablas y llaves, el nombre de quien organiza cada uno y los nombres de los equipos. En los torneos donde compiten jugadores, se ven el nombre y el apellido de cada jugador. Con la sesión abierta, la pestaña Rendimiento del perfil compara la mejor marca de cada cuenta, con la inicial del nombre y el apellido.</p>
  <p>Las cuentas y los torneos con la marca «De muestra» son ejemplos: no pertenecen a personas reales.</p>
</section>

<section id="datos">
  <h2>Datos que se guardan y para qué</h2>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Datos que se guardan y para qué">
  <table>
    <thead><tr><th scope="col">Dato</th><th scope="col">Para qué</th></tr></thead>
    <tbody>
      <tr><th scope="row">Nombre, apellido, alias y presentación</th><td>Mostrar la cuenta en el perfil y en los torneos.</td></tr>
      <tr><th scope="row">Correo</th><td>Iniciar sesión y reconocer la cuenta. No se publica: lo ven la administración y quien suma a la cuenta a su equipo.</td></tr>
      <tr><th scope="row">Contraseña</th><td>Iniciar sesión. Se guarda transformada (un hash): nadie la puede leer, tampoco la administración.</td></tr>
      <tr><th scope="row">Foto y portada, si se suben</th><td>El perfil y el círculo de la cabecera.</td></tr>
      <tr><th scope="row">Equipos, inscripciones y resultados</th><td>Armar los torneos, sus calendarios y sus tablas.</td></tr>
      <tr><th scope="row">Mediciones de rendimiento</th><td>Calcular los indicadores de la pestaña Rendimiento. El dato crudo, con la masa, lo ve solo la cuenta que lo carga.</td></tr>
      <tr><th scope="row">Registro de actividad</th><td>La seguridad del sitio: cada acción con su fecha, su hora y la dirección IP, y en un inicio de sesión fallido, el correo intentado. Lo ve solo la administración.</td></tr>
    </tbody>
  </table>
  </div>
  <p>Los datos no se venden ni se ceden, no se usan para publicidad y no reciben correos del sitio: Stadion no manda correos.</p>
</section>

<section id="navegador">
  <h2>En el navegador</h2>
  <p>Una sola cookie, la de la sesión: existe mientras la sesión está abierta. La preferencia del modo noche queda guardada en el propio navegador. Las tipografías se bajan de Google Fonts, que recibe la dirección IP de quien abre la página.</p>
</section>

<section id="derechos">
  <h2>Consulta, corrección y baja</h2>
  <p>El nombre, el alias, la presentación, las imágenes y la contraseña se cambian desde el perfil. Para consultar todos los datos de una cuenta, corregir el correo o darla de baja, el pedido va a <a href="mailto:<?php echo CORREO_CONTACTO; ?>"><?php echo CORREO_CONTACTO; ?></a>, desde el correo de la cuenta. Son los derechos de la Ley 18.331 de Protección de Datos Personales.</p>
  <p>Una cuenta dada de baja deja de iniciar sesión. Los partidos que jugó quedan en los torneos, porque forman parte de la tabla de los demás participantes.</p>
</section>

<section id="cambios">
  <h2>Cambios</h2>
  <p>Si estos términos cambian, cambia la fecha del comienzo de la página.</p>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">En esta página</span>
  <ul class="lista-apilada">
    <li><a href="#servicio">El servicio</a></li>
    <li><a href="#cuenta">La cuenta</a></li>
    <li><a href="#uso">Reglas de uso</a></li>
    <li><a href="#publico">Lo que se ve en público</a></li>
    <li><a href="#datos">Datos que se guardan</a></li>
    <li><a href="#navegador">En el navegador</a></li>
    <li><a href="#derechos">Consulta, corrección y baja</a></li>
  </ul>
</div>
<div class="tarjeta">
  <span class="etiqueta">Dudas</span>
  <p>Las preguntas más comunes están en la ayuda. Lo demás, por correo.</p>
  <p><a href="ayuda.php">Ayuda <span aria-hidden="true">→</span></a></p>
  <p><a href="contacto.php">Contacto <span aria-hidden="true">→</span></a></p>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
