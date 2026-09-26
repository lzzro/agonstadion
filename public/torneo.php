<?php require __DIR__ . '/../apps/config/pagina.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Liga Valorant · Otoño · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/tema.js"></script>
</head>
<body>
<a class="saltar" href="#contenido" data-vista="resumen">Saltar al contenido</a>
<a class="saltar" href="#calendario" data-vista="calendario">Saltar al contenido</a>
<a class="saltar" href="#posiciones" data-vista="posiciones">Saltar al contenido</a>
<a class="saltar" href="#participantes" data-vista="participantes">Saltar al contenido</a>
<a class="saltar" href="#reglas" data-vista="reglas">Saltar al contenido</a>
<div class="pagina">
<header>
  <a class="marca" href="index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $persona_sesion); ?>
</header>
<nav><a href="index.php">Inicio</a><a href="torneos.php" class="activo" aria-current="true" data-fuera="posiciones">Torneos</a><a href="torneos.php" data-vista="posiciones">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones" data-fuera="posiciones">Posiciones</a><a href="torneo.php#posiciones" class="activo" aria-current="page" data-vista="posiciones">Posiciones</a><a href="panel.html">Organizadores</a></nav>
<main id="contenido">
<div class="torneo-zona">
<section>
  <p class="etiqueta">Torneos / Esports</p>
  <div class="fila"><span class="etiqueta">Liga · Esports · Ronda 7 de 11</span><span class="estado estado-en-vivo">En vivo</span></div>
  <h1>Liga Valorant · Otoño</h1>
  <p class="intro">Organiza <strong>Comunidad Vórtice</strong> · 12 equipos · 22 de agosto – 26 de octubre · Montevideo (online)</p>
  <div class="fila"><span class="enlace-apagado" aria-disabled="true">Seguir torneo</span><span class="enlace-apagado" aria-disabled="true">Inscribir equipo</span></div>
</section>
<div class="vista" id="calendario">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario" class="activo" aria-current="page">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Calendario del torneo</h2>
  <div class="agenda">
  <div class="agenda-dia">
    <p class="etiqueta dia">Ronda 8 · en curso</p>
    <div class="partido"><span class="hora">19:00</span><span class="cruce-nombres"><span class="torneo">Jueves 18 de septiembre</span><span class="lados">Titanes CS <span class="vs">vs</span> Vortex</span></span><span class="estado estado-en-vivo">En vivo</span></div>
    <div class="partido"><span class="hora">20:30</span><span class="cruce-nombres"><span class="torneo">Jueves 18 de septiembre</span><span class="lados">Nova Esports <span class="vs">vs</span> Delta Gaming</span></span></div>
    <div class="partido"><span class="hora">17:00</span><span class="cruce-nombres"><span class="torneo">Sábado 20 de septiembre</span><span class="lados">Aurora FC <span class="vs">vs</span> Halcones</span></span></div>
    <div class="partido"><span class="hora">—</span><span class="cruce-nombres"><span class="torneo">Domingo 21 de septiembre · Horario a confirmar</span><span class="lados">Liceo 3 <span class="vs">vs</span> Sur Gaming</span></span></div>
  </div>
  <div class="agenda-dia">
    <p class="etiqueta dia">Ronda 9 · programada</p>
    <div class="partido"><span class="hora">—</span><span class="cruce-nombres"><span class="lados">Enfrentamientos por confirmar.</span></span></div>
  </div>
  <div class="agenda-dia">
    <p class="etiqueta dia">Ronda 10 · programada</p>
    <div class="partido"><span class="hora">—</span><span class="cruce-nombres"><span class="lados">Enfrentamientos por confirmar.</span></span></div>
  </div>
  <div class="agenda-dia">
    <p class="etiqueta dia">Ronda 11 · programada</p>
    <div class="partido"><span class="hora">—</span><span class="cruce-nombres"><span class="lados">Enfrentamientos por confirmar.</span></span></div>
  </div>
  </div>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Las rondas jugadas quedan en la tabla de posiciones.</span>
  </section>
</div>
<div class="vista" id="posiciones">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones" class="activo" aria-current="page">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Tabla de posiciones</h2>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Tabla de posiciones">
  <table>
    <thead><tr><th>#</th><th>Equipo</th><th>PJ</th><th>G</th><th>E</th><th>P</th><th>Dif</th><th>Pts</th></tr></thead>
    <tbody><tr class="clasifica"><td class="num">1</td><td>Titanes CS</td><td>7</td><td>6</td><td>0</td><td>1</td><td>+9</td><td class="num">18</td></tr><tr class="clasifica"><td class="num">2</td><td>Nova Esports</td><td>7</td><td>5</td><td>0</td><td>2</td><td>+6</td><td class="num">15</td></tr><tr class="clasifica"><td class="num">3</td><td>Vortex</td><td>7</td><td>4</td><td>1</td><td>2</td><td>+3</td><td class="num">13</td></tr><tr class="clasifica"><td class="num">4</td><td>Delta Gaming</td><td>7</td><td>4</td><td>0</td><td>3</td><td>+1</td><td class="num">12</td></tr><tr><td class="num">5</td><td>Aurora FC</td><td>7</td><td>3</td><td>1</td><td>3</td><td>0</td><td class="num">10</td></tr><tr><td class="num">6</td><td>Halcones</td><td>7</td><td>3</td><td>0</td><td>4</td><td>-1</td><td class="num">9</td></tr><tr><td class="num">7</td><td>Ping Masters</td><td>7</td><td>2</td><td>2</td><td>3</td><td>-2</td><td class="num">8</td></tr><tr><td class="num">8</td><td>Liceo 3</td><td>7</td><td>2</td><td>1</td><td>4</td><td>-3</td><td class="num">7</td></tr></tbody>
  </table>
  </div>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em">En verde: clasifican a playoffs · Victoria 3 pts · Empate 1 pt</span>
</section>
</div>
<div class="vista" id="participantes">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes" class="activo" aria-current="page">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Participantes</h2>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Participantes">
  <table>
    <thead><tr><th>Equipo</th><th>Ciudad</th><th>Capitán</th></tr></thead>
    <tbody><tr><td>Titanes CS</td><td>Montevideo</td><td>L. Martiarena</td></tr><tr><td>Nova Esports</td><td>Ciudad de la Costa</td><td>G. Etcheverry</td></tr><tr><td>Vortex</td><td>Montevideo</td><td>F. Bentancor</td></tr><tr><td>Delta Gaming</td><td>Las Piedras</td><td>R. Olivera</td></tr><tr><td>Aurora FC</td><td>Canelones</td><td>V. Techera</td></tr><tr><td>Halcones</td><td>Pando</td><td>H. Cardozo</td></tr><tr><td>Ping Masters</td><td>Montevideo</td><td>S. Viera</td></tr><tr><td>Liceo 3</td><td>Montevideo</td><td>A. Falero</td></tr></tbody>
  </table>
  </div>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Ocho equipos · un capitán por equipo</span>
  </section>
</div>
<div class="vista" id="reglas">
  <div class="pestanas"><a href="#resumen">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas" class="activo" aria-current="page">Reglas</a></div>
  <section class="tarjeta">
  <h2>Reglas</h2>
  <table>
    <tbody>
      <tr><th scope="row" class="etiqueta">Formato</th><td>Liga de doce equipos, todos contra todos, una vuelta.</td></tr>
      <tr><th scope="row" class="etiqueta">Puntaje</th><td>Tres puntos la victoria, uno el empate, ninguno la derrota.</td></tr>
      <tr><th scope="row" class="etiqueta">Desempate</th><td>Primero la diferencia entre mapas ganados y perdidos; si persiste, los mapas ganados.</td></tr>
      <tr><th scope="row" class="etiqueta">Ausencia</th><td>Un equipo que no se presenta a la hora pactada pierde el enfrentamiento por walkover, sin necesidad de jugarlo.</td></tr>
    </tbody>
  </table>
  <span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Cuatro reglas · las mismas para toda la liga</span>
  </section>
</div>
<div class="vista" id="resumen">
  <div class="pestanas"><a href="#resumen" class="activo" aria-current="page">Resumen</a><a href="#calendario">Calendario</a><a href="#posiciones">Posiciones</a><a href="#participantes">Participantes</a><a href="#reglas">Reglas</a></div>
  <section class="tarjeta">
  <h2>Resumen</h2>
  <p class="intro">Once rondas a una vuelta: cada equipo se cruza una vez con cada rival, y la tabla decide quiénes llegan a los playoffs. Siete rondas quedan atrás; la octava está en juego.</p>
  </section>
  <section class="tarjeta">
  <span class="etiqueta">Próximo enfrentamiento</span>
  <h3>Nova Esports vs Delta Gaming</h3>
  <p>Ronda 8 · jueves 18 de septiembre, 20:30</p>
  <p><a href="#calendario">Ver el calendario del torneo <span aria-hidden="true">→</span></a></p>
  </section>
  <section class="tarjeta">
  <span class="etiqueta">Al frente de la tabla</span>
  <h3>Titanes CS</h3>
  <p>18 puntos en 7 rondas · 6 ganados, 1 perdido · +9 de diferencia de mapas</p>
  <p><a href="#posiciones">Ver la tabla completa <span aria-hidden="true">→</span></a></p>
  </section>
</div>
</div>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Reglas en breve</span>
  <p>Todos contra todos, una vuelta. Mejor de 3 mapas. Victoria 3 pts, empate 1 pt. Los 4 primeros clasifican a playoffs.</p>
</div>
<div class="tarjeta tarjeta-olivo">
  <span class="etiqueta">Organizador</span>
  <h3>Comunidad Vórtice</h3>
  <p>7 torneos organizados · desde 2024</p>
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
