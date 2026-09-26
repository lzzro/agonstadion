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
# Con errores, el titulo de la pestana empieza con "Aviso ·": la pagina
# llega entera del servidor, asi que el role="alert" de la lista no se
# anuncia solo, y el titulo es lo primero que lee el lector de pantalla.
# El perfil y la administracion hacen lo mismo.
#
# Las rutas del CSS, del JS y de los enlaces son relativas a la
# direccion del controlador, que es la que queda en el navegador. Esa
# direccion cambia segun donde este instalado el sitio:
#
#   en la maquina local   /apps/controllers/loginController.php
#   en el hosting         /controllers/login.php
#
# Por eso no van escritas a mano sino a traves de $ruta_publica, que
# quien llama puede dejar preparada. Si nadie la define queda el valor
# de la instalacion local, que es la de todos los dias.
# =====================================================================

if (!isset($titulo))  { $titulo  = 'Stadion'; }
if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }

# El perfil es el unico destino que no esta en la carpeta publica sino
# al lado de los controladores, asi que su direccion se arma aparte.
if (!isset($ruta_perfil)) { $ruta_perfil = 'perfilController.php'; }
# La cuenta que acaba de entrar, si el controlador la deja preparada
# (loginController lo hace). Sin ella, la cabecera usa la sesion.
if (!isset($persona_cabecera)) { $persona_cabecera = null; }

require_once __DIR__ . '/cabecera.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <title><?php if (!empty($errores)) { echo 'Aviso · '; } ?><?php echo htmlspecialchars($titulo); ?> · Stadion</title>
  <link rel="icon" href="<?php echo $ruta_publica; ?>/img/stadion.png">
  <link rel="stylesheet" href="<?php echo $ruta_publica; ?>/css/style.css">
  <script src="<?php echo $ruta_publica; ?>/js/tema.js"></script>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="pagina">
<header>
  <a class="marca" href="<?php echo $ruta_publica; ?>/index.php"><svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <path d="M 26,100 A 146.9 146.9 0 0 1 174,100 A 146.9 146.9 0 0 1 26,100 Z" fill="none" stroke="currentColor" stroke-width="5"/>
  <rect x="22" y="86" width="6" height="28" fill="currentColor"/>
  <rect x="172" y="86" width="6" height="28" fill="currentColor"/>
</svg><span>STADION</span></a>
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $persona_cabecera); ?>
</header>
<main id="contenido">
<section>
  <p class="etiqueta">Acceso</p>
  <h1><?php echo htmlspecialchars($titulo); ?></h1>

<?php if (!empty($errores)) { ?>
  <div role="alert">
  <ul class="avisos">
<?php   foreach ($errores as $error) { ?>
    <li><?php echo htmlspecialchars($error); ?></li>
<?php   } ?>
  </ul>
  </div>
<?php } ?>

<?php if ($mensaje !== '') { ?>
  <p class="intro" role="status"><?php echo htmlspecialchars($mensaje); ?></p>
<?php } ?>

  <div class="fila">
<?php if (isset($_SESSION['id_usuario'])) { ?>
    <a class="btn btn-primario" href="<?php echo $ruta_perfil; ?>">Ver el perfil</a>
<?php } else { ?>
    <a class="btn" href="<?php echo $ruta_publica; ?>/login.php">Volver al acceso</a>
<?php } ?>
    <a class="btn" href="<?php echo $ruta_publica; ?>/index.php">Ir al inicio</a>
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
