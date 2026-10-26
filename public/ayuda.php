<?php
require __DIR__ . '/../apps/config/pagina.php';

# Pagina fija: las preguntas mas comunes por rol, sacadas del manual de usuario.
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Preguntas frecuentes de Stadion, por rol: sin cuenta, la cuenta, jugador, organizador y administración.">
  <title>Ayuda · Stadion</title>
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
  <p class="epigrafe" lang="grc">βοήθεια</p>
  <h1>Ayuda</h1>
  <p class="intro">Las preguntas más comunes, por rol. Lo que no está acá se consulta por correo.</p>
</section>

<section id="sin-cuenta">
  <h2>Sin cuenta</h2>
  <h3>¿Hace falta una cuenta para ver los torneos?</h3>
  <p>No. Los torneos, sus calendarios, las tablas y las llaves están a la vista sin cuenta, desde Torneos. La cuenta hace falta para jugar u organizar.</p>
  <h3>¿Dónde están los partidos de la semana?</h3>
  <p>En Calendario, día por día, con los de todos los torneos públicos. Los enlaces de arriba pasan a la semana anterior o a la siguiente.</p>
  <h3>¿Cómo se lee la tabla de posiciones?</h3>
  <p>PJ son los partidos jugados; G, E y P, los ganados, empatados y perdidos (la E aparece solo si el torneo admite empates); DIF, la diferencia de tantos, y PTS, los puntos. Los que clasifican a playoffs llevan una barra a la izquierda.</p>
  <h3>¿Qué diferencia hay entre liga, eliminación directa y suizo?</h3>
  <p>En la liga juegan todos contra todos y manda la tabla. En la eliminación directa, quien pierde queda afuera, y la pestaña Posiciones pasa a llamarse Llave. En el suizo, cada ronda enfrenta a quienes van parejos en la tabla, sin repetir rival.</p>
  <h3>¿Qué quiere decir «De muestra»?</h3>
  <p>Que el torneo o la cuenta son un ejemplo: no pertenecen a personas reales. Un torneo de muestra no recibe pedidos ni inscripciones.</p>
</section>

<section id="la-cuenta">
  <h2>La cuenta</h2>
  <h3>¿Cómo se crea una cuenta?</h3>
  <p>Desde Iniciar sesión, con Crear una cuenta: nombre, apellido, correo y contraseña, y la conformidad con los <a href="terminos.php">términos</a>. Toda cuenta nueva empieza con el rol de jugador. Después se inicia la sesión con el correo y la contraseña.</p>
  <h3>¿Qué pide la contraseña?</h3>
  <p>Al menos 10 caracteres, con una mayúscula, una minúscula y un número, y sin el nombre, el apellido ni el correo de la cuenta.</p>
  <h3>¿Cómo se cambia la contraseña?</h3>
  <p>En el perfil, pestaña Datos, tarjeta Contraseña: la actual, la nueva y la nueva otra vez.</p>
  <h3>¿Y si se olvida la contraseña?</h3>
  <p>Recuperar contraseña todavía no está disponible. El caso se consulta por <a href="contacto.php">correo</a>, desde el correo de la cuenta.</p>
  <h3>El acceso dice «Demasiados intentos fallidos con ese correo».</h3>
  <p>Después de 5 intentos fallidos en 15 minutos, ese correo queda en pausa 15 minutos, aunque la contraseña sea la correcta. Pasado ese tiempo, se intenta de nuevo.</p>
  <h3>La sesión se cerró sola.</h3>
  <p>Pasa después de 30 minutos sin actividad. Lo que se envió después no se guarda: se inicia la sesión otra vez y se repite.</p>
</section>

<section id="jugador">
  <h2>Jugador</h2>
  <h3>¿Cómo se arma un equipo?</h3>
  <p>En el perfil, pestaña Mis torneos, bloque Mis equipos: el nombre del equipo y Armar el equipo. La cuenta que lo arma queda de capitán, y suma integrantes con el correo de su cuenta y, si quiere, un dorsal.</p>
  <h3>¿Cómo se juega un torneo por equipos?</h3>
  <p>El capitán abre la página del torneo con la inscripción abierta, elige el equipo y presiona Pedir lugar. El pedido lo acepta o lo rechaza quien organiza.</p>
  <h3>¿Y un torneo de jugadores?</h3>
  <p>Desde la página del torneo, con Inscribirse: la inscripción es directa mientras haya lugar.</p>
  <h3>¿Dónde están los partidos jugados?</h3>
  <p>En el perfil, pestaña Mis torneos, bloque Mis resultados: cada partido con su rival, el marcador y si fue victoria, empate o derrota.</p>
  <h3>¿Cómo se carga una medición de rendimiento?</h3>
  <p>En el perfil, pestaña Rendimiento: un formulario por prueba (tiempo de reacción, aceleración de salida y potencia de salto). Cada medición se puede borrar desde la tabla de mediciones.</p>
</section>

<section id="organizador">
  <h2>Organizador</h2>
  <h3>¿Cómo se consigue el rol de organizador?</h3>
  <p>En el perfil, tarjeta Roles, con Pedir el rol de organizador. Lo aprueba la administración, y desde ese momento Crear torneo y Organizadores quedan disponibles.</p>
  <h3>¿Cómo se crea un torneo?</h3>
  <p>Con Crear torneo: el formato, el nombre, el juego o deporte, el cupo y cómo se juega cada partido. El torneo nace con la inscripción abierta.</p>
  <h3>¿Cómo se arma el calendario?</h3>
  <p>En Organizadores, en la tarjeta del torneo: con 4 o más anotados, Cerrar la inscripción y después Armar el fixture, la llave o la primera ronda, según el formato.</p>
  <h3>¿Cómo se cargan los resultados?</h3>
  <p>Con Fechas y resultados (Rondas y resultados fuera de la liga): el día y el lugar, en vivo y el resultado de cada partido. La tabla cambia en el momento. Con todo jugado, se cierra la fecha.</p>
  <h3>¿Cuándo termina un torneo?</h3>
  <p>Al cerrar la última fecha de la liga o del suizo, o la final de la eliminación directa. El campeón queda marcado con la rama de olivo.</p>
</section>

<section id="administracion">
  <h2>Administración</h2>
  <h3>¿Quién aprueba los pedidos de rol?</h3>
  <p>Una cuenta de la administración, en Administración, sección Pedidos. Un pedido propio lo resuelve otra cuenta.</p>
  <h3>¿Cómo se da de baja una cuenta?</h3>
  <p>En Administración, sección Cuentas, con la casilla Confirmar y Dar de baja. La cuenta deja de iniciar sesión y su historial queda. Reactivar la devuelve.</p>
  <h3>¿Qué es un módulo?</h3>
  <p>Cada formato de torneo: liga, eliminación directa y suizo. Un módulo deshabilitado no aparece al crear un torneo. Siempre queda uno habilitado.</p>
  <h3>¿Dónde se ve lo que pasa en el sitio?</h3>
  <p>En Administración, sección Auditoría: cada acción con su cuenta, su fecha y su hora, con un filtro por acción.</p>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">En esta página</span>
  <ul class="lista-apilada">
    <li><a href="#sin-cuenta">Sin cuenta</a></li>
    <li><a href="#la-cuenta">La cuenta</a></li>
    <li><a href="#jugador">Jugador</a></li>
    <li><a href="#organizador">Organizador</a></li>
    <li><a href="#administracion">Administración</a></li>
  </ul>
</div>
<div class="tarjeta">
  <span class="etiqueta">Sin respuesta</span>
  <p>Lo que no está en esta página va por correo.</p>
  <p><a href="contacto.php">Contacto <span aria-hidden="true">→</span></a></p>
  <p><a href="terminos.php">Términos y privacidad <span aria-hidden="true">→</span></a></p>
</div>
</aside>
<?php piePagina(true, $ruta_publica); ?>
</body>
</html>
