<?php
# =====================================================================
# Puente: perfil
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Generado por scripts/armar-deploy.sh. No editar a mano.
#
# Este archivo no decide nada: solo llama al controlador de verdad, que
# vive en stadion_app/, fuera de public_html. Asi los modelos y la
# configuracion con la contrasena de la base quedan donde ningun pedido
# web puede alcanzarlos, ni con un .htaccess mal escrito, porque no
# estan dentro de la carpeta que el servidor publica.
#
# Si en el hosting stadion_app/ no quedara al lado de public_html, la
# unica linea que hay que cambiar es la de $APLICACION.
# =====================================================================

# Desde public_html/controllers/, subir dos veces deja en la carpeta de
# la cuenta, donde esta stadion_app/ al lado de public_html/.
$APLICACION = __DIR__ . '/../../stadion_app';

# Donde estan el CSS, el JS y las paginas, vistos desde la direccion de
# este archivo en el navegador (/controllers/...).
$ruta_publica = '..';
$ruta_perfil  = 'perfil.php';
$ruta_salir   = 'salir.php';

# Donde se guardan las fotos de perfil y las portadas: la carpeta
# subidas/ de public_html, al lado de esta. La que tiene el .htaccess
# que impide ejecutar nada.
$carpeta_subidas = __DIR__ . '/../subidas';

if (!is_dir($APLICACION)) {
    http_response_code(500);
    echo 'El sitio no encuentra su aplicacion.';
    exit;
}

require_once $APLICACION . '/controllers/perfilController.php';
