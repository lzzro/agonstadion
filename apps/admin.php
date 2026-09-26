<?php
# =====================================================================
# Vista de administracion
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La incluye adminController.php, y solo despues de comprobar que la
# cuenta de la sesion tiene el rol administrador. Recibe:
#   $administrador  la cuenta de la sesion (Usuario, con roles)
#   $pendientes     pedidos de rol pendientes (arreglo de PedidoRol)
#   $lista          cuentas con roles y ultimo acceso, o null si no se
#                   pudo leer (ver UsuarioRepositorio::listarConRoles)
#   $registro       ultimas filas de la auditoria (arreglo de
#                   Auditoria), o null si no se pudo leer
#   $mensaje, $errores
#   $ruta_publica, $ruta_perfil, $ruta_admin   direcciones
#
# Reemplaza a la maqueta public/admin.html. Lo que habia ahi:
#   Usuarios administrativos   REAL: todas las cuentas de la base, con
#                              sus roles. Las cuentas inventadas de la
#                              maqueta (Comunidad Vortice, Club Sur...)
#                              no estan: no existen en la base.
#   Registro de auditoria      REAL: las ultimas filas de la tabla.
#   Modulos del sistema        DE MUESTRA: la base tiene los modulos
#                              pero no si estan encendidos o no. Van
#                              con la marca "De muestra".
#   Ultimo respaldo            FUERA: el sitio no ve los respaldos, que
#                              hace un script del servidor.
# Y se suma lo nuevo: los pedidos de rol pendientes.
#
# Tiene su propio menu, como tenia la maqueta: enlaces a cada seccion
# de la misma pagina. Ninguno lleva la marca "activo", porque no hay
# vistas que cambien: es una sola pagina que se recorre.
#
# Todo lo que viene de la base se imprime con htmlspecialchars.
# =====================================================================

if (!isset($mensaje)) { $mensaje = ''; }
if (!isset($errores)) { $errores = array(); }
if (!isset($pendientes)) { $pendientes = array(); }
if (!isset($lista)) { $lista = null; }
if (!isset($registro)) { $registro = null; }
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }

require_once __DIR__ . '/cabecera.php';
require_once __DIR__ . '/config/csrf.php';

# 2026-09-25 17:42:05 -> 25 sep 2026 · 17:42
function fechaRegistro($fecha)
{
    $meses = array(1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
                   'jul', 'ago', 'sep', 'oct', 'nov', 'dic');
    $fecha = (string)$fecha;
    $partes = explode('-', substr($fecha, 0, 10));
    if (count($partes) !== 3 || !isset($meses[(int)$partes[1]])) {
        return '';
    }
    return (int)$partes[2] . ' ' . $meses[(int)$partes[1]] . ' ' . $partes[0]
         . ' · ' . substr($fecha, 11, 5);
}

# Como se nombra cada accion de la auditoria en pantalla: en
# sustantivos, como pide la voz del sitio.
$acciones = array(
    'alta'         => 'Alta de cuenta',
    'baja'         => 'Baja de cuenta',
    'modificacion' => 'Modificación de perfil',
    'login_ok'     => 'Inicio de sesión',
    'login_error'  => 'Intento de inicio de sesión',
    'logout'       => 'Cierre de sesión',
    'pedido_rol'   => 'Pedido de rol',
    'aprobacion'   => 'Aprobación de rol',
    'rechazo'      => 'Rechazo de pedido de rol'
);

# Cuentas por rol, para el resumen del costado.
$total = 0;
$con_rol = array('administrador' => 0, 'organizador' => 0);
if (is_array($lista)) {
    $total = count($lista);
    foreach ($lista as $cuenta) {
        foreach ($con_rol as $nombre => $cantidad) {
            if ($cuenta['usuario']->tieneRol($nombre)) { $con_rol[$nombre]++; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#F3EEE3">
  <meta name="description" content="Stadion: plataforma modular de torneos de Agón.">
  <title><?php if (!empty($errores)) { echo 'Aviso · '; } ?>Administración · Stadion</title>
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
  <?php accionesCabecera($ruta_publica, $ruta_perfil, $administrador); ?>
</header>
<nav><a href="#pedidos">Pedidos</a><a href="#cuentas">Cuentas</a><a href="#modulos">Módulos</a><a href="#auditoria">Auditoría</a></nav>
<main id="contenido">
<section>
  <p class="epigrafe" lang="grc">ἑλλανοδίκαι</p>
  <p class="etiqueta">Agón · Stadion</p>
  <h1>Administración</h1>
</section>

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

<section class="tarjeta" id="pedidos">
  <h2>Pedidos de rol</h2>
<?php if (empty($pendientes)) { ?>
  <p>Ningún pedido en revisión.</p>
<?php } else { ?>
<?php   # Un renglon por pedido, no una tabla: en el telefono una tabla se
        # desplaza a lo ancho y los botones quedarian fuera de la vista. ?>
  <div class="pedidos">
<?php   foreach ($pendientes as $pedido) {
          $quien = $pedido->getUsuario();
          $propio = ((int)$quien->getIdUsuario() === (int)$administrador->getIdUsuario());
          # Los botones dicen de quien es el pedido: con varios pedidos en
          # la lista, un lector de pantalla que recorre solo los botones
          # oiria "Aprobar, Aprobar, Aprobar".
          $de_quien = htmlspecialchars($quien->getNombreCompletoVisible()); ?>
    <div class="pedido">
      <span class="pedido-texto"><strong><?php echo htmlspecialchars($quien->getNombreCompletoVisible()); ?></strong><span class="pedido-dato"><?php echo htmlspecialchars($quien->getCorreo()); ?> · <?php echo htmlspecialchars($pedido->getRol()->getNombre()); ?> · <span class="fecha"><?php echo htmlspecialchars(fechaRegistro($pedido->getFechaPedido())); ?></span></span></span>
<?php     if ($propio) { ?>
      <small>Lo resuelve otra cuenta de la administración.</small>
<?php     } else { ?>
      <span class="resolver-pedido">
        <form action="<?php echo htmlspecialchars($ruta_admin); ?>" method="post">
          <?php echo campoCsrf(); ?>
          <input type="hidden" name="id_pedido" value="<?php echo (int)$pedido->getIdPedidoRol(); ?>">
          <input type="hidden" name="accion" value="aprobar">
          <button class="btn btn-primario" type="submit" aria-label="Aprobar el pedido de <?php echo $de_quien; ?>">Aprobar</button>
        </form>
        <form action="<?php echo htmlspecialchars($ruta_admin); ?>" method="post">
          <?php echo campoCsrf(); ?>
          <input type="hidden" name="id_pedido" value="<?php echo (int)$pedido->getIdPedidoRol(); ?>">
          <input type="hidden" name="accion" value="rechazar">
          <button class="btn" type="submit" aria-label="Rechazar el pedido de <?php echo $de_quien; ?>">Rechazar</button>
        </form>
      </span>
<?php     } ?>
    </div>
<?php   } ?>
  </div>
<?php } ?>
</section>

<section class="tarjeta" id="cuentas">
  <h2>Cuentas</h2>
<?php if ($lista === null) { ?>
  <p>La lista de cuentas no se puede leer por ahora.</p>
<?php } else { ?>
  <div class="tabla-scroll" tabindex="0" role="region" aria-label="Cuentas">
  <table>
    <thead><tr><th>Nombre</th><th>Correo</th><th>Roles</th><th>Estado</th><th>Último acceso</th></tr></thead>
    <tbody>
<?php   foreach ($lista as $cuenta) {
          $u = $cuenta['usuario'];
          $roles = array();
          foreach ($u->getRoles() as $rol) { $roles[] = $rol->getNombre(); } ?>
      <tr>
        <td><?php echo htmlspecialchars($u->getNombreCompletoVisible()); ?></td>
        <td><?php echo htmlspecialchars($u->getCorreo()); ?></td>
        <td><?php echo htmlspecialchars(empty($roles) ? 'Sin roles' : implode(' · ', $roles)); ?></td>
        <td><?php if ($u->estaActivo()) { ?><span class="estado estado-inscripcion">Activa</span><?php } else { ?><span class="estado estado-cerrado">De baja</span><?php } ?></td>
        <td class="fecha"><?php echo ($cuenta['ultimo_acceso'] === null) ? 'Sin acceso' : htmlspecialchars(fechaRegistro($cuenta['ultimo_acceso'])); ?></td>
      </tr>
<?php   } ?>
    </tbody>
  </table>
  </div>
<?php } ?>
</section>

<section class="tarjeta" id="modulos">
  <div class="fila" style="justify-content:space-between"><h2>Módulos del sistema</h2><span class="muestra">De muestra</span></div>
    <div class="modulo"><span class="modulo-texto"><strong>Liga</strong><span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Todos contra todos</span></span><span class="modulo-estado"><span class="etiqueta">Activo</span><span class="llave-visual encendida" role="img" aria-label="Activo"></span></span></div>
    <div class="modulo"><span class="modulo-texto"><strong>Eliminación directa</strong><span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Llaves</span></span><span class="modulo-estado"><span class="etiqueta">Activo</span><span class="llave-visual encendida" role="img" aria-label="Activo"></span></span></div>
    <div class="modulo"><span class="modulo-texto"><strong>Sistema suizo</strong><span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Emparejamiento por puntaje</span></span><span class="modulo-estado"><span class="etiqueta">Activo</span><span class="llave-visual encendida" role="img" aria-label="Activo"></span></span></div>
    <div class="modulo"><span class="modulo-texto"><strong>Consulta pública</strong><span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Torneos visibles sin cuenta</span></span><span class="modulo-estado"><span class="etiqueta">Activo</span><span class="llave-visual encendida" role="img" aria-label="Activo"></span></span></div>
    <div class="modulo"><span class="modulo-texto"><strong>Registro de participantes</strong><span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Alta de nuevas cuentas</span></span><span class="modulo-estado"><span class="etiqueta">Activo</span><span class="llave-visual encendida" role="img" aria-label="Activo"></span></span></div>
    <div class="modulo"><span class="modulo-texto"><strong>Exportación CSV</strong><span class="etiqueta" style="text-transform:none;letter-spacing:.04em">Descarga de tablas y resultados</span><span class="chip">Opcional · en pruebas</span></span><span class="modulo-estado"><span class="etiqueta">En pausa</span><span class="llave-visual" role="img" aria-label="En pausa"></span></span></div>
</section>

<section class="tarjeta" id="auditoria">
  <h2>Registro de auditoría</h2>
<?php if ($registro === null) { ?>
  <p>El registro no se puede leer por ahora.</p>
<?php } elseif (empty($registro)) { ?>
  <p>Sin movimientos registrados.</p>
<?php } else { ?>
  <div class="registro">
<?php   foreach ($registro as $fila) {
          $responsable = $fila->getUsuario();
          if ($responsable !== null) {
              $quien = $responsable->getNombreCompletoVisible();
          } elseif ($fila->getAccion() === 'login_error') {
              $quien = 'Sin cuenta';
          } else {
              $quien = 'Sistema';
          }
          $que = isset($acciones[$fila->getAccion()]) ? $acciones[$fila->getAccion()] : $fila->getAccion();
          # Las cargas de imagen traen su propio nombre en el detalle
          # ("Carga de foto de perfil"); el resto lo suma al lado.
          if ($fila->getAccion() === 'modificacion' && !empty($fila->getDetalle())) {
              $que = $fila->getDetalle();
          } elseif (!empty($fila->getDetalle())) {
              $que .= ' · ' . $fila->getDetalle();
          } ?>
    <div class="registro-linea"><span class="cuando"><?php echo htmlspecialchars(fechaRegistro($fila->getFechaHora())); ?></span><span class="quien"><?php echo htmlspecialchars($quien); ?></span><span><?php echo htmlspecialchars($que); ?></span></div>
<?php   } ?>
  </div>
  <p><small><?php echo (count($registro) === 1) ? 'El último movimiento.' : 'Los últimos ' . count($registro) . ' movimientos.'; ?></small></p>
<?php } ?>
</section>
</main>
<aside>
<div class="tarjeta">
  <span class="etiqueta">Cuentas</span>
<?php if ($lista === null) { ?>
  <p>Sin datos por ahora.</p>
<?php } else { ?>
  <h3><?php echo $total; ?> <?php echo ($total === 1) ? 'cuenta' : 'cuentas'; ?></h3>
  <p><?php echo $con_rol['administrador']; ?> en la administración · <?php echo $con_rol['organizador']; ?> <?php echo ($con_rol['organizador'] === 1) ? 'organizador' : 'organizadores'; ?></p>
<?php } ?>
</div>
<div class="tarjeta">
  <span class="etiqueta">Pedidos en revisión</span>
  <h3><?php echo count($pendientes); ?></h3>
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
