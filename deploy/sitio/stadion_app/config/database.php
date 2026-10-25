<?php
# =====================================================================
# Conexion a la base de datos
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# LA CONTRASENA NO ESTA EN ESTE ARCHIVO. Vive en database.local.php,
# que el .gitignore excluye, asi que nunca llega al repositorio. Este
# archivo solo trae los valores que si se pueden publicar y, si existe
# el archivo local, lo carga encima.
#
# Para poner en marcha una copia nueva:
#   1. copiar database.local.php.ejemplo como database.local.php
#   2. escribir ahi la contrasena real de sgdm_app
#   3. no agregarlo al repositorio: el .gitignore ya se encarga
#
# La contrasena real se le da al usuario de base de datos con un
# ALTER USER, que se ejecuta a mano y tampoco queda en sql/schema.sql.
#
# El usuario es sgdm_app, el de la seccion 12 de sql/schema.sql: solo
# tiene permisos de lectura y escritura sobre la base sgdm, nada de
# DDL. Nunca root: si esta conexion se ve comprometida, el dano queda
# acotado a los datos, sin poder tocar la estructura.
# =====================================================================

# Valores publicables. La clave queda vacia a proposito.
$configuracion_bd = array(
    'servidor' => 'localhost',
    'usuario'  => 'sgdm_app',
    'clave'    => '',
    'base'     => 'sgdm',
    'juego'    => 'utf8mb4'
);

# Si hay configuracion local, pisa lo de arriba. El archivo devuelve un
# arreglo con solo las claves que quiera cambiar.
$archivo_local = __DIR__ . '/database.local.php';
if (file_exists($archivo_local)) {
    $configuracion_local = require $archivo_local;
    if (is_array($configuracion_local)) {
        $configuracion_bd = array_merge($configuracion_bd, $configuracion_local);
    }
}

# Abre la conexion y la devuelve. Si algo falla devuelve null y deja el
# motivo en $error_bd, para que el controlador lo muestre sin que la
# pagina se caiga con un error de PHP a la vista del usuario.
function conectarBD()
{
    global $configuracion_bd, $error_bd;

    # Por defecto mysqli lanza excepciones. Se apaga para poder
    # comprobar los errores con if, que es como se trabaja en el resto
    # del proyecto.
    # Sin configuracion local no hay clave, y conviene decirlo con
    # claridad en vez de dejar que falle como si el servidor no
    # estuviera.
    # Segun la instalacion, el nombre de la base y el del usuario
    # tambien pueden cambiar, asi que se comprueban los tres y no solo
    # la clave: media configuracion cargada falla igual, pero con un
    # mensaje que no ayuda.
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

    # La hora de la base, la de Montevideo (UTC-3, sin horario de verano
    # desde 2015), igual que la de las paginas (apps/fechas.php). Sin
    # esto, NOW() y los DEFAULT CURRENT_TIMESTAMP guardan la hora del
    # servidor, que puede ser otra (UTC): un pedido hecho a las 17:14
    # figuraba a las 20:14. Se da como diferencia (-03:00) y no por
    # nombre, porque el nombre necesita las tablas de zonas horarias de
    # MariaDB, que no siempre estan cargadas.
    # Lo guardado antes de este cambio queda con la hora del servidor (no
    # se convierte): por eso las resoluciones de pedidos usan
    # GREATEST(NOW(), fecha_pedido). La 005 y primer_administrador.sql,
    # que se corren a mano, ponen la misma hora al empezar.
    $conexion->query("SET time_zone = '-03:00'");

    return $conexion;
}
