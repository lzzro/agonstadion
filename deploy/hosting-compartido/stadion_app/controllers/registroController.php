<?php
# =====================================================================
# Controlador: alta de usuario
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Recibe por POST el formulario de "crear una cuenta" de
# public/registro.php. Sigue el camino de la estructura de clase:
# require_once de los modelos, validar con isset/empty, castear lo
# numerico, crear el objeto, y volver a incluir la vista con el
# resultado.
#
# La contrasena entra por asignarClave(), que la pasa por
# password_hash(). En claro no se guarda ni se muestra nunca.
#
# La conformidad con los terminos se comprueba aca tambien, no solo con
# el required del formulario: el navegador se puede saltear.
#
# El alta queda registrada en la tabla auditoria, igual que los inicios
# de sesion. No lleva detalle: con el id de la cuenta recien creada ya
# se sabe todo lo que hace falta, y el correo esta en la propia fila de
# usuario.
#
# El formulario trae el token de config/csrf.php: sin el, o con uno que
# no es el de esta sesion, no se da de alta nada. La sesion se abre
# solo para compararlo; el alta no inicia sesion.
# =====================================================================

session_start();

require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

$titulo  = 'Alta de cuenta';
$mensaje = '';
$errores = array();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errores[] = 'El alta llega desde el formulario de la pagina de acceso.';
} elseif (!csrfValido()) {
    $errores[] = rechazarCsrf();
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

    # Una casilla sin marcar no se envia, asi que alcanza con isset. El
    # required del formulario no sirve de garantia: el POST puede llegar
    # de cualquier lado, no solo del formulario.
    if (!isset($_POST['terminos'])) {
        $errores[] = 'Falta la conformidad con los terminos.';
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
                    $auditorias = new AuditoriaRepositorio($conexion);
                    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
                    $auditorias->registrar(new Auditoria(
                        null, $usuario, 'usuario', 'alta',
                        $usuario->getIdUsuario(), null, $ip));
                    # Si la auditoria fallara no se le avisa a quien se
                    # dio de alta: la cuenta ya quedo abierta y el aviso
                    # lo confundiria.

                    $mensaje = 'La cuenta queda abierta a nombre de '
                             . $usuario->getNombreCompletoVisible()
                             . ', con el numero ' . $usuario->getIdUsuario() . '.';
                }
                $conexion->close();
            }
        }
    }
}

include __DIR__ . '/../index.php';
