<?php
# =====================================================================
# Conexion a la base de datos
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# SEGURIDAD - pendiente para produccion: estas credenciales tendrian
# que venir de variables de entorno y no estar escritas en el codigo,
# que ademas va al repositorio. Se dejan aca porque esa practica
# tampoco se dio en clase. Si el proyecto sale del entorno local, lo
# primero que hay que cambiar es esto.
#
# El usuario es sgdm_app, el de la seccion 12 de sql/schema.sql: solo
# tiene permisos de lectura y escritura sobre la base sgdm, nada de
# DDL. Nunca root: si esta conexion se ve comprometida, el dano queda
# acotado a los datos, sin poder tocar la estructura.
# =====================================================================

$configuracion_bd = array(
    'servidor' => 'localhost',
    'usuario'  => 'sgdm_app',
    'clave'    => 'CAMBIAR_CLAVE_APP',  # la misma que se puso en el GRANT
    'base'     => 'sgdm',
    'juego'    => 'utf8mb4'
);

# Abre la conexion y la devuelve. Si algo falla devuelve null y deja el
# motivo en $error_bd, para que el controlador lo muestre sin que la
# pagina se caiga con un error de PHP a la vista del usuario.
function conectarBD()
{
    global $configuracion_bd, $error_bd;

    # Por defecto mysqli lanza excepciones. Se apaga para poder
    # comprobar los errores con if, que es como se trabaja en el resto
    # del proyecto.
    mysqli_report(MYSQLI_REPORT_OFF);

    $conexion = @new mysqli(
        $configuracion_bd['servidor'],
        $configuracion_bd['usuario'],
        $configuracion_bd['clave'],
        $configuracion_bd['base']
    );

    if ($conexion->connect_errno !== 0) {
        $error_bd = 'No hay conexion con la base de datos.';
        return null;
    }

    # Sin esto, los acentos y la enie se guardan mal.
    $conexion->set_charset($configuracion_bd['juego']);

    return $conexion;
}
