<?php
# =====================================================================
# Controlador: crear una liga
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Fase 2 del motor de torneos. Muestra el formulario de una liga nueva
# (GET) y la guarda (POST). Reemplaza a la maqueta de crear.php, cuyo
# "Continuar" no guardaba nada.
#
# Es solo para cuentas con el rol organizador, y eso se comprueba ACA,
# en cada pedido, con la cuenta de la sesion leida de la base:
#   sin sesion vigente   a la pagina de acceso, como el perfil
#   sin el rol, al abrir el formulario: al perfil, a la tarjeta de los
#                        roles, donde se pide el de organizador, con un
#                        aviso que lo explica
#   sin el rol, con un POST armado a mano: 403, sin guardar nada
#
# El organizador de la liga es siempre la cuenta de la sesion, nunca un
# id que venga del formulario. La liga nace con la inscripcion abierta.
#
# Todo se valida aca, en el servidor, campo por campo, aunque el
# formulario ya traiga sus minimos y maximos: el navegador se puede
# saltear. Cada error va al lado de su campo, y el titulo de la pagina
# empieza con "Aviso ·". Despues validan el modelo (Torneo y
# ConfiguracionTorneo) y la base, con sus CHECK.
#
# Con la liga creada, queda la fila en la auditoria y se va al panel
# (redireccion despues del POST: recargar la pagina no crea otra liga).
#
# El formulario trae el token de config/csrf.php.
# =====================================================================

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/UsuarioRepositorio.php';
require_once __DIR__ . '/../models/Torneo.php';
require_once __DIR__ . '/../models/ConfiguracionTorneo.php';
require_once __DIR__ . '/../models/TorneoRepositorio.php';
require_once __DIR__ . '/../models/CatalogoRepositorio.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AuditoriaRepositorio.php';

# Direcciones. Si quien llama no las dejo preparadas, vale la
# instalacion local. Ver apps/index.php.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_crear))   { $ruta_crear   = 'crearController.php'; }
if (!isset($ruta_panel))   { $ruta_panel   = 'panelController.php'; }

$titulo  = 'Nueva liga';
$mensaje = '';
$errores = array();
$errores_campo = array();   # campo => mensaje, para mostrarlo al lado

# --- 1. Sin sesion vigente, al acceso --------------------------------
if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

# --- 2. Conexion, cuenta y rol ---------------------------------------
$conexion = conectarBD();
if ($conexion === null) {
    $errores[] = $error_bd;
    include __DIR__ . '/../index.php';
    exit;
}

$cuentas = new UsuarioRepositorio($conexion);
$organizador = $cuentas->buscarPorId($id_usuario);

if ($organizador === null || !$organizador->estaActivo()) {
    $conexion->close();
    cerrarSesion();
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}
$cuentas->cargarRoles($organizador);

if (!$organizador->tieneRol('organizador')) {
    $conexion->close();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
        $errores[] = 'Crear una liga pide el rol de organizador.';
        $persona_cabecera = $organizador;
        include __DIR__ . '/../index.php';
        exit;
    }
    header('Location: ' . $ruta_perfil . '?aviso=organizador#roles');
    exit;
}

$catalogos   = new CatalogoRepositorio($conexion);
$disciplinas = $catalogos->disciplinas();
if ($disciplinas === null) {
    $disciplinas = array();
}

# Lo que muestra el formulario: lo que llego, o los valores de una liga
# nueva (los mismos DEFAULT de configuracion_torneo).
$valores = array(
    'nombre'     => '',
    'disciplina' => '',
    'cupo'       => '16',
    'vueltas'    => 'una',
    'victoria'   => '3',
    'empate'     => '1',
    'derrota'    => '0',
    'desempate'  => 'diferencia',
    'inicio'     => ''
);

# --- 3. Guardar ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrfValido()) {
        $errores[] = rechazarCsrf();
    } else {
        foreach ($valores as $campo => $defecto) {
            $valores[$campo] = isset($_POST[$campo]) ? trim((string)$_POST[$campo]) : '';
        }

        # Cada campo, con su mensaje. Los numeros se castean con (int)
        # recien despues de ver que son numeros enteros.
        if ($valores['nombre'] === '') {
            $errores_campo['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($valores['nombre']) < 4 || mb_strlen($valores['nombre']) > 80) {
            $errores_campo['nombre'] = 'El nombre lleva entre 4 y 80 caracteres.';
        }

        $id_disciplina = (int)$valores['disciplina'];
        if (!isset($disciplinas[$id_disciplina])) {
            $errores_campo['disciplina'] = 'Falta elegir el juego o deporte.';
        }

        if (!ctype_digit($valores['cupo']) || (int)$valores['cupo'] < 4 || (int)$valores['cupo'] > 32) {
            $errores_campo['cupo'] = 'El cupo va de 4 a 32 equipos.';
        }

        if ($valores['vueltas'] !== 'una' && $valores['vueltas'] !== 'dos') {
            $errores_campo['vueltas'] = 'Falta elegir una vuelta o ida y vuelta.';
        }

        if (!ctype_digit($valores['victoria']) || (int)$valores['victoria'] < 1 || (int)$valores['victoria'] > 10) {
            $errores_campo['victoria'] = 'La victoria vale de 1 a 10 puntos.';
        }
        if (!ctype_digit($valores['empate']) || (int)$valores['empate'] > 10) {
            $errores_campo['empate'] = 'El empate vale de 0 a 10 puntos.';
        }
        if (!ctype_digit($valores['derrota']) || (int)$valores['derrota'] > 10) {
            $errores_campo['derrota'] = 'La derrota vale de 0 a 10 puntos.';
        }
        # La misma regla que ck_config_pts: la victoria vale al menos lo
        # que el empate, y el empate al menos lo que la derrota.
        if (!isset($errores_campo['victoria']) && !isset($errores_campo['empate'])
            && (int)$valores['empate'] > (int)$valores['victoria']) {
            $errores_campo['empate'] = 'El empate no vale más que la victoria.';
        }
        if (!isset($errores_campo['empate']) && !isset($errores_campo['derrota'])
            && (int)$valores['derrota'] > (int)$valores['empate']) {
            $errores_campo['derrota'] = 'La derrota no vale más que el empate.';
        }

        if (!array_key_exists($valores['desempate'], ConfiguracionTorneo::criterios())) {
            $errores_campo['desempate'] = 'Falta elegir el criterio de desempate.';
        }

        if ($valores['inicio'] !== '' && !Torneo::fechaValida($valores['inicio'])) {
            $errores_campo['inicio'] = 'La fecha de inicio no es una fecha válida.';
        }

        if (empty($errores_campo)) {
            $tipo   = $catalogos->tipoPorNombre('Por equipos');
            $modulo = $catalogos->moduloPorNombre('Liga');
            if ($tipo === null || $modulo === null) {
                $errores[] = 'La liga no se puede crear por ahora.';
            } else {
                $torneo = new Torneo(null, $valores['nombre'], $disciplinas[$id_disciplina], $tipo, $modulo,
                                     $organizador, ($valores['inicio'] === '') ? null : $valores['inicio'],
                                     null, (int)$valores['cupo'], null, 'inscripcion');
                $configuracion = new ConfiguracionTorneo(null, (int)$valores['victoria'],
                                     (int)$valores['empate'], (int)$valores['derrota'], 1, 0,
                                     ($valores['vueltas'] === 'dos') ? 1 : 0, null, null,
                                     $valores['desempate']);

                $repositorio = new TorneoRepositorio($conexion);
                $resultado   = $repositorio->crearLiga($torneo, $configuracion);

                if (empty($resultado)) {
                    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
                    $auditorias = new AuditoriaRepositorio($conexion);
                    $auditorias->registrar(new Auditoria(null, $organizador, 'torneo', 'alta',
                        $torneo->getIdTorneo(), mb_substr('Liga ' . $torneo->getNombre(), 0, 255), $ip));
                    $conexion->close();
                    header('Location: ' . $ruta_panel . '?aviso=liga-creada#liga-' . (int)$torneo->getIdTorneo());
                    exit;
                }

                # El nombre repetido es un error del campo nombre; lo
                # demas va arriba.
                foreach ($resultado as $error) {
                    if (strpos($error, 'nombre') !== false) {
                        $errores_campo['nombre'] = $error;
                    } else {
                        $errores[] = $error;
                    }
                }
            }
        }
    }
}

$conexion->close();

include __DIR__ . '/../crear.php';
