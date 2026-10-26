<?php
# Puente: administracion. Llama al controlador de stadion_app/, fuera de lo publicado.
# Generado por scripts/armar-deploy.sh.

$APLICACION = __DIR__ . '/../../stadion_app';

$ruta_publica     = '..';
$ruta_perfil      = 'perfil.php';
$ruta_salir       = 'salir.php';
$ruta_admin       = 'admin.php';
$ruta_crear       = 'crear.php';
$ruta_panel       = 'panel.php';
$ruta_inscripcion = 'inscripcion.php';
$ruta_partidos    = 'partidos.php';
$ruta_reportes    = 'reportes.php';

$carpeta_subidas = __DIR__ . '/../subidas';

if (!is_dir($APLICACION)) {
    http_response_code(500);
    echo 'El sitio no encuentra su aplicacion.';
    exit;
}

require_once $APLICACION . '/controllers/adminController.php';
