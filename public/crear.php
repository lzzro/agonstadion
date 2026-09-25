<?php require __DIR__ . '/../apps/config/pagina.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Nuevo torneo · Stadion</title>
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
<nav><a href="index.php">Inicio</a><a href="torneos.php">Torneos</a><a href="calendario.php">Calendario</a><a href="torneo.php#posiciones">Posiciones</a><a href="panel.html" class="activo">Organizadores</a></nav>
<main>
<section>
  <p class="etiqueta">Panel / Mis torneos</p>
  <h1>Nuevo torneo</h1>
  <div class="pasos"><span class="hecho">✓ Datos</span><span class="activo">2 Formato</span><span>3 Participantes</span><span>4 Calendario</span><span>5 Publicar</span></div>
</section>
<form action="#" method="post">
  <fieldset>
    <legend>Datos del torneo</legend>
    <label>Nombre del torneo<input type="text" name="nombre" placeholder="Abierto de Tenis de Mesa · Primavera" required minlength="4" maxlength="80"></label>
    <div class="grilla">
      <label>Disciplina<select name="disciplina" required><option value="">Elegir…</option><option>Esports</option><option>Ajedrez</option><option>Tenis de mesa</option><option>Fútbol</option><option>Cartas</option></select></label>
      <label>Inicio<input type="date" name="inicio" required></label>
      <label>Máximo de participantes<input type="number" name="max" min="2" max="128" value="16" required></label>
    </div>
  </fieldset>
  <fieldset>
    <legend>Formato de competencia</legend>
    <div class="opciones">
      <label class="opcion"><input type="radio" name="formato" value="liga" checked><span><strong>Liga</strong><br>Todos contra todos. Calendario completo desde el inicio.</span></label>
      <label class="opcion"><input type="radio" name="formato" value="eliminacion"><span><strong>Eliminación directa</strong><br>Llaves. Quien pierde queda fuera.</span></label>
      <label class="opcion"><input type="radio" name="formato" value="suizo"><span><strong>Sistema suizo</strong><br>Emparejamiento por puntaje, sin repetir rivales.</span></label>
    </div>
    <div class="grilla">
      <label>Puntos por victoria<input type="number" name="pv" value="3" min="1" max="10"></label>
      <label>Puntos por empate<input type="number" name="pe" value="1" min="0" max="10"></label>
      <label>Clasifican a playoffs<select name="playoffs"><option value="0" selected>Ninguno</option><option>2</option><option>4</option><option>8</option></select></label>
    </div>
  </fieldset>
  <label>Reglas (texto libre)<textarea name="reglas" rows="4" placeholder="Mejor de 3 mapas. Desempate por diferencia y luego resultado directo."></textarea></label>
  <div class="fila" style="justify-content:space-between"><a class="btn" href="#">← Volver</a><div class="fila"><button class="btn" type="button">Guardar borrador</button><button class="btn btn-primario" type="submit">Continuar →</button></div></div>
</form>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Resumen del torneo</span>
  <table><tr><td>Formato</td><td>Liga · una vuelta</td></tr><tr><td>Participantes</td><td>16 como máximo</td></tr><tr><td>Inicio</td><td>por definir</td></tr></table>
</div>
<div class="tarjeta" style="background:var(--olivoT)">
  <span class="etiqueta" style="color:var(--olivo)">Lo que se va a generar</span>
  <h3 style="font-size:34px">120</h3>
  <p>enfrentamientos, en 15 rondas, si inscribís 16 participantes. El calendario se arma solo al publicar.</p>
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
