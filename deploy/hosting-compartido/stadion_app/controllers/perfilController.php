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
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Desde donde alcanzar css, js e imagenes. Si quien llama no lo dejo
# preparado, vale la instalacion local. Ver apps/index.php.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }

$titulo  = 'Perfil';
$mensaje = '';
$errores = array();

# --- 1. Sin sesion vigente no hay nada que mostrar -------------------
if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.html');
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

$usuario = $repositorio->buscarPorId($id_usuario);

# La sesion dice que hay alguien, pero la cuenta ya no esta o quedo
# dada de baja. Se cierra la sesion y se vuelve al acceso: seguir
# adelante seria dejar entrar a una cuenta que no existe.
if ($usuario === null || !$usuario->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.html');
    exit;
}

# --- 3. Guardado, si el formulario llego por POST --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
            $usuario->getFechaAlta()
        );

        $errores = $repositorio->actualizarPerfil($editado);

        if (empty($errores)) {
            $usuario = $editado;
            $mensaje = 'El perfil queda guardado.';

            # El nombre que se saluda en la sesion se actualiza tambien,
            # o seguiria apareciendo el anterior hasta el proximo ingreso.
            $_SESSION['nombre'] = $usuario->getNombre();

            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
            $auditorias->registrar(new Auditoria(
                null, $usuario, 'usuario', 'modificacion',
                $usuario->getIdUsuario(), null, $ip));
        }
    }
}

# --- 4. Roles y vista ------------------------------------------------
$repositorio->cargarRoles($usuario);

$conexion->close();

include __DIR__ . '/../perfil.php';
