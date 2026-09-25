<?php
# =====================================================================
# Token de formulario (CSRF)
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# El problema: el navegador manda la cookie de sesion con todo pedido
# a este sitio, venga de donde venga. Si una pagina ajena tiene un
# formulario oculto que apunta a "aprobar pedido" y quien la visita es
# un administrador con la sesion abierta, el pedido llega con su
# sesion y el servidor no tiene como distinguirlo de uno legitimo.
#
# La defensa: al armar cada formulario se le agrega un campo oculto con
# un numero al azar que se guarda en la sesion. Al recibirlo, el
# servidor compara los dos. Una pagina ajena no puede leer ese numero
# (el navegador no la deja leer las paginas de este sitio), asi que no
# puede armar un formulario que pase la comparacion.
#
# Es UNO por sesion, no uno por formulario: todos los formularios de la
# misma sesion llevan el mismo. Se cambia al iniciar sesion, para que el
# que se uso antes de entrar no siga sirviendo despues.
#
# Todo formulario que cambia algo lo lleva: perfil, foto, portada,
# cerrar sesion, pedir rol, aprobar y rechazar, y tambien el inicio de
# sesion y el alta de cuenta.
# =====================================================================

# Devuelve el token de la sesion, y lo crea si todavia no hay uno.
# Abre la sesion si hace falta: por eso, en una pagina, se llama antes
# de que salga una sola linea de HTML.
function tokenCsrf()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
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

# true si el formulario recibido trae el token de esta sesion.
# Sin token, con uno equivocado, o sin sesion: false.
function csrfValido()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['token_csrf']) || !is_string($_SESSION['token_csrf'])
        || !isset($_POST['token_csrf']) || !is_string($_POST['token_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['token_csrf'], $_POST['token_csrf']);
}

# Cambia el token. Se llama al iniciar sesion.
function renovarCsrf()
{
    unset($_SESSION['token_csrf']);
    return tokenCsrf();
}

# Lo que se muestra cuando un formulario llega sin el token correcto.
# Pasa, sin mala intencion, si la pagina quedo abierta mas de media
# hora y la sesion vencio en el medio.
function mensajeCsrf()
{
    return 'El formulario no corresponde a esta sesion. '
         . 'Conviene volver a abrir la pagina y repetirlo.';
}

# Responde que el pedido no se acepta: codigo 403 y el aviso.
# Quien la llama decide que vista mostrar despues.
function rechazarCsrf()
{
    http_response_code(403);
    return mensajeCsrf();
}
