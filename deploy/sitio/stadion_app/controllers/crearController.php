<?php
# Controlador: crear un torneo (una liga, una eliminacion directa o un suizo)

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
require_once __DIR__ . '/../fechas.php';
require_once __DIR__ . '/../models/Suizo.php';

# Direcciones.
if (!isset($ruta_publica)) { $ruta_publica = '../../public'; }
if (!isset($ruta_perfil))  { $ruta_perfil  = 'perfilController.php'; }
if (!isset($ruta_crear))   { $ruta_crear   = 'crearController.php'; }
if (!isset($ruta_panel))   { $ruta_panel   = 'panelController.php'; }

function formatosDeTorneo()
{
    return array(
        'liga'        => array('Liga', 'Liga', 'Todos contra todos, fecha por fecha. Gana el primero de la tabla.'),
        'eliminacion' => array('Eliminación directa', 'Eliminación directa', 'Una llave: quien pierde queda afuera. Si los inscriptos no llenan la llave, los primeros pasan la primera ronda sin jugar.'),
        'suizo'       => array('Suizo', 'Suizo', 'Rondas fijas; en cada una se cruzan los de puntaje parecido, sin repetir rival. Gana el primero de la tabla.')
    );
}

function formatosDePartido()
{
    return array(
        'empate' => array('Partido único', 'Puede terminar empatado.'),
        'unico'  => array('Partido único, siempre con ganador', 'Ningún partido termina empatado.'),
        '3'      => array('Al mejor de 3', 'Mapas, sets o partidas: gana quien llega a 2.'),
        '5'      => array('Al mejor de 5', 'Gana quien llega a 3.'),
        '7'      => array('Al mejor de 7', 'Gana quien llega a 4.')
    );
}

# Hoy, en Montevideo (apps/fechas.php): una liga no empieza antes.
$hoy = date('Y-m-d');

$titulo  = 'Nuevo torneo';
$mensaje = '';
$errores = array();
$errores_campo = array();   # campo => mensaje, para mostrarlo al lado

# 1. Sin sesion vigente, al acceso
if (!sesionVigente()) {
    header('Location: ' . $ruta_publica . '/login.php');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

# 2. Conexion, cuenta y rol
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

$modulos = $catalogos->modulos();
$formatos_habilitados = array();
foreach (formatosDeTorneo() as $clave => $f) {
    if ($modulos === null || (isset($modulos[$f[1]]) && $modulos[$f[1]]->estaHabilitado())) {
        $formatos_habilitados[] = $clave;
    }
}

$valores = array(
    'formato'    => empty($formatos_habilitados) ? 'liga' : $formatos_habilitados[0],
    'rondas'     => '',
    'nombre'     => '',
    'disciplina' => '',
    'compite'    => 'equipos',
    'cupo'       => '16',
    'vueltas'    => 'una',
    'partido'    => 'empate',
    'victoria'   => '3',
    'empate'     => '1',
    'derrota'    => '0',
    'desempate'  => 'diferencia',
    'inicio'     => '',
    'sede'       => '',
    'clasifican' => '0'
);

# 3. Guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrfValido()) {
        $errores[] = rechazarCsrf();
    } else {
        foreach ($valores as $campo => $defecto) {
            # Solo textos: un campo mandado como arreglo (nombre[]=x) queda vacio.
            $valores[$campo] = (isset($_POST[$campo]) && is_string($_POST[$campo])) ? trim($_POST[$campo]) : '';
        }

        # Cada campo, con su mensaje.
        if ($valores['nombre'] === '') {
            $errores_campo['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($valores['nombre']) < 4 || mb_strlen($valores['nombre']) > 80) {
            $errores_campo['nombre'] = 'El nombre lleva entre 4 y 80 caracteres.';
        }

        $id_disciplina = (int)$valores['disciplina'];
        if (!isset($disciplinas[$id_disciplina])) {
            $errores_campo['disciplina'] = 'Falta elegir el juego o deporte.';
        }

        $formatos = formatosDeTorneo();
        if (!isset($formatos[$valores['formato']])) {
            $errores_campo['formato'] = 'Falta elegir el formato.';
        } elseif (!in_array($valores['formato'], $formatos_habilitados, true)) {
            $errores_campo['formato'] = 'Ese formato no está habilitado.';
        } else {
            if ($valores['formato'] === 'eliminacion' && $valores['partido'] === 'empate') {
                $errores_campo['partido'] = 'En una eliminación directa ningún partido termina empatado.';
            }
            if ($valores['formato'] !== 'liga' && $valores['vueltas'] === 'dos') {
                $errores_campo['vueltas'] = 'Solo una liga se juega de ida y vuelta.';
            }
            if ($valores['formato'] === 'suizo' && $valores['rondas'] !== ''
                && (!ctype_digit($valores['rondas']) || (int)$valores['rondas'] < 3 || (int)$valores['rondas'] > 7)) {
                $errores_campo['rondas'] = 'El suizo se juega de 3 a 7 rondas.';
            }
        }

        if ($valores['compite'] !== 'equipos' && $valores['compite'] !== 'jugadores') {
            $errores_campo['compite'] = 'Falta elegir si compiten equipos o jugadores.';
        }

        if (!ctype_digit($valores['cupo']) || (int)$valores['cupo'] < 4 || (int)$valores['cupo'] > 32) {
            $errores_campo['cupo'] = 'El cupo va de 4 a 32.';
        }

        if ($valores['vueltas'] !== 'una' && $valores['vueltas'] !== 'dos') {
            $errores_campo['vueltas'] = 'Falta elegir una vuelta o ida y vuelta.';
        }

        if (!array_key_exists($valores['partido'], formatosDePartido())) {
            $errores_campo['partido'] = 'Falta elegir cómo se juega cada partido.';
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

        # La sede (RF-13): opcional, el lugar donde se juega.
        if (mb_strlen($valores['sede']) > 80) {
            $errores_campo['sede'] = 'La sede lleva hasta 80 caracteres.';
        }

        # Cuantos clasifican a playoffs (RF-14), solo en la liga.
        if ($valores['clasifican'] === '') {
            $valores['clasifican'] = '0';
        }
        if (!in_array($valores['clasifican'], array('0', '2', '4', '8'), true)) {
            $errores_campo['clasifican'] = 'A los playoffs clasifican 2, 4 u 8, o ninguno.';
        } elseif ($valores['clasifican'] !== '0' && $valores['formato'] !== 'liga') {
            $errores_campo['clasifican'] = 'Solo en una liga clasifican a playoffs.';
        } elseif (!isset($errores_campo['cupo']) && (int)$valores['clasifican'] > (int)$valores['cupo']) {
            $errores_campo['clasifican'] = 'A los playoffs no clasifican más que el cupo.';
        }

        if ($valores['inicio'] !== '' && !Torneo::fechaValida($valores['inicio'])) {
            $errores_campo['inicio'] = 'La fecha de inicio no es una fecha válida.';
        } elseif ($valores['inicio'] !== '' && $valores['inicio'] < $hoy) {
            # Las dos van como AAAA-MM-DD: comparar el texto es comparar las fechas.
            $errores_campo['inicio'] = 'La fecha de inicio va de hoy en adelante.';
        }

        if (empty($errores_campo)) {
            $tipo   = $catalogos->tipoPorNombre(($valores['compite'] === 'jugadores') ? 'Individual' : 'Por equipos');
            $modulo = $catalogos->moduloPorNombre($formatos[$valores['formato']][1]);
            if ($tipo === null || $modulo === null) {
                $errores[] = 'La liga no se puede crear por ahora.';
            } else {
                $torneo = new Torneo(null, $valores['nombre'], $disciplinas[$id_disciplina], $tipo, $modulo,
                                     $organizador, ($valores['inicio'] === '') ? null : $valores['inicio'],
                                     null, (int)$valores['cupo'], ($valores['sede'] === '') ? null : $valores['sede'],
                                     'inscripcion');
                $configuracion = new ConfiguracionTorneo(null, (int)$valores['victoria'],
                                     (int)$valores['empate'], (int)$valores['derrota'],
                                     ($valores['partido'] === 'empate') ? 1 : 0, (int)$valores['clasifican'],
                                     ($valores['vueltas'] === 'dos') ? 1 : 0, null, null,
                                     $valores['desempate'],
                                     ctype_digit($valores['partido']) ? (int)$valores['partido'] : null);
                # Las rondas del suizo; en los otros, las pone el fixture.
                if ($valores['formato'] === 'suizo' && $valores['rondas'] !== '') {
                    $configuracion = new ConfiguracionTorneo(null, $configuracion->getPuntosVictoria(),
                        $configuracion->getPuntosEmpate(), $configuracion->getPuntosDerrota(),
                        $configuracion->getAdmiteEmpate(), 0, 0, (int)$valores['rondas'], null,
                        $configuracion->getCriterioDesempate(), $configuracion->getMejorDe());
                }

                $repositorio = new TorneoRepositorio($conexion);
                $resultado   = $repositorio->crearLiga($torneo, $configuracion);

                if (empty($resultado)) {
                    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
                    $auditorias = new AuditoriaRepositorio($conexion);
                    $auditorias->registrar(new Auditoria(null, $organizador, 'torneo', 'alta',
                        $torneo->getIdTorneo(), mb_substr($formatos[$valores['formato']][0] . ' · ' . $torneo->getNombre(), 0, 255), $ip));
                    $conexion->close();
                    header('Location: ' . $ruta_panel . '?aviso=liga-creada#liga-' . (int)$torneo->getIdTorneo());
                    exit;
                }

                # El nombre repetido es un error del campo nombre; lo demas va arriba.
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
