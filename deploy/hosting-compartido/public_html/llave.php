<?php require __DIR__ . '/../stadion_app/config/pagina.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Copa Interliceal de Ajedrez · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/tema.js"></script>
</head>
<body>
<div class="pagina">
<header>
  <a class="marca" href="index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $persona_sesion); ?>
</header>
<nav><a href="index.php">Inicio</a><a href="torneos.php" class="activo">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.html">Organizadores</a></nav>
<main>
<section>
  <p class="epigrafe">κλῆρος</p>
  <p class="etiqueta">Torneos / Ajedrez</p>
  <div class="fila"><span class="etiqueta">Eliminación directa · Ajedrez</span><span class="estado estado-en-juego">Cuartos en juego</span></div>
  <h1>Copa Interliceal de Ajedrez</h1>
  <p class="intro">Organiza <strong>Liceo N.º 3</strong> · 16 participantes · Partidas a 25 min + 10 s</p>
  <div class="fila"><a class="btn" href="#">Seguir torneo</a><a class="btn btn-primario" href="#">Descargar llave</a></div>
  <div class="pestanas"><a href="#">Resumen</a><a href="#" class="activo">Llave</a><a href="#">Participantes</a><a href="#">Reglas</a></div>
</section>
<section>
  <h2>Llave del torneo</h2>
  <p class="intro">El ganador de cada cruce pasa a la ronda siguiente.</p>
  <div class="llave">
  <div class="llave-ronda">
    <p class="etiqueta">Octavos</p>
    <div class="par"><div class="cruce"><div class="pasa"><span>M. Ferreira</span><span class="puntos">1</span></div><div><span>L. Martiarena</span><span class="puntos">0</span></div></div><div class="cruce"><div class="pasa"><span>S. Pérez</span><span class="puntos">1</span></div><div><span>A. Gómez</span><span class="puntos">0</span></div></div></div>
    <div class="par"><div class="cruce"><div class="pasa"><span>J. Tuneu</span><span class="puntos">1</span></div><div><span>B. Fernández</span><span class="puntos">0</span></div></div><div class="cruce"><div class="pasa"><span>C. Silva</span><span class="puntos">1</span></div><div><span>D. Rodríguez</span><span class="puntos">0</span></div></div></div>
    <div class="par"><div class="cruce"><div class="pasa"><span>N. López</span><span class="puntos">1</span></div><div><span>F. Castro</span><span class="puntos">0</span></div></div><div class="cruce"><div class="pasa"><span>R. Díaz</span><span class="puntos">1</span></div><div><span>M. Sosa</span><span class="puntos">0</span></div></div></div>
    <div class="par"><div class="cruce"><div class="pasa"><span>P. Núñez</span><span class="puntos">1</span></div><div><span>T. Vega</span><span class="puntos">0</span></div></div><div class="cruce"><div class="pasa"><span>I. Barrera</span><span class="puntos">1</span></div><div><span>E. Ramos</span><span class="puntos">0</span></div></div></div>
  </div>
  <div class="llave-ronda">
    <p class="etiqueta">Cuartos</p>
    <div class="par"><div class="cruce"><div class="pasa"><span>M. Ferreira</span><span class="puntos">1</span></div><div><span>S. Pérez</span><span class="puntos">0</span></div></div><div class="cruce"><div class="pasa"><span>C. Silva</span><span class="puntos">1</span></div><div><span>J. Tuneu</span><span class="puntos">0</span></div></div></div>
    <div class="par"><div class="cruce"><div><span>N. López</span></div><div><span>R. Díaz</span></div></div><div class="cruce"><div><span>P. Núñez</span></div><div><span>I. Barrera</span></div></div></div>
  </div>
  <div class="llave-ronda">
    <p class="etiqueta">Semifinal</p>
    <div class="par"><div class="cruce"><div><span>M. Ferreira</span></div><div><span>C. Silva</span></div></div><div class="cruce"><div class="espera"><span>Por definir</span></div><div class="espera"><span>Por definir</span></div></div></div>
  </div>
  <div class="llave-ronda">
    <p class="etiqueta">Final</p>
    <div class="par"><div class="cruce"><div class="espera"><span>Por definir</span></div><div class="espera"><span>Por definir</span></div></div></div>
  </div>
  </div>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Próxima partida</span>
  <h3>Semifinal</h3>
  <table><tr><td>M. Ferreira</td><td class="etiqueta">VIE 18:00</td><td>C. Silva</td></tr></table>
  <p><a href="calendario.php">Ver el calendario completo →</a></p>
</div>
<div class="tarjeta">
  <span class="etiqueta">Reglas en breve</span>
  <p>Eliminación directa a una partida. 25 minutos por jugador, con 10 segundos de incremento por jugada. En caso de tablas, una partida relámpago define el cruce.</p>
</div>
<div class="tarjeta" style="background:var(--olivoT)">
  <span class="etiqueta">Organizador</span>
  <h3>Liceo N.º 3</h3>
  <p>4 torneos organizados · desde 2025</p>
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
  <div class="fila"><a href="#">Ayuda</a><a href="#">Términos</a><a href="#">Contacto</a></div>
</footer>
</div>
<button type="button" class="interruptor-tema" id="interruptor-tema">
<svg width="66" height="66" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg" aria-label="Cambiar a modo noche">
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
<svg width="66" height="66" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg" aria-label="Cambiar a modo día">
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
