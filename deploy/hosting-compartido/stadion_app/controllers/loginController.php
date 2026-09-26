<?php
# =====================================================================
# Controlador: inicio de sesion
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Recibe por POST el formulario de "iniciar sesion" de
# public/login.php, busca el usuario por correo y comprueba la clave
# con verificarClave(), que usa password_verify().
#
# Al entrar se cambia el identificador de sesion con
# session_regenerate_id(true). Si no, alguien podria fijar de antemano
# el identificador en el navegador de la victima y quedarse con la
# sesion ya iniciada. El true ademas borra el archivo del identificador
# viejo, para que no quede una sesion suelta.
#
# El mensaje de error es el mismo cuando el correo no existe y cuando
# la clave no coincide. Es a proposito: si fueran distintos, cualquiera
# podria averiguar que correos tienen cuenta probando de a uno.
#
# Cada intento, salga bien o mal, queda en la tabla auditoria. Los que
# fallan van sin id de usuario: en el detalle queda el correo que se
# intento, que es lo unico que se sabe con certeza.
#
# Las cuentas de muestra (de_muestra = 1) se rechazan siempre, antes de
# mirar la clave: ver Usuario::esDeMuestra().
#
# Al entrar se guarda ademas la hora de la ultima actividad. Con ella,
# config/sesion.php cierra la sesion que queda media hora sin uso.
#
# El formulario trae el token de config/csrf.php: sin el, o con uno que
# no es el de esta sesion, no se intenta nada y no queda registro (no
# hay intento de verdad que registrar). Al entrar se cambia el token,
# igual que el identificador de sesion.
# =====================================================================

session_start();

require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

$titulo  = 'Inicio de sesion';
$mensaje = '';
$errores = array();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errores[] = 'El inicio de sesion llega desde el formulario de la pagina de acceso.';
} elseif (!csrfValido()) {
    $errores[] = rechazarCsrf();
} else {

    $correo = isset($_POST['correo'])   ? trim($_POST['correo']) : '';
    $clave  = isset($_POST['password']) ? $_POST['password']     : '';

    if (empty($correo) || empty($clave)) {
        $errores[] = 'El correo y la contrasena son obligatorios.';
    }

    if (empty($errores)) {
        $conexion = conectarBD();
        if ($conexion === null) {
            $errores[] = $error_bd;
        } else {
            $repositorio = new UsuarioRepositorio($conexion);
            $auditorias  = new AuditoriaRepositorio($conexion);

            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
            # El detalle de la tabla admite 255 caracteres: se recorta por
            # si llega un correo desmedido.
            $correo_intentado = mb_substr($correo, 0, 255);

            $usuario = $repositorio->buscarPorCorreo($correo);

            if ($usuario !== null && $usuario->esDeMuestra()) {
                # Las cuentas de muestra (las organizadoras de las ligas
                # de muestra) no abren sesion con ninguna clave. Se
                # rechazan antes de mirar la clave, aunque su hash tampoco
                # la aceptaria: son dos cerrojos, no uno. Decir que es de
                # muestra no revela nada: esas cuentas se ven en todas
                # las paginas publicas.
                $errores[] = 'Una cuenta de muestra no abre sesión.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error',
                    $usuario->getIdUsuario(), 'Cuenta de muestra: ' . $correo_intentado, $ip));

            } elseif ($usuario === null || !$usuario->verificarClave($clave)) {
                $errores[] = 'El correo o la contrasena no coinciden.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error', null, $correo_intentado, $ip));

            } elseif (!$usuario->estaActivo()) {
                $errores[] = 'Esa cuenta esta dada de baja.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error',
                    $usuario->getIdUsuario(), $correo_intentado, $ip));

            } else {
                # Identificador nuevo antes de guardar nada en la sesion.
                session_regenerate_id(true);
                renovarCsrf();

                $_SESSION['id_usuario'] = $usuario->getIdUsuario();
                $_SESSION['nombre']     = $usuario->getNombre();

                # Marca de actividad: desde aca cuenta la media hora de
                # inactividad que cierra la sesion (ver config/sesion.php).
                $_SESSION['ultima_actividad'] = time();

                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'login_ok',
                    $usuario->getIdUsuario(), null, $ip));

                $mensaje = 'La sesion queda abierta a nombre de '
                         . $usuario->getNombreCompletoVisible() . '.';

                # La cuenta, para el circulo de la cabecera de la vista
                # de resultado.
                $persona_cabecera = $usuario;
            }
            # Si la auditoria fallara, no se le avisa a quien entra: el
            # inicio de sesion ya paso y el aviso lo confundiria.

            $conexion->close();
        }
    }
}

include __DIR__ . '/../index.php';
