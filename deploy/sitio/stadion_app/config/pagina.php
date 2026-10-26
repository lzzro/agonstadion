<?php
# Arranque de las paginas publicas

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/rutas_paginas.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/recursos.php';
require_once __DIR__ . '/../cabecera.php';
require_once __DIR__ . '/../pie.php';

# La pagina cambia segun haya o no sesion: que el navegador no guarde una copia.
header('Cache-Control: no-store');

$carpeta_app = dirname(__DIR__);

# La cuenta de la sesion, leida de la base, para el circulo de la cabecera. null si no hay sesion.
$persona_sesion = null;

if (isset($_COOKIE[session_name()]) && sesionVigente()) {
    $conexion = conectarBD();
    if ($conexion !== null) {
        $cuentas = new UsuarioRepositorio($conexion);
        $persona_sesion = $cuentas->buscarPorId((int)$_SESSION['id_usuario']);
        $conexion->close();

        if ($persona_sesion === null || !$persona_sesion->estaActivo()) {
            $persona_sesion = null;
            cerrarSesion();
        }
    }
}
