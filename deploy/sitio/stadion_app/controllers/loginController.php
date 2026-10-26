<?php
# Controlador: inicio de sesion

require_once __DIR__ . '/../config/sesion.php';
abrirSesion();

require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

const INTENTOS_ANTES_DEL_BLOQUEO = 5;
const MINUTOS_BLOQUEO = 15;

$titulo  = 'Inicio de sesión';
$mensaje = '';
$errores = array();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errores[] = 'El inicio de sesión llega desde el formulario de la página de acceso.';
} elseif (!csrfValido()) {
    $errores[] = rechazarCsrf();
} else {

    $correo = (isset($_POST['correo']) && is_string($_POST['correo'])) ? trim($_POST['correo']) : '';
    $clave  = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';

    if (empty($correo) || empty($clave)) {
        $errores[] = 'El correo y la contraseña son obligatorios.';
    }

    if (empty($errores)) {
        $conexion = conectarBD();
        if ($conexion === null) {
            $errores[] = $error_bd;
        } else {
            $repositorio = new UsuarioRepositorio($conexion);
            $auditorias  = new AuditoriaRepositorio($conexion);

            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
            # El detalle de la tabla admite 255 caracteres: se recorta por si llega un correo desmedido.
            $correo_intentado = mb_substr($correo, 0, 255);

            $usuario = $repositorio->buscarPorCorreo($correo);

            $fallidos = $auditorias->intentosFallidos($correo_intentado, MINUTOS_BLOQUEO);

            if ($fallidos !== null && $fallidos >= INTENTOS_ANTES_DEL_BLOQUEO) {
                $errores[] = 'Demasiados intentos fallidos con ese correo. El acceso queda en pausa por '
                           . MINUTOS_BLOQUEO . ' minutos.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error', null,
                    mb_substr('Bloqueo: ' . $correo_intentado, 0, 255), $ip));

            } elseif ($usuario !== null && $usuario->esDeMuestra()) {
                $errores[] = 'Una cuenta de muestra no abre sesión.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error',
                    $usuario->getIdUsuario(), 'Cuenta de muestra: ' . $correo_intentado, $ip));

            } elseif ($usuario === null || !$usuario->verificarClave($clave)) {
                $errores[] = 'El correo o la contraseña no coinciden.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error', null, $correo_intentado, $ip));

            } elseif (!$usuario->estaActivo()) {
                $errores[] = 'Esa cuenta está dada de baja.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error',
                    $usuario->getIdUsuario(), $correo_intentado, $ip));

            } else {
                # Identificador nuevo antes de guardar nada en la sesion.
                session_regenerate_id(true);
                renovarCsrf();

                $_SESSION['id_usuario'] = $usuario->getIdUsuario();
                $_SESSION['nombre']     = $usuario->getNombre();

                $_SESSION['ultima_actividad'] = time();

                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'login_ok',
                    $usuario->getIdUsuario(), null, $ip));

                $mensaje = 'La sesión queda abierta a nombre de '
                         . $usuario->getNombreCompletoVisible() . '.';

                # La cuenta, para el circulo de la cabecera de la vista de resultado.
                $persona_cabecera = $usuario;
            }

            $conexion->close();
        }
    }
}

include __DIR__ . '/../index.php';
