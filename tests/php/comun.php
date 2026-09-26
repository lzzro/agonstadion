<?php
# =====================================================================
# Lo que comparten las pruebas en PHP - Stadion (Agon)
# ---------------------------------------------------------------------
# Se corren desde la consola, con php (ver tests/README.md). Las que
# leen la base se conectan con estas variables de entorno, nunca con una
# contrasena escrita aca:
#   STADION_DB_SOCKET   el socket de MariaDB (por defecto el del sistema)
#   STADION_DB_BASE     la base de PRUEBA (por defecto sgdm)
#   STADION_DB_USUARIO  la cuenta (por defecto root, que en la maquina de
#                       prueba entra por el socket sin contrasena)
#   STADION_DB_CLAVE    su contrasena, si tiene (por defecto ninguna)
# =====================================================================

$APLICACION = __DIR__ . '/../../apps';

$malas  = 0;
$buenas = 0;

function chk($condicion, $descripcion)
{
    global $malas, $buenas;
    if ($condicion) {
        $buenas++;
        echo '  ok    ' . $descripcion . "\n";
    } else {
        $malas++;
        echo '  FALLA ' . $descripcion . "\n";
    }
    return $condicion;
}

function fin()
{
    global $malas, $buenas;
    echo (($malas === 0) ? 'TODO BIEN' : $malas . ' FALLARON') . ': ' . $buenas . ' de ' . ($buenas + $malas)
       . " comprobaciones\n";
    exit(($malas === 0) ? 0 : 1);
}

function conexionDePrueba()
{
    $socket  = getenv('STADION_DB_SOCKET') ?: ini_get('mysqli.default_socket');
    $base    = getenv('STADION_DB_BASE') ?: 'sgdm';
    $usuario = getenv('STADION_DB_USUARIO') ?: 'root';
    $clave   = getenv('STADION_DB_CLAVE') ?: '';
    mysqli_report(MYSQLI_REPORT_OFF);
    $conexion = @new mysqli('localhost', $usuario, $clave, $base, 0, $socket);
    if ($conexion->connect_errno) {
        echo "No se puede abrir la base de prueba ($base): " . $conexion->connect_error . "\n";
        exit(2);
    }
    $conexion->set_charset('utf8mb4');
    return $conexion;
}

# Un valor de una sola consulta.
function valor($conexion, $sql)
{
    $fila = $conexion->query($sql)->fetch_row();
    return ($fila === null) ? null : $fila[0];
}
