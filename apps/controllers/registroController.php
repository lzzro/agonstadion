<?php
# =====================================================================
# Controlador: alta de usuario
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Recibe por POST el formulario de "crear una cuenta" de
# public/login.html. Sigue el camino de la estructura de clase:
# require_once de los modelos, validar con isset/empty, castear lo
# numerico, crear el objeto, y volver a incluir la vista con el
# resultado.
#
# La contrasena entra por asignarClave(), que la pasa por
# password_hash(). En claro no se guarda ni se muestra nunca.
# =====================================================================

require_once '../config/database.php';
require_once '../models/Usuario.php';
require_once '../models/UsuarioRepositorio.php';

$titulo  = 'Alta de cuenta';
$mensaje = '';
$errores = array();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errores[] = 'El alta llega desde el formulario de la pagina de acceso.';
} else {

    # --- lo que llega del formulario ---
    $nombre   = isset($_POST['nombre'])   ? trim($_POST['nombre'])   : '';
    $apellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : '';
    $correo   = isset($_POST['correo'])   ? trim($_POST['correo'])   : '';
    $clave    = isset($_POST['password']) ? $_POST['password']       : '';
    $alias    = isset($_POST['alias'])    ? trim($_POST['alias'])    : '';

    if (empty($nombre) || empty($apellido) || empty($correo) || empty($clave)) {
        $errores[] = 'Nombre, apellido, correo y contrasena son obligatorios.';
    }

    if (empty($errores)) {
        # El hash se asigna despues, con asignarClave().
        $usuario = new Usuario(null, $correo, null, $nombre, $apellido,
                               ($alias === '' ? null : $alias));

        $errores = $usuario->asignarClave($clave);

        if (empty($errores)) {
            $conexion = conectarBD();
            if ($conexion === null) {
                $errores[] = $error_bd;
            } else {
                $repositorio = new UsuarioRepositorio($conexion);
                $errores = $repositorio->insertar($usuario);

                if (empty($errores)) {
                    $mensaje = 'La cuenta queda abierta a nombre de '
                             . $usuario->getNombreCompleto()
                             . ', con el numero ' . $usuario->getIdUsuario() . '.';
                }
                $conexion->close();
            }
        }
    }
}

include('../index.php');
