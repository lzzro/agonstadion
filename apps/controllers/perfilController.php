<?php
# =====================================================================
# Controlador: perfil
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Muestra los datos de quien tiene la sesion abierta, y guarda los
# cambios que se manden desde el formulario de la misma pagina.
#
# Es la primera pantalla que exige haber iniciado sesion. Quien llega
# sin sesion vigente va a parar a la pagina de acceso: no se le muestra
# un perfil vacio ni un aviso, porque no hay perfil que mostrar.
#
# De aca sale la primera aplicacion real de apps/config/sesion.php: si
# pasaron mas de 30 minutos sin actividad, sesionVigente() cierra la
# sesion y este controlador manda al acceso.
#
# Quien esta es el id guardado en la sesion, nunca un id que venga por
# formulario o por la direccion. Si viniera de afuera, cambiando un
# numero en la URL cualquiera editaria el perfil de otro.
#
# El correo no se edita: identifica la cuenta. La contrasena tampoco:
# se cambia por su propio camino. El formulario solo manda los cuatro
# campos que si son del perfil.
#
# Recibe cuatro formularios, que se distinguen por el campo "accion":
#   datos      nombre, apellido, alias y presentacion (el de siempre;
#              si no llega "accion" tambien es este)
#   foto       la foto de perfil
#   portada    la imagen de portada
#   pedir_rol  el pedido del rol de organizador, que despues aprueba o
#              rechaza la administracion (ver adminController.php)
#   crear_equipo  un equipo nuevo, con esta cuenta de capitan (ver
#              EquipoRepositorio): con el, se pide lugar en una liga
#              desde la pagina de la liga
# Todos traen el token de config/csrf.php. Sin el, o con uno que no es
# el de esta sesion, no se toca nada: se responde 403 con el aviso.
# Las dos imagenes pasan por ImagenSubida, que decide si se aceptan.
# Cada una va al mismo lugar: una carpeta que no ejecuta nada (ver el
# .htaccess de public/subidas/). Al reemplazar una imagen, la anterior
# se borra del disco: no quedan archivos de nadie dando vueltas.
#
# Ademas prepara lo que muestran las pestanas del perfil: los torneos
# de la persona y los equipos que capitanea, sacados de la base.
#
# ?aviso=organizador en la direccion: llega desde crear.php o el panel
# sin el rol de organizador. La tarjeta de los roles lo explica al lado
# del boton de pedirlo (y el titulo empieza con "Aviso ·").
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/ImagenSubida.php';
require_once __DIR__ . '/../models/PedidoRolRepositorio.php';
require_once __DIR__ . '/../models/Equipo.php';
require_once __DIR__ . '/../models/EquipoRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Desde donde alcanzar css, js e imagenes, y las otras dos direcciones
# de la cabecera. Si quien llama no las dejo preparadas, vale la
# instalacion local. Ver apps/index.php.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_salir))   { $ruta_salir   = 'salirController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }

# Donde se guardan las imagenes, en el disco. En el hosting la deja
# preparada el puente, porque ahi la carpeta publica es public_html.
if (!isset($carpeta_subidas)) { $carpeta_subidas = __DIR__ . '/../../public/subidas'; }

$titulo  = 'Perfil';
$mensaje = '';
$errores = array();
$errores_equipo = array();   # campo del equipo nuevo => mensaje
$valores_equipo = array('nombre' => '', 'ciudad' => '');

# --- 1. Sin sesion vigente no hay nada que mostrar -------------------
if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

# --- 2. Conexion y carga del usuario ---------------------------------
$conexion = conectarBD();
if ($conexion === null) {
    $errores[] = $error_bd;
    include __DIR__ . '/../index.php';
    exit;
}

$repositorio = new UsuarioRepositorio($conexion);
$auditorias  = new AuditoriaRepositorio($conexion);
$pedidos     = new PedidoRolRepositorio($conexion);

$usuario = $repositorio->buscarPorId($id_usuario);

# La sesion dice que hay alguien, pero la cuenta ya no esta o quedo
# dada de baja. Se cierra la sesion y se vuelve al acceso: seguir
# adelante seria dejar entrar a una cuenta que no existe.
if ($usuario === null || !$usuario->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

# --- 3. Lo que llegue por POST ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = isset($_POST['accion']) ? $_POST['accion'] : 'datos';

    # Si el pedido entero supera el limite de PHP (post_max_size), PHP
    # lo descarta sin avisar: llegan $_POST y $_FILES vacios. Solo una
    # imagen puede pesar tanto, asi que el aviso es el del tamano.
    if (empty($_POST) && empty($_FILES)
        && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $errores[] = 'La imagen supera los 2 MB.';

    } elseif (!csrfValido()) {
        # Sin el token de esta sesion no se hace nada: ni datos, ni
        # imagenes, ni pedidos.
        $errores[] = rechazarCsrf();

    } elseif ($accion === 'pedir_rol') {

        # --- 3a. Pedido del rol de organizador ------------------------
        # El rol pedido es siempre organizador: no se lee del formulario,
        # asi nadie pide "administrador" cambiando un campo oculto.
        # Quien ya es organizador no tiene nada que pedir. Que no haya
        # dos pedidos pendientes a la vez lo asegura la base (ver
        # PedidoRolRepositorio::crear); esta comprobacion solo da un
        # aviso mas claro.
        $repositorio->cargarRoles($usuario);
        $anterior = $pedidos->ultimoDe($id_usuario, 'organizador');

        if ($usuario->tieneRol('organizador')) {
            $errores[] = 'La cuenta ya tiene el rol de organizador.';
        } elseif ($anterior !== null && $anterior->estaPendiente()) {
            $errores[] = 'Ya hay un pedido en revision.';
        } else {
            $errores = $pedidos->crear($usuario, 'organizador');
            if (empty($errores)) {
                $nuevo = $pedidos->ultimoDe($id_usuario, 'organizador');
                $mensaje = 'El pedido del rol de organizador queda en revision.';
                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'pedido_rol', 'pedido_rol',
                    ($nuevo === null) ? null : $nuevo->getIdPedidoRol(),
                    'Rol organizador', $ip));
            }
        }

        # Los roles se vuelven a cargar abajo, completos.
        $usuario = $repositorio->buscarPorId($id_usuario);

    } elseif ($accion === 'crear_equipo') {

        # --- 3d. Un equipo nuevo, con esta cuenta de capitan ----------
        # El capitan es siempre la cuenta de la sesion.
        $valores_equipo['nombre'] = (isset($_POST['nombre_equipo']) && is_string($_POST['nombre_equipo']))
                                    ? trim($_POST['nombre_equipo']) : '';
        $valores_equipo['ciudad'] = (isset($_POST['ciudad_equipo']) && is_string($_POST['ciudad_equipo']))
                                    ? trim($_POST['ciudad_equipo']) : '';

        $equipo = new Equipo(null, $valores_equipo['nombre'],
                             ($valores_equipo['ciudad'] === '') ? null : $valores_equipo['ciudad'], $usuario);
        $resultado = (new EquipoRepositorio($conexion))->crearConCapitan($equipo);

        if (empty($resultado)) {
            $mensaje = 'El equipo ' . $equipo->getNombre() . ' queda armado, con esta cuenta de capitán.';
            $valores_equipo = array('nombre' => '', 'ciudad' => '');
            $auditorias->registrar(new Auditoria(
                null, $usuario, 'equipo', 'alta', $equipo->getIdEquipo(),
                mb_substr('Equipo ' . $equipo->getNombre(), 0, 255), $ip));
        } else {
            foreach ($resultado as $error) {
                if (strpos($error, 'ciudad') !== false) {
                    $errores_equipo['ciudad'] = $error;
                } else {
                    $errores_equipo['nombre'] = $error;
                }
            }
            $errores = $resultado;
        }

    } elseif ($accion === 'foto' || $accion === 'portada') {

        # --- 3b. Foto de perfil o portada ----------------------------
        # Otra vez: la imagen es SIEMPRE de quien tiene la sesion. Un
        # id_usuario que llegue en el formulario ni se lee.
        $imagen  = new ImagenSubida(isset($_FILES['imagen']) ? $_FILES['imagen'] : null);
        $errores = $imagen->validar();

        if (empty($errores)) {
            $anterior = ($accion === 'foto') ? $usuario->getFotoPerfil() : $usuario->getFotoPortada();
            $nuevo    = $imagen->guardarEn($carpeta_subidas);

            if ($nuevo === null) {
                $errores[] = 'La imagen no se puede guardar por ahora.';

            } elseif (!$repositorio->actualizarImagen($usuario, $accion, $nuevo)) {
                # La base no la tomo: el archivo recien guardado no le
                # sirve a nadie y se borra.
                @unlink($carpeta_subidas . '/' . $nuevo);
                $errores[] = 'La imagen no se puede guardar por ahora.';

            } else {
                # La anterior se borra del disco. Solo si su nombre tiene
                # la forma de los que genera el sistema: asi un valor
                # raro nunca termina borrando otra cosa.
                if (ImagenSubida::nombreValido($anterior)
                    && is_file($carpeta_subidas . '/' . $anterior)) {
                    @unlink($carpeta_subidas . '/' . $anterior);
                }

                $que = ($accion === 'foto') ? 'foto de perfil' : 'portada';
                $mensaje = ($accion === 'foto') ? 'La foto de perfil queda cargada.'
                                                : 'La portada queda cargada.';
                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'modificacion',
                    $usuario->getIdUsuario(), 'Carga de ' . $que, $ip));

                $usuario = $repositorio->buscarPorId($id_usuario);
            }
        }

    } else {

        # --- 3c. Datos del perfil -------------------------------------
        $nombre       = isset($_POST['nombre'])       ? trim($_POST['nombre'])       : '';
        $apellido     = isset($_POST['apellido'])     ? trim($_POST['apellido'])     : '';
        $alias        = isset($_POST['alias'])        ? trim($_POST['alias'])        : '';
        $presentacion = isset($_POST['presentacion']) ? trim($_POST['presentacion']) : '';

        # Los campos vacios que la base admite en NULL viajan como NULL y
        # no como cadena vacia, para que la columna quede igual que cuando
        # nunca se completo.
        if ($alias === '')        { $alias = null; }
        if ($presentacion === '') { $presentacion = null; }

        if (empty($nombre) || empty($apellido)) {
            $errores[] = 'El nombre y el apellido son obligatorios.';
        }

        if (empty($errores)) {
            # Se arma un Usuario nuevo con los campos editados y el resto
            # tal como esta en la base. Asi el correo, la clave y la fecha
            # de alta llegan intactos a la comprobacion de actualizarPerfil,
            # sin pasar por el formulario ni una sola vez.
            $editado = new Usuario(
                $usuario->getIdUsuario(),
                $usuario->getCorreo(),
                $usuario->getHashPassword(),
                $nombre,
                $apellido,
                $alias,
                $presentacion,
                $usuario->getActivo(),
                $usuario->getFechaAlta(),
                $usuario->getFotoPerfil(),
                $usuario->getFotoPortada()
            );

            $errores = $repositorio->actualizarPerfil($editado);

            if (empty($errores)) {
                $usuario = $editado;
                $mensaje = 'El perfil queda guardado.';

                # El nombre que se saluda en la sesion se actualiza tambien,
                # o seguiria apareciendo el anterior hasta el proximo ingreso.
                $_SESSION['nombre'] = $usuario->getNombre();

                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'modificacion',
                    $usuario->getIdUsuario(), null, $ip));
            }
        }
    }
}

# --- 4. Roles, pedido, torneos y vista -------------------------------
$repositorio->cargarRoles($usuario);

# El ultimo pedido del rol de organizador, para que la vista sepa que
# mostrar: nada (ya es organizador), "en revision", o el boton de
# pedirlo (nunca lo pidio, o se lo rechazaron y puede volver a pedir).
$pedido_organizador = $usuario->tieneRol('organizador') ? null
                    : $pedidos->ultimoDe($id_usuario, 'organizador');

# El aviso de crear.php y del panel, solo si de verdad falta el rol.
$aviso_organizador = isset($_GET['aviso']) && is_string($_GET['aviso']) && $_GET['aviso'] === 'organizador'
                     && !$usuario->tieneRol('organizador');

# Los equipos que capitanea, para la pestana Mis torneos.
$equipos = (new EquipoRepositorio($conexion))->listarDeCapitan($usuario);

# Los torneos de la persona, de la base. null si la consulta falla,
# para que la vista no confunda "no se pudo leer" con "ninguno".
$torneos = new TorneoRepositorio($conexion);
$inscripciones = $torneos->buscarPorUsuario($id_usuario);

$conexion->close();

include __DIR__ . '/../perfil.php';
