<?php require __DIR__ . '/../apps/config/pagina.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Inicio · Stadion</title>
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
<nav><a href="index.php" class="activo">Inicio</a><a href="torneos.php">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.html">Organizadores</a></nav>
<main>
<section>
  <p class="epigrafe">ἀγών · στάδιον</p>
  <h1>Toda competencia merece un <em>estadio.</em></h1>
  <p class="intro">Liga, eliminación directa o sistema suizo. Esports, ajedrez, tenis de mesa o fútbol: inscripciones, enfrentamientos, resultados y posiciones en un solo lugar.</p>
  <div class="fila"><a class="btn btn-primario" href="crear.php">Organizar un torneo</a><a class="btn" href="torneos.php">Ver torneos públicos</a></div>
  <div class="datos"><div><strong>312</strong><span class="etiqueta">Torneos activos</span></div><div><strong>4.860</strong><span class="etiqueta">Participantes</span></div><div><strong>3</strong><span class="etiqueta">Formatos</span></div></div>
</section>
<section>
  <h2>Torneos en curso</h2>
  <div class="grilla">
  <article class="tarjeta">
    <span class="estado estado-en-vivo">En vivo</span>
    <h3>Liga Valorant Otoño</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">12 equipos · Ronda 7 de 11</p>
    <div class="barra"><span style="width:64%"></span></div>
    <a href="torneo.php">Ver torneo →</a>
  </article>
  <article class="tarjeta">
    <span class="etiqueta">Eliminación · Ajedrez</span>
    <h3>Copa Interliceal</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">32 participantes · Octavos</p>
    <div class="barra"><span style="width:40%"></span></div>
    <a href="torneo.php">Ver torneo →</a>
  </article>
  <article class="tarjeta">
    <span class="etiqueta">Suizo · Tenis de mesa</span>
    <h3>Abierto de Tenis de Mesa</h3>
    <p class="etiqueta" style="letter-spacing:.04em;text-transform:none;">24 jugadores · Ronda 3 de 5</p>
    <div class="barra"><span style="width:60%"></span></div>
    <a href="torneo.php">Ver torneo →</a>
  </article>
  </div>
</section>
<section>
  <h2>Cada contienda sigue su propia ley.</h2>
  <div class="grilla">
    <article class="tarjeta"><h3>Liga</h3><p>Todos contra todos. El calendario completo se genera al abrir el torneo.</p></article>
    <article class="tarjeta"><h3>Eliminación directa</h3><p>Llaves que avanzan solas: el ganador espera en la siguiente ronda.</p></article>
    <article class="tarjeta"><h3>Sistema suizo</h3><p>Emparejamiento por rendimiento, sin repetir rivales.</p></article>
  </div>
</section>
</main>
<aside>
<div class="tarjeta">
  <div class="fila" style="justify-content:space-between"><span class="etiqueta">Cuartos de final</span><span class="estado estado-en-vivo">En vivo</span></div>
  <h3>Liga Valorant · Otoño</h3>
  <table><tr><td>Titanes CS</td><td class="num">2 – 0</td><td>Nova Esports</td></tr><tr><td>Vortex</td><td class="num">1 – 1</td><td>Aurora FC</td></tr><tr><td>Delta Gaming</td><td class="num">—</td><td>Ping Masters</td></tr></table>
  <a href="torneo.php">Tabla completa →</a>
</div>
<div class="tarjeta">
  <span class="etiqueta">Por qué olivo</span>
  <p><em>"¿Contra qué clase de hombres nos has traído a luchar? Hombres que no compiten por riquezas, sino por la virtud."</em></p>
  <span class="etiqueta">Heródoto, Historias VIII</span>
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
