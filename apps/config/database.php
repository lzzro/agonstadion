<?php
# Conexion a la base de datos

# Valores publicables.
$configuracion_bd = array(
    'servidor' => 'localhost',
    'usuario'  => 'sgdm_app',
    'clave'    => '',
    'base'     => 'sgdm',
    'juego'    => 'utf8mb4'
);

# Si hay configuracion local, pisa lo de arriba.
$archivo_local = __DIR__ . '/database.local.php';
if (file_exists($archivo_local)) {
    $configuracion_local = require $archivo_local;
    if (is_array($configuracion_local)) {
        $configuracion_bd = array_merge($configuracion_bd, $configuracion_local);
    }
}

# Abre la conexion y la devuelve.
function conectarBD()
{
    global $configuracion_bd, $error_bd;

    # Por defecto mysqli lanza excepciones.
    foreach (array('usuario', 'clave', 'base') as $dato) {
        if (!isset($configuracion_bd[$dato]) || $configuracion_bd[$dato] === '') {
            $error_bd = 'Falta la configuración local de la base de datos.';
            return null;
        }
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $conexion = @new mysqli(
        $configuracion_bd['servidor'],
        $configuracion_bd['usuario'],
        $configuracion_bd['clave'],
        $configuracion_bd['base']
    );

    if ($conexion->connect_errno !== 0) {
        $error_bd = 'No hay conexión con la base de datos.';
        return null;
    }

    # Sin esto, los acentos y la enie se guardan mal.
    $conexion->set_charset($configuracion_bd['juego']);

    $conexion->query("SET time_zone = '-03:00'");

    return $conexion;
}
