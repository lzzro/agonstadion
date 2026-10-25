<?php
# =====================================================================
# Vigencia de la sesion
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Cualquier pagina que exija haber iniciado sesion incluye este archivo
# y llama a sesionVigente(). Si devuelve false, no hay a quien mostrarle
# la pagina.
#
# La ventana es de 30 minutos (1800 segundos) y se renueva con cada
# paso: no es un limite fijo desde el inicio de sesion, sino desde la
# ultima actividad. Asi una persona que sigue usando el sitio no se
# queda afuera a mitad de camino, y una que se fue de la maquina deja
# de tener sesion a la media hora.
#
# Media hora es el punto medio habitual: lo bastante corto para que una
# maquina compartida no quede abierta toda la tarde, y lo bastante
# largo para armar un torneo sin apuro.
#
# Al vencer se hace session_unset() y session_destroy(): el primero
# vacia los datos de esta ejecucion, el segundo borra la sesion del
# servidor. Los dos juntos, porque uno solo deja la mitad en pie.
# =====================================================================

# Tiempo maximo sin actividad antes de cerrar la sesion, en segundos.
define('LIMITE_INACTIVIDAD', 1800);

# ---------------------------------------------------------------------
# Abre la sesion (si no esta abierta) con la cookie protegida
# (Ciberseguridad, tercera entrega):
#   httponly  el JavaScript de la pagina no la puede leer
#   samesite  Lax: otro sitio no la manda en un formulario armado
#   secure    con HTTPS, solo viaja cifrada (sin HTTPS, en XAMPP, no)
# y en modo estricto: un identificador que no creo el servidor no se
# acepta (otra defensa contra la fijacion de sesion, ademas de
# session_regenerate_id al entrar).
# ---------------------------------------------------------------------
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

# ---------------------------------------------------------------------
# Devuelve true si hay una sesion iniciada y todavia vigente.
# De paso corre la marca de actividad hacia adelante, asi la ventana
# se renueva sola mientras haya movimiento.
# ---------------------------------------------------------------------
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

# ---------------------------------------------------------------------
# Cierra la sesion por completo.
# Ademas de vaciarla y borrarla del servidor, le pide al navegador que
# tire la cookie: si no, seguiria mandando un identificador que ya no
# corresponde a nada, y cada pagina abriria una sesion vacia con el.
#
# Y deja preparado un identificador nuevo, por si en el mismo pedido se
# abre otra sesion (login.php lo hace, para el token de su formulario,
# cuando la anterior acaba de vencer). Sin eso, la sesion nueva reusaria
# el identificador de la cerrada, y el navegador, que acaba de recibir
# la orden de tirar esa cookie, no la volveria a mandar.
# ---------------------------------------------------------------------
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

# ---------------------------------------------------------------------
# Aviso para mostrar cuando la sesion vence.
# ---------------------------------------------------------------------
function mensajeSesionVencida()
{
    return 'La sesión se cierra sola después de media hora sin uso. '
         . 'El acceso vuelve a abrirse desde la página de ingreso.';
}
