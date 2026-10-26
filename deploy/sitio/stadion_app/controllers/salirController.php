<?php
# Controlador: cierre de sesion

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Desde donde alcanzar las paginas.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sesionVigente() && !csrfValido()) {
    $titulo  = 'Cierre de sesión';
    $errores = array(rechazarCsrf());
    include __DIR__ . '/../index.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sesionVigente()) {

    $id_usuario = (int)$_SESSION['id_usuario'];

    # La salida queda registrada con el id de quien sale.
    $conexion = conectarBD();
    if ($conexion !== null) {
        $auditorias = new AuditoriaRepositorio($conexion);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $quien = new Usuario($id_usuario, null, null, null, null);
        $auditorias->registrar(new Auditoria(
            null, $quien, 'usuario', 'logout', $id_usuario, null, $ip));
        $conexion->close();
    }

    cerrarSesion();
}

header('Location: ' . $ruta_publica . '/index.php');
exit;
