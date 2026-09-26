<?php require __DIR__ . '/../stadion_app/config/pagina.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Torneos · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/tema.js"></script>
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
<nav><a href="index.php">Inicio</a><a href="torneos.php" class="activo" aria-current="page">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.html">Organizadores</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἀγῶνες</p>
  <h1>Torneos</h1>
  <p class="intro">312 competencias públicas · 27 en vivo ahora mismo</p>
</section>
<section>
  <h2 class="visualmente-oculto">Competencias públicas</h2>
  <div class="grilla">
  <article class="tarjeta">
    <span class="estado estado-en-vivo">En vivo</span>
    <h3>Liga Valorant Otoño</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">Comunidad Vórtice · 12 equipos · Ronda 7 de 11</p>
    <div class="barra"><span style="width:64%"></span></div>
    <a href="torneo.php">Ver torneo<span class="visualmente-oculto"> Liga Valorant Otoño</span> <span aria-hidden="true">→</span></a>
  </article>
  <article class="tarjeta">
    <div class="fila"><span class="estado estado-en-juego">En juego</span><span class="etiqueta">Eliminación · Ajedrez</span></div>
    <h3>Copa Interliceal de Ajedrez</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">Liceo N.º 3 · 16 participantes · Cuartos</p>
    <div class="barra"><span style="width:50%"></span></div>
    <a href="llave.php">Ver torneo<span class="visualmente-oculto"> Copa Interliceal de Ajedrez</span> <span aria-hidden="true">→</span></a>
  </article>
  <article class="tarjeta">
    <span class="estado estado-en-vivo">En vivo</span>
    <h3>Abierto de Tenis de Mesa</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">Club Sur · 24 jugadores · Ronda 3 de 5</p>
    <div class="barra"><span style="width:60%"></span></div>
    <span class="enlace-apagado" aria-disabled="true">Ver torneo<span class="visualmente-oculto"> Abierto de Tenis de Mesa</span> <span aria-hidden="true">→</span></span>
  </article>
  <article class="tarjeta">
    <div class="fila"><span class="estado estado-en-juego">En juego</span><span class="etiqueta">Liga · Fútbol 5</span></div>
    <h3>Liga Barrial del Cerro</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">Centro Juvenil Cerro · 10 equipos · Fecha 4 de 9</p>
    <div class="barra"><span style="width:44%"></span></div>
    <span class="enlace-apagado" aria-disabled="true">Ver torneo<span class="visualmente-oculto"> Liga Barrial del Cerro</span> <span aria-hidden="true">→</span></span>
  </article>
  <article class="tarjeta">
    <span class="estado estado-en-vivo">En vivo</span>
    <h3>Torneo LoL Clasificatorio</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">Agón Comunidad · 16 equipos · Cuartos</p>
    <div class="barra"><span style="width:50%"></span></div>
    <span class="enlace-apagado" aria-disabled="true">Ver torneo<span class="visualmente-oculto"> Torneo LoL Clasificatorio</span> <span aria-hidden="true">→</span></span>
  </article>
  <article class="tarjeta">
    <div class="fila"><span class="estado estado-en-juego">En juego</span><span class="etiqueta">Suizo · Cartas</span></div>
    <h3>Abierto de Magic Commander</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">Tienda El Dado · 40 jugadores · Ronda 1 de 6</p>
    <div class="barra"><span style="width:17%"></span></div>
    <span class="enlace-apagado" aria-disabled="true">Ver torneo<span class="visualmente-oculto"> Abierto de Magic Commander</span> <span aria-hidden="true">→</span></span>
  </article>
  </div>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Próximos cierres</span>
  <table><tr><td>Copa Interliceal · Cuartos<br><small>cierra hoy</small></td><td class="num">0 d</td></tr><tr><td>Liga Valorant · R7<br><small>cierra el domingo</small></td><td class="num">2 d</td></tr><tr><td>Tenis de Mesa · R3<br><small>cierra el martes</small></td><td class="num">4 d</td></tr></table>
</div>
<div class="tarjeta tarjeta-contraste">
  <span class="etiqueta">Para organizadores</span>
  <h3>Un estadio propio</h3>
  <p>Participantes, formato y fecha. Con eso, el calendario y la llave quedan trazados.</p>
  <a class="btn" href="crear.php">Crear torneo</a>
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
  <div class="fila"><span class="enlace-apagado" aria-disabled="true">Ayuda</span><span class="enlace-apagado" aria-disabled="true">Términos</span><span class="enlace-apagado" aria-disabled="true">Contacto</span></div>
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
