<?php
# Vigencia de la sesion

# Tiempo maximo sin actividad antes de cerrar la sesion, en segundos.
define('LIMITE_INACTIVIDAD', 1800);

function abrirSesion()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    session_start();
}

# Devuelve true si hay una sesion iniciada y todavia vigente.
function sesionVigente()
{
    abrirSesion();

    # Sin id de usuario no hay sesion iniciada que cuidar.
    if (!isset($_SESSION['id_usuario'])) {
        return false;
    }

    # Una sesion sin marca de actividad no se puede medir: se cierra.
    if (!isset($_SESSION['ultima_actividad'])) {
        cerrarSesion();
        return false;
    }

    $inactividad = time() - (int)$_SESSION['ultima_actividad'];

    if ($inactividad > LIMITE_INACTIVIDAD) {
        cerrarSesion();
        return false;
    }

    $_SESSION['ultima_actividad'] = time();
    return true;
}

# Cierra la sesion por completo.
function cerrarSesion()
{
    abrirSesion();

    session_unset();
    session_destroy();

    if (!headers_sent()) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', array(
            'expires'  => time() - 3600,
            'path'     => $cookie['path'],
            'domain'   => $cookie['domain'],
            'secure'   => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite']
        ));
        session_id(session_create_id());
    }
}

# Aviso para mostrar cuando la sesion vence.
function mensajeSesionVencida()
{
    return 'La sesión se cierra sola después de media hora sin uso. '
         . 'El acceso vuelve a abrirse desde la página de ingreso.';
}
