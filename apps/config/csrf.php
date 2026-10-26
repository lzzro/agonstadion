<?php
# Token de formulario (CSRF)

require_once __DIR__ . '/sesion.php';

# Devuelve el token de la sesion, y lo crea si todavia no hay uno.
function tokenCsrf()
{
    abrirSesion();
    if (!isset($_SESSION['token_csrf']) || !is_string($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

# El campo oculto que va dentro de cada formulario.
function campoCsrf()
{
    return '<input type="hidden" name="token_csrf" value="'
         . htmlspecialchars(tokenCsrf()) . '">';
}

function csrfValido()
{
    abrirSesion();
    if (!isset($_SESSION['token_csrf']) || !is_string($_SESSION['token_csrf'])
        || !isset($_POST['token_csrf']) || !is_string($_POST['token_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['token_csrf'], $_POST['token_csrf']);
}

# Cambia el token.
function renovarCsrf()
{
    unset($_SESSION['token_csrf']);
    return tokenCsrf();
}

# Lo que se muestra cuando un formulario llega sin el token correcto.
function mensajeCsrf()
{
    return 'El formulario no corresponde a esta sesión. '
         . 'Conviene volver a abrir la página y repetirlo.';
}

# Responde que el pedido no se acepta: codigo 403 y el aviso.
function rechazarCsrf()
{
    http_response_code(403);
    return mensajeCsrf();
}
