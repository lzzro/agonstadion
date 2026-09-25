<?php
# =====================================================================
# Controlador: cierre de sesion
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Lo llama el boton "Cerrar sesion" del perfil, que es un formulario
# con POST. Cierra la sesion con cerrarSesion() de
# config/sesion.php, deja la salida en la auditoria y vuelve al inicio.
#
# Solo cierra por POST. Si llega un GET (alguien que escribio la
# direccion a mano, o una pagina ajena que intenta sacar a la gente de
# su sesion con un enlace), no toca nada y manda al inicio.
#
# El POST ademas trae el token de config/csrf.php. Sin el, o con uno
# que no es el de esta sesion, la sesion sigue abierta: se responde 403
# con el aviso. Asi una pagina ajena tampoco puede cerrarla con un
# formulario oculto.
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Desde donde alcanzar las paginas. Ver apps/index.php.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sesionVigente() && !csrfValido()) {
    $titulo  = 'Cierre de sesion';
    $errores = array(rechazarCsrf());
    include __DIR__ . '/../index.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sesionVigente()) {

    $id_usuario = (int)$_SESSION['id_usuario'];

    # La salida queda registrada con el id de quien sale. Si la base no
    # responde, la sesion se cierra igual: quedarse adentro por una
    # falla del registro seria peor.
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
