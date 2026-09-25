<?php
# =====================================================================
# Arranque de las paginas publicas
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Toda pagina de public/ con la cabecera compartida empieza incluyendo
# este archivo. Hace dos cosas antes de que salga una sola linea de
# HTML:
#
#   1. Averigua si hay una sesion vigente, con sesionVigente() de
#      config/sesion.php. Asi la cabecera muestra "Iniciar sesion" o el
#      nombre de quien entro, y una sesion vencida por inactividad se
#      nota en la primera pagina que se abra despues de la media hora.
#   2. Si hay sesion, lee la cuenta de la base: la cabecera muestra su
#      foto, o sus iniciales, en un circulo que lleva al perfil.
#   3. Deja preparada la direccion del perfil, que no es la misma en la
#      maquina local y en el hosting (ver config/rutas_paginas.php).
#
# Solo se abre la sesion si el navegador ya trae la cookie. Quien nunca
# inicio sesion no recibe ninguna: no hay por que crearle una sesion
# vacia a cada visitante.
#
# Por que las paginas pasaron de .html a .php: la cabecera depende de la
# sesion, y la sesion solo la conoce el servidor. Resolverlo aca, antes
# de mandar la pagina, evita el JavaScript que haria falta para
# preguntarlo despues y el parpadeo de una cabecera que cambia cuando
# la pagina ya esta a la vista.
# =====================================================================

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/rutas_paginas.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../cabecera.php';

# La pagina cambia segun haya o no sesion: que el navegador no guarde
# una copia. Sin esto, el boton de volver despues de cerrar la sesion
# podria mostrar la cabecera con el nombre de quien ya salio.
header('Cache-Control: no-store');

# La cuenta de la sesion, leida de la base, para el circulo de la
# cabecera. null si no hay sesion.
$persona_sesion = null;

if (isset($_COOKIE[session_name()]) && sesionVigente()) {
    $conexion = conectarBD();
    if ($conexion !== null) {
        $cuentas = new UsuarioRepositorio($conexion);
        $persona_sesion = $cuentas->buscarPorId((int)$_SESSION['id_usuario']);
        $conexion->close();

        # La sesion dice que hay alguien, pero la cuenta ya no esta o
        # quedo dada de baja: se cierra, igual que hace el perfil.
        if ($persona_sesion === null || !$persona_sesion->estaActivo()) {
            $persona_sesion = null;
            cerrarSesion();
        }
    }
    # Si la base no responde, la sesion sigue: la cabecera muestra la
    # inicial del nombre que guarda la sesion (ver accionesCabecera).
}
