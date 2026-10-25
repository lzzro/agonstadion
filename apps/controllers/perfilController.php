<?php
# =====================================================================
# Controlador: perfil
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Muestra los datos de quien tiene la sesion abierta, y guarda los
# cambios que se manden desde el formulario de la misma pagina.
#
# Es la primera pantalla que exige haber iniciado sesion. Quien llega
# sin sesion vigente va a parar a la pagina de acceso: no se le muestra
# un perfil vacio ni un aviso, porque no hay perfil que mostrar.
#
# De aca sale la primera aplicacion real de apps/config/sesion.php: si
# pasaron mas de 30 minutos sin actividad, sesionVigente() cierra la
# sesion y este controlador manda al acceso.
#
# Quien esta es el id guardado en la sesion, nunca un id que venga por
# formulario o por la direccion. Si viniera de afuera, cambiando un
# numero en la URL cualquiera editaria el perfil de otro.
#
# El correo no se edita: identifica la cuenta. La contrasena se cambia
# por su propio formulario (accion "clave"), que pide la actual. El
# formulario de datos solo manda los cuatro campos que si son del perfil.
#
# Recibe cuatro formularios, que se distinguen por el campo "accion":
#   datos      nombre, apellido, alias y presentacion (el de siempre;
#              si no llega "accion" tambien es este)
#   foto       la foto de perfil
#   portada    la imagen de portada
#   clave      el cambio de contrasena (tercera entrega): la actual, la
#              nueva y su repeticion
#   pedir_rol  el pedido del rol de organizador, que despues aprueba o
#              rechaza la administracion (ver adminController.php)
#   crear_equipo  un equipo nuevo, con esta cuenta de capitan (ver
#              EquipoRepositorio): con el, se pide lugar en una liga
#              desde la pagina de la liga
#   medicion   una medicion de rendimiento fisico (RF-29): la prueba,
#              sus datos crudos y la fecha (ver Medicion)
#   borrar_medicion  borra una medicion de esta cuenta (una de otra
#              cuenta no se borra: el repositorio filtra por la cuenta)
#   sumar_integrante, dorsal_integrante, baja_integrante   los
#              integrantes de un equipo que capitanea esta cuenta, con
#              su dorsal (RF-12; ver EquipoRepositorio): de cada uno se
#              leen solo el numero de equipo, el correo, la cuenta y el
#              dorsal, y el repositorio comprueba que el capitan sea la
#              cuenta de la sesion
# Todos traen el token de config/csrf.php. Sin el, o con uno que no es
# el de esta sesion, no se toca nada: se responde 403 con el aviso.
# Las dos imagenes pasan por ImagenSubida, que decide si se aceptan.
# Cada una va al mismo lugar: una carpeta que no ejecuta nada (ver el
# .htaccess de public/subidas/). Al reemplazar una imagen, la anterior
# se borra del disco: no quedan archivos de nadie dando vueltas.
#
# Ademas prepara lo que muestran las pestanas del perfil: los torneos
# de la persona y los equipos que capitanea, sacados de la base.
#
# ?aviso=organizador en la direccion: llega desde crear.php o el panel
# sin el rol de organizador. La tarjeta de los roles lo explica al lado
# del boton de pedirlo (y el titulo empieza con "Aviso ·").
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/ImagenSubida.php';
require_once __DIR__ . '/../models/PedidoRolRepositorio.php';
require_once __DIR__ . '/../models/Equipo.php';
require_once __DIR__ . '/../models/EquipoRepositorio.php';
require_once __DIR__ . '/../models/MedicionRepositorio.php';
require_once __DIR__ . '/../models/EnfrentamientoRepositorio.php';
require_once __DIR__ . '/../models/ParticipanteRepositorio.php';
require_once __DIR__ . '/../models/FixtureRepositorio.php';
require_once __DIR__ . '/../models/ResultadoRepositorio.php';
require_once __DIR__ . '/../fechas.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Desde donde alcanzar css, js e imagenes, y las otras dos direcciones
# de la cabecera. Si quien llama no las dejo preparadas, vale la
# instalacion local. Ver apps/index.php.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_salir))   { $ruta_salir   = 'salirController.php'; }
if (!isset($ruta_admin))   { $ruta_admin   = 'adminController.php'; }

# Donde se guardan las imagenes, en el disco. En la copia de publicacion
# la deja preparada el puente, porque ahi la carpeta publica es public_html.
if (!isset($carpeta_subidas)) { $carpeta_subidas = __DIR__ . '/../../public/subidas'; }

$titulo  = 'Perfil';
$mensaje = '';
$errores = array();
$errores_equipo = array();   # campo del equipo nuevo => mensaje
$errores_medicion = array(); # prueba => mensaje, al lado de su formulario
$valores_medicion = array(); # prueba => lo que se escribio, si hubo un error
$valores_equipo = array('nombre' => '', 'ciudad' => '');
$errores_clave = array();       # los del cambio de contrasena, al lado de su formulario
$errores_integrante = array();  # id de equipo => mensaje, al lado de su formulario
$valores_integrante = array();  # id de equipo => array('correo', 'dorsal'), si hubo un error

# --- 1. Sin sesion vigente no hay nada que mostrar -------------------
if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

# --- 2. Conexion y carga del usuario ---------------------------------
$conexion = conectarBD();
if ($conexion === null) {
    $errores[] = $error_bd;
    include __DIR__ . '/../index.php';
    exit;
}

$repositorio = new UsuarioRepositorio($conexion);
$auditorias  = new AuditoriaRepositorio($conexion);
$pedidos     = new PedidoRolRepositorio($conexion);

$usuario = $repositorio->buscarPorId($id_usuario);

# La sesion dice que hay alguien, pero la cuenta ya no esta o quedo
# dada de baja. Se cierra la sesion y se vuelve al acceso: seguir
# adelante seria dejar entrar a una cuenta que no existe.
if ($usuario === null || !$usuario->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

# --- 3. Lo que llegue por POST ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = (isset($_POST['accion']) && is_string($_POST['accion'])) ? $_POST['accion'] : 'datos';

    # Si el pedido entero supera el limite de PHP (post_max_size), PHP
    # lo descarta sin avisar: llegan $_POST y $_FILES vacios. Solo una
    # imagen puede pesar tanto, asi que el aviso es el del tamano.
    if (empty($_POST) && empty($_FILES)
        && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $errores[] = 'La imagen supera los 2 MB.';

    } elseif (!csrfValido()) {
        # Sin el token de esta sesion no se hace nada: ni datos, ni
        # imagenes, ni pedidos.
        $errores[] = rechazarCsrf();

    } elseif ($accion === 'clave') {

        # --- 3x. Cambio de contrasena (tercera entrega) ---------------
        # Pide la clave actual: una sesion abierta y olvidada en otra
        # computadora no alcanza para cambiarla. La nueva pasa por las
        # mismas reglas que en el alta (Usuario::asignarClave). Al
        # cambiarla, identificador de sesion y token nuevos, igual que
        # al iniciar sesion. Queda en la auditoria, sin la clave.
        $actual  = (isset($_POST['clave_actual']) && is_string($_POST['clave_actual'])) ? $_POST['clave_actual'] : '';
        $nueva   = (isset($_POST['clave_nueva']) && is_string($_POST['clave_nueva'])) ? $_POST['clave_nueva'] : '';
        $repetir = (isset($_POST['clave_repetir']) && is_string($_POST['clave_repetir'])) ? $_POST['clave_repetir'] : '';

        if (empty($actual) || empty($nueva) || empty($repetir)) {
            $errores_clave[] = 'Las tres contraseñas son obligatorias.';
        } elseif (!$usuario->verificarClave($actual)) {
            $errores_clave[] = 'La contraseña actual no coincide.';
            $auditorias->registrar(new Auditoria(
                null, $usuario, 'usuario', 'login_error',
                $usuario->getIdUsuario(), 'Cambio de contraseña con la actual equivocada', $ip));
        } elseif ($nueva !== $repetir) {
            $errores_clave[] = 'La contraseña nueva y su repetición no coinciden.';
        } elseif ($nueva === $actual) {
            $errores_clave[] = 'La contraseña nueva es igual a la actual.';
        } else {
            $errores_clave = $usuario->asignarClave($nueva);
            if (empty($errores_clave)) {
                $errores_clave = $repositorio->actualizarClave($usuario);
            }
            if (empty($errores_clave)) {
                session_regenerate_id(true);
                renovarCsrf();
                $mensaje = 'La contraseña queda cambiada.';
                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'modificacion',
                    $usuario->getIdUsuario(), 'Cambio de contraseña', $ip));
            }
        }
        # Los errores van tambien arriba, en la lista general, para el
        # "Aviso ·" del titulo.
        $errores = array_merge($errores, $errores_clave);

    } elseif ($accion === 'pedir_rol') {

        # --- 3a. Pedido del rol de organizador ------------------------
        # El rol pedido es siempre organizador: no se lee del formulario,
        # asi nadie pide "administrador" cambiando un campo oculto.
        # Quien ya es organizador no tiene nada que pedir. Que no haya
        # dos pedidos pendientes a la vez lo asegura la base (ver
        # PedidoRolRepositorio::crear); esta comprobacion solo da un
        # aviso mas claro.
        $repositorio->cargarRoles($usuario);
        $anterior = $pedidos->ultimoDe($id_usuario, 'organizador');

        if ($usuario->tieneRol('organizador')) {
            $errores[] = 'La cuenta ya tiene el rol de organizador.';
        } elseif ($anterior !== null && $anterior->estaPendiente()) {
            $errores[] = 'Ya hay un pedido en revisión.';
        } else {
            $errores = $pedidos->crear($usuario, 'organizador');
            if (empty($errores)) {
                $nuevo = $pedidos->ultimoDe($id_usuario, 'organizador');
                $mensaje = 'El pedido del rol de organizador queda en revisión.';
                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'pedido_rol', 'pedido_rol',
                    ($nuevo === null) ? null : $nuevo->getIdPedidoRol(),
                    'Rol organizador', $ip));
            }
        }

        # Los roles se vuelven a cargar abajo, completos.
        $usuario = $repositorio->buscarPorId($id_usuario);

    } elseif ($accion === 'crear_equipo') {

        # --- 3d. Un equipo nuevo, con esta cuenta de capitan ----------
        # El capitan es siempre la cuenta de la sesion.
        $valores_equipo['nombre'] = (isset($_POST['nombre_equipo']) && is_string($_POST['nombre_equipo']))
                                    ? trim($_POST['nombre_equipo']) : '';
        $valores_equipo['ciudad'] = (isset($_POST['ciudad_equipo']) && is_string($_POST['ciudad_equipo']))
                                    ? trim($_POST['ciudad_equipo']) : '';

        $equipo = new Equipo(null, $valores_equipo['nombre'],
                             ($valores_equipo['ciudad'] === '') ? null : $valores_equipo['ciudad'], $usuario);
        $resultado = (new EquipoRepositorio($conexion))->crearConCapitan($equipo);

        if (empty($resultado)) {
            $mensaje = 'El equipo ' . $equipo->getNombre() . ' queda armado, con esta cuenta de capitán.';
            $valores_equipo = array('nombre' => '', 'ciudad' => '');
            $auditorias->registrar(new Auditoria(
                null, $usuario, 'equipo', 'alta', $equipo->getIdEquipo(),
                mb_substr('Equipo ' . $equipo->getNombre(), 0, 255), $ip));
        } else {
            foreach ($resultado as $error) {
                if (strpos($error, 'ciudad') !== false) {
                    $errores_equipo['ciudad'] = $error;
                } else {
                    $errores_equipo['nombre'] = $error;
                }
            }
            $errores = $resultado;
        }

    } elseif ($accion === 'sumar_integrante' || $accion === 'dorsal_integrante' || $accion === 'baja_integrante') {

        # --- 3g. Los integrantes de un equipo (RF-12) -------------------
        # Solo numeros y el correo. El dorsal vacio es "sin dorsal".
        $id_equipo  = (isset($_POST['id_equipo']) && is_string($_POST['id_equipo'])) ? (int)$_POST['id_equipo'] : 0;
        $id_cuenta  = (isset($_POST['id_usuario']) && is_string($_POST['id_usuario'])) ? (int)$_POST['id_usuario'] : 0;
        $correo     = (isset($_POST['correo']) && is_string($_POST['correo'])) ? trim($_POST['correo']) : '';
        $dorsal_txt = (isset($_POST['dorsal']) && is_string($_POST['dorsal'])) ? trim($_POST['dorsal']) : '';
        $dorsal     = ($dorsal_txt === '') ? null : (ctype_digit($dorsal_txt) ? (int)$dorsal_txt : -1);
        $equipos_repo = new EquipoRepositorio($conexion);

        if ($accion === 'sumar_integrante') {
            $resultado = ($correo === '') ? array('errores' => array('Falta el correo de la cuenta.'), 'nombre' => null)
                       : $equipos_repo->sumarIntegrante($id_equipo, $usuario, $correo, $dorsal);
            if (empty($resultado['errores'])) {
                $mensaje = $resultado['nombre'] . ' queda en el equipo'
                         . (($dorsal === null) ? ', sin dorsal.' : ', con el dorsal ' . $dorsal . '.');
                $auditorias->registrar(new Auditoria(null, $usuario, 'integrante_equipo', 'alta', $id_equipo,
                    mb_substr($resultado['nombre'] . ((($dorsal === null) ? '' : ' · dorsal ' . $dorsal)), 0, 255), $ip));
            } else {
                $errores = $resultado['errores'];
                $errores_integrante[$id_equipo] = implode(' ', $resultado['errores']);
                $valores_integrante[$id_equipo] = array('correo' => $correo, 'dorsal' => $dorsal_txt);
            }
        } else {
            # El nombre del integrante, para el aviso y la auditoria.
            $otra = ($id_cuenta > 0) ? $repositorio->buscarPorId($id_cuenta) : null;
            $quien = ($otra === null) ? 'El integrante' : $otra->getNombreCompletoVisible();
            if ($accion === 'dorsal_integrante') {
                $errores = $equipos_repo->cambiarDorsal($id_equipo, $usuario, $id_cuenta, $dorsal);
                if (empty($errores)) {
                    $mensaje = $quien . (($dorsal === null) ? ' queda sin dorsal.' : ' queda con el dorsal ' . $dorsal . '.');
                    $auditorias->registrar(new Auditoria(null, $usuario, 'integrante_equipo', 'modificacion', $id_equipo,
                        mb_substr('Cambio de dorsal · ' . $quien . (($dorsal === null) ? ' · sin dorsal' : ' · ' . $dorsal), 0, 255), $ip));
                }
            } else {
                $errores = $equipos_repo->bajaIntegrante($id_equipo, $usuario, $id_cuenta);
                if (empty($errores)) {
                    $mensaje = $quien . ' queda de baja en el equipo.';
                    $auditorias->registrar(new Auditoria(null, $usuario, 'integrante_equipo', 'baja', $id_equipo,
                        mb_substr($quien, 0, 255), $ip));
                }
            }
        }

    } elseif ($accion === 'medicion') {

        # --- 3e. Una medicion de rendimiento fisico --------------------
        # Siempre de la cuenta de la sesion. Los numeros llegan con punto
        # o con coma decimal; se leen solo si son numeros.
        $prueba = (isset($_POST['prueba']) && is_string($_POST['prueba'])) ? $_POST['prueba'] : '';
        $leer = function ($campo) {
            if (!isset($_POST[$campo]) || !is_string($_POST[$campo])) { return null; }
            $texto = str_replace(',', '.', trim($_POST[$campo]));
            return is_numeric($texto) ? (float)$texto : null;
        };
        $fecha = (isset($_POST['fecha']) && is_string($_POST['fecha'])) ? trim($_POST['fecha']) : '';
        $medicion = new Medicion(null, $usuario, $prueba,
                                 ($prueba === 'reaccion') ? $leer('distancia') : null,
                                 ($prueba === 'reaccion') ? null : $leer('tiempo'),
                                 ($prueba === 'reaccion') ? null : $leer('masa'), $fecha);
        $resultado = (new MedicionRepositorio($conexion))->insertar($medicion, date('Y-m-d'));
        if (empty($resultado)) {
            $datos = Medicion::pruebas();
            $mensaje = $datos[$prueba]['indicador'] . ': ' . Medicion::formatear($prueba, $medicion->indicador())
                     . ' ' . $datos[$prueba]['corta'] . '. La medición queda guardada.';
            $auditorias->registrar(new Auditoria(null, $usuario, 'medicion', 'medicion',
                $medicion->getIdMedicion(), mb_substr($datos[$prueba]['indicador'] . ' · ' . $fecha, 0, 255), $ip));
        } else {
            $errores = $resultado;
            if (array_key_exists($prueba, Medicion::pruebas())) {
                $errores_medicion[$prueba] = implode(' ', $resultado);
                $valores_medicion[$prueba] = array(
                    'fecha'     => $fecha,
                    'distancia' => (isset($_POST['distancia']) && is_string($_POST['distancia'])) ? $_POST['distancia'] : '',
                    'tiempo'    => (isset($_POST['tiempo']) && is_string($_POST['tiempo'])) ? $_POST['tiempo'] : '',
                    'masa'      => (isset($_POST['masa']) && is_string($_POST['masa'])) ? $_POST['masa'] : '');
            }
        }

    } elseif ($accion === 'borrar_medicion') {

        # --- 3f. Borrar una medicion propia ---------------------------
        $id_medicion = (isset($_POST['id_medicion']) && is_string($_POST['id_medicion'])) ? (int)$_POST['id_medicion'] : 0;
        if ((new MedicionRepositorio($conexion))->borrar($id_medicion, $id_usuario)) {
            $mensaje = 'La medición queda borrada.';
            $auditorias->registrar(new Auditoria(null, $usuario, 'medicion', 'baja', $id_medicion,
                'Baja de medición', $ip));
        } else {
            $errores[] = 'Esa medición no es de esta cuenta.';
        }

    } elseif ($accion === 'foto' || $accion === 'portada') {

        # --- 3b. Foto de perfil o portada ----------------------------
        # Otra vez: la imagen es SIEMPRE de quien tiene la sesion. Un
        # id_usuario que llegue en el formulario ni se lee.
        $imagen  = new ImagenSubida(isset($_FILES['imagen']) ? $_FILES['imagen'] : null);
        $errores = $imagen->validar();

        if (empty($errores)) {
            $anterior = ($accion === 'foto') ? $usuario->getFotoPerfil() : $usuario->getFotoPortada();
            $nuevo    = $imagen->guardarEn($carpeta_subidas);

            if ($nuevo === null) {
                $errores[] = 'La imagen no se puede guardar por ahora.';

            } elseif (!$repositorio->actualizarImagen($usuario, $accion, $nuevo)) {
                # La base no la tomo: el archivo recien guardado no le
                # sirve a nadie y se borra.
                @unlink($carpeta_subidas . '/' . $nuevo);
                $errores[] = 'La imagen no se puede guardar por ahora.';

            } else {
                # La anterior se borra del disco. Solo si su nombre tiene
                # la forma de los que genera el sistema: asi un valor
                # raro nunca termina borrando otra cosa.
                if (ImagenSubida::nombreValido($anterior)
                    && is_file($carpeta_subidas . '/' . $anterior)) {
                    @unlink($carpeta_subidas . '/' . $anterior);
                }

                $que = ($accion === 'foto') ? 'foto de perfil' : 'portada';
                $mensaje = ($accion === 'foto') ? 'La foto de perfil queda cargada.'
                                                : 'La portada queda cargada.';
                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'modificacion',
                    $usuario->getIdUsuario(), 'Carga de ' . $que, $ip));

                $usuario = $repositorio->buscarPorId($id_usuario);
            }
        }

    } else {

        # --- 3c. Datos del perfil -------------------------------------
        # Solo textos: un campo mandado como arreglo (nombre[]=x) queda vacio
        # (sin esto, trim() corta con un error y la pagina responde 500).
        $nombre       = (isset($_POST['nombre']) && is_string($_POST['nombre'])) ? trim($_POST['nombre']) : '';
        $apellido     = (isset($_POST['apellido']) && is_string($_POST['apellido'])) ? trim($_POST['apellido']) : '';
        $alias        = (isset($_POST['alias']) && is_string($_POST['alias'])) ? trim($_POST['alias']) : '';
        $presentacion = (isset($_POST['presentacion']) && is_string($_POST['presentacion'])) ? trim($_POST['presentacion']) : '';

        # Los campos vacios que la base admite en NULL viajan como NULL y
        # no como cadena vacia, para que la columna quede igual que cuando
        # nunca se completo.
        if ($alias === '')        { $alias = null; }
        if ($presentacion === '') { $presentacion = null; }

        if (empty($nombre) || empty($apellido)) {
            $errores[] = 'El nombre y el apellido son obligatorios.';
        }

        if (empty($errores)) {
            # Se arma un Usuario nuevo con los campos editados y el resto
            # tal como esta en la base. Asi el correo, la clave y la fecha
            # de alta llegan intactos a la comprobacion de actualizarPerfil,
            # sin pasar por el formulario ni una sola vez.
            $editado = new Usuario(
                $usuario->getIdUsuario(),
                $usuario->getCorreo(),
                $usuario->getHashPassword(),
                $nombre,
                $apellido,
                $alias,
                $presentacion,
                $usuario->getActivo(),
                $usuario->getFechaAlta(),
                $usuario->getFotoPerfil(),
                $usuario->getFotoPortada()
            );

            $errores = $repositorio->actualizarPerfil($editado);

            if (empty($errores)) {
                $usuario = $editado;
                $mensaje = 'El perfil queda guardado.';

                # El nombre que se saluda en la sesion se actualiza tambien,
                # o seguiria apareciendo el anterior hasta el proximo ingreso.
                $_SESSION['nombre'] = $usuario->getNombre();

                $auditorias->registrar(new Auditoria(
                    null, $usuario, 'usuario', 'modificacion',
                    $usuario->getIdUsuario(), null, $ip));
            }
        }
    }
}

# --- 4. Roles, pedido, torneos y vista -------------------------------
$repositorio->cargarRoles($usuario);

# El ultimo pedido del rol de organizador, para que la vista sepa que
# mostrar: nada (ya es organizador), "en revision", o el boton de
# pedirlo (nunca lo pidio, o se lo rechazaron y puede volver a pedir).
$pedido_organizador = $usuario->tieneRol('organizador') ? null
                    : $pedidos->ultimoDe($id_usuario, 'organizador');

# El aviso de crear.php y del panel, solo si de verdad falta el rol.
$aviso_organizador = isset($_GET['aviso']) && is_string($_GET['aviso']) && $_GET['aviso'] === 'organizador'
                     && !$usuario->tieneRol('organizador');

# Los equipos que capitanea, con sus integrantes (RF-12), y los que
# integra sin ser capitan, para la pestana Mis torneos.
$equipos_repo = new EquipoRepositorio($conexion);
$equipos = $equipos_repo->listarDeCapitan($usuario);
$integrantes_de = array();   # id de equipo => integrantes (IntegranteEquipo), o null
if (is_array($equipos)) {
    foreach ($equipos as $equipo) {
        $integrantes_de[(int)$equipo->getIdEquipo()] = $equipos_repo->integrantesDe($equipo);
    }
}
$equipos_ajenos = $equipos_repo->listarDeIntegrante($usuario);

# Los torneos de la persona, de la base. null si la consulta falla,
# para que la vista no confunda "no se pudo leer" con "ninguno".
$torneos = new TorneoRepositorio($conexion);
$inscripciones = $torneos->buscarPorUsuario($id_usuario);

# Mis resultados (RF-26): los partidos jugados de la persona (los suyos
# y los de sus equipos), y lo que cuentan las estadisticas de arriba:
# las finales jugadas (la ultima ronda de una eliminacion directa) y
# los kotinos (cada torneo terminado que gana). null si no se pueden
# leer.
$mis_partidos = null;
$finales = null;
$kotinos = null;
if (is_array($inscripciones)) {
    $ids_participante = array();
    foreach ($inscripciones as $inscripcion) {
        $ids_participante[] = (int)$inscripcion['participante']->getIdParticipante();
    }
    $mis_partidos = (new EnfrentamientoRepositorio($conexion))->jugadosDe($ids_participante);
    if (is_array($mis_partidos)) {
        $finales = 0;
        foreach ($mis_partidos as $partido) {
            if ($partido['torneo']->esEliminacion() && $partido['ronda']->getNombre() === 'Final') {
                $finales++;
            }
        }
    }
    $ganados = array();     # id de torneo => true
    $participantes_repo = new ParticipanteRepositorio($conexion);
    $fixtures = new FixtureRepositorio($conexion);
    $se_pudo = true;
    foreach ($inscripciones as $inscripcion) {
        $torneo_jugado = $inscripcion['torneo'];
        if ($torneo_jugado->getEstado() !== 'finalizado') {
            continue;
        }
        $id_torneo = (int)$torneo_jugado->getIdTorneo();
        $todos  = $participantes_repo->listarDeTorneo($id_torneo, true);
        $rondas = ($todos === null) ? null : $fixtures->fechasDe($id_torneo, $todos);
        if ($rondas === null) {
            $se_pudo = false;
            break;
        }
        $campeon = ResultadoRepositorio::campeon($torneo_jugado, $todos, $rondas);
        if ($campeon !== null
            && (int)$campeon->getIdParticipante() === (int)$inscripcion['participante']->getIdParticipante()) {
            $ganados[$id_torneo] = true;
        }
    }
    $kotinos = $se_pudo ? count($ganados) : null;
}

# El rendimiento fisico (RF-29): las mediciones de la cuenta, y la
# comparacion de cada prueba con las demas cuentas. null si no se
# pueden leer.
$mediciones_repo = new MedicionRepositorio($conexion);
$mediciones = $mediciones_repo->listarDeUsuario($usuario);
$comparaciones = array();
foreach (array_keys(Medicion::pruebas()) as $prueba) {
    $comparaciones[$prueba] = $mediciones_repo->comparacion($prueba);
}
$hoy = date('Y-m-d');

$conexion->close();

include __DIR__ . '/../perfil.php';
