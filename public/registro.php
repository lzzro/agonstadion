<?php
require __DIR__ . '/../apps/config/pagina.php';
# Con la sesion ya abierta no hay nada que hacer aca: se va al perfil.
if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . $ruta_perfil);
    exit;
}
# El token del formulario (ver apps/config/csrf.php). Abre una sesion
# sin nadie adentro: login y registro son las unicas paginas que la
# abren antes de entrar, porque su formulario tambien lo lleva.
tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title>Crear una cuenta · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/tema.js"></script>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="acceso">
  <header class="panel panel-marmol">
    <a class="marca" href="index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
    <p class="epigrafe" lang="grc">ἀρετή</p>
    <h1>Cada prueba tiene un solo <em>vencedor.</em></h1>
    <p class="intro">No hay segundo ni tercer puesto. Cada torneo tiene una tabla clara, un calendario cumplido y un ganador que nadie discute.</p>
    <span class="etiqueta">Un producto de Agón</span>
  </header>
  <main id="contenido" class="panel panel-form">
    <form action="../apps/controllers/registroController.php" method="post" id="alta">
      <?php echo campoCsrf(); ?>
      <span class="etiqueta">Primera vez</span>
      <h2>Crear una cuenta</h2>
      <label>Nombre<input type="text" name="nombre" placeholder="Ana" required minlength="2" maxlength="40" autocomplete="given-name"></label>
      <label>Apellido<input type="text" name="apellido" placeholder="Pereira" required minlength="2" maxlength="40" autocomplete="family-name"></label>
      <label>Correo<input type="email" name="correo" placeholder="tu@correo.com" required maxlength="120" autocomplete="email" spellcheck="false" autocapitalize="off"></label>
      <div class="campo">
        <label>Contraseña<input type="password" name="password" required minlength="10" autocomplete="new-password" aria-describedby="ayuda-clave"></label>
        <p class="ayuda-campo" id="ayuda-clave">Mínimo 10 caracteres.</p>
      </div>
      <div class="campo">
        <label>Alias en juego · opcional<input type="text" name="alias" placeholder="ana_p" maxlength="20" pattern="[A-Za-z0-9_]+" title="Letras, números y guion bajo" aria-describedby="ayuda-alias" spellcheck="false" autocapitalize="off"></label>
        <p class="ayuda-campo" id="ayuda-alias">Letras, números y guion bajo; hasta 20 caracteres.</p>
      </div>
      <label class="opcion" style="border:none;padding:0"><input type="checkbox" name="terminos" required> <span>Conforme con los términos</span></label>
      <button class="btn btn-primario" type="submit">Crear una cuenta</button>
      <span class="etiqueta" style="text-transform:none;letter-spacing:.04em;text-align:center">¿Ya tenés cuenta? <a href="login.php" style="color:var(--olivo);text-decoration:underline;text-underline-offset:3px">Iniciar sesión</a></span>
    </form>
  </main>
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
