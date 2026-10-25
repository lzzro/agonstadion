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
# Bloqueo (tercera entrega): con 5 intentos fallidos con el mismo correo
# en 15 minutos, el inicio de sesion con ese correo queda en pausa hasta
# que pasen 15 minutos del quinto. Se cuenta con las filas login_error de
# la auditoria, que ya guardaban el correo intentado: no hace falta una
# tabla nueva.
#
# Al entrar se guarda ademas la hora de la ultima actividad. Con ella,
# config/sesion.php cierra la sesion que queda media hora sin uso.
#
# El formulario trae el token de config/csrf.php: sin el, o con uno que
# no es el de esta sesion, no se intenta nada y no queda registro (no
# hay intento de verdad que registrar). Al entrar se cambia el token,
# igual que el identificador de sesion.
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
abrirSesion();

require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# El bloqueo tras varios intentos fallidos (politica de contrasenas de
# Ciberseguridad, tercera entrega).
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

    # Solo textos: un campo mandado como arreglo (nombre[]=x) queda vacio
    # (sin esto, trim() corta con un error y la pagina responde 500).
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
            # El detalle de la tabla admite 255 caracteres: se recorta por
            # si llega un correo desmedido.
            $correo_intentado = mb_substr($correo, 0, 255);

            $usuario = $repositorio->buscarPorCorreo($correo);

            # Bloqueo de la politica de contrasenas: con 5 fallos con
            # este correo en los ultimos 15 minutos no se mira la clave,
            # ni siquiera la correcta. El aviso es el mismo exista o no
            # la cuenta. El intento queda en la auditoria con otro
            # detalle, que no suma al conteo (ver intentosFallidos).
            $fallidos = $auditorias->intentosFallidos($correo_intentado, MINUTOS_BLOQUEO);

            if ($fallidos !== null && $fallidos >= INTENTOS_ANTES_DEL_BLOQUEO) {
                $errores[] = 'Demasiados intentos fallidos con ese correo. El acceso queda en pausa por '
                           . MINUTOS_BLOQUEO . ' minutos.';
                $auditorias->registrar(new Auditoria(
                    null, null, 'usuario', 'login_error', null,
                    mb_substr('Bloqueo: ' . $correo_intentado, 0, 255), $ip));

            } elseif ($usuario !== null && $usuario->esDeMuestra()) {
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

                # Marca de actividad: desde aca cuenta la media hora de
                # inactividad que cierra la sesion (ver config/sesion.php).
                $_SESSION['ultima_actividad'] = time();

                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'login_ok',
                    $usuario->getIdUsuario(), null, $ip));

                $mensaje = 'La sesión queda abierta a nombre de '
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
