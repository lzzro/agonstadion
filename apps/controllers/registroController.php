<?php
# Controlador: alta de usuario

require_once __DIR__ . '/../config/sesion.php';
abrirSesion();

require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

$titulo  = 'Alta de cuenta';
$mensaje = '';
$errores = array();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errores[] = 'El alta llega desde el formulario de la página de acceso.';
} elseif (!csrfValido()) {
    $errores[] = rechazarCsrf();
} else {

    $nombre   = (isset($_POST['nombre']) && is_string($_POST['nombre'])) ? trim($_POST['nombre']) : '';
    $apellido = (isset($_POST['apellido']) && is_string($_POST['apellido'])) ? trim($_POST['apellido']) : '';
    $correo   = (isset($_POST['correo']) && is_string($_POST['correo'])) ? trim($_POST['correo']) : '';
    $clave    = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $alias    = (isset($_POST['alias']) && is_string($_POST['alias'])) ? trim($_POST['alias']) : '';

    if (empty($nombre) || empty($apellido) || empty($correo) || empty($clave)) {
        $errores[] = 'Nombre, apellido, correo y contraseña son obligatorios.';
    }

    # Una casilla sin marcar no se envia, asi que alcanza con isset.
    if (!isset($_POST['terminos'])) {
        $errores[] = 'Falta la conformidad con los términos.';
    }

    if (empty($errores)) {
        # El hash se asigna despues, con asignarClave().
        $usuario = new Usuario(null, $correo, null, $nombre, $apellido,
                               ($alias === '' ? null : $alias));

        $errores = $usuario->asignarClave($clave);

        if (empty($errores)) {
            $conexion = conectarBD();
            if ($conexion === null) {
                $errores[] = $error_bd;
            } else {
                $repositorio = new UsuarioRepositorio($conexion);
                $errores = $repositorio->insertar($usuario);

                if (empty($errores)) {
                    $auditorias = new AuditoriaRepositorio($conexion);
                    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
                    $auditorias->registrar(new Auditoria(
                        null, $usuario, 'usuario', 'alta',
                        $usuario->getIdUsuario(), null, $ip));

                    # Sin el número interno de la cuenta: en pantalla no va ningún valor de la base.
                    $mensaje = 'La cuenta queda abierta a nombre de '
                             . $usuario->getNombreCompletoVisible() . '.';
                }
                $conexion->close();
            }
        }
    }
}

include __DIR__ . '/../index.php';
