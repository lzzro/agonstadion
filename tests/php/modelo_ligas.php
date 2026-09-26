<?php
# =====================================================================
# Las reglas de las ligas en los modelos, sin base - Stadion (Agon)
# ---------------------------------------------------------------------
#   php tests/php/modelo_ligas.php
#
# Lo que deciden las clases del dominio, antes de llegar a la base:
#   - Torneo: el cupo de una liga (4 a 32), la fecha de inicio opcional
#     pero valida, cerrar la inscripcion (4 o mas, y solo si esta
#     abierta);
#   - ConfiguracionTorneo: los puntos (victoria 1 a 10, el empate no pasa
#     la victoria), el criterio de desempate y el orden de la tabla, las
#     fechas de una liga;
#   - PedidoInscripcion: lo resuelve solo quien organiza la liga, y solo
#     si esta pendiente; lo pide el capitan;
#   - Disciplina: la unidad de los tantos;
#   - Usuario: la marca de cuenta de muestra.
# =====================================================================

require __DIR__ . '/comun.php';
require_once $APLICACION . '/models/Torneo.php';
require_once $APLICACION . '/models/ConfiguracionTorneo.php';
require_once $APLICACION . '/models/PosicionTabla.php';
require_once $APLICACION . '/models/PedidoInscripcion.php';

echo "===== Reglas de las ligas en los modelos =====\n";

$organizador = new Usuario(10, 'org@ejemplo.invalid', null, 'Olga', 'Organiza');
$otro        = new Usuario(11, 'otro@ejemplo.invalid', null, 'Otto', 'Otro');
$capitan     = new Usuario(12, 'cap@ejemplo.invalid', null, 'Carla', 'Capitana');

function liga($cupo, $inicio = null, $estado = 'inscripcion')
{
    global $organizador;
    return new Torneo(1, 'Liga de prueba', new Disciplina(1, 'Fútbol'),
                      new TipoTorneo(2, 'Por equipos', 1), new ModuloCompetencia(1, 'Liga'),
                      $organizador, $inicio, null, $cupo, null, $estado);
}

# --- Torneo ---------------------------------------------------------------
chk(!empty(liga(3)->validarLiga()) && !empty(liga(33)->validarLiga()), 'el cupo de una liga no baja de 4 ni pasa de 32');
chk(empty(liga(4)->validarLiga()) && empty(liga(32)->validarLiga()), '4 y 32 estan bien');
chk(empty(liga(8, null)->validarLiga()), 'sin fecha de inicio esta bien (queda a definir)');
chk(!empty(liga(8, '2026-02-30')->validarLiga()) && !empty(liga(8, '4/10/2026')->validarLiga()),
    'una fecha que no existe, o con otra forma, no');
chk(empty(liga(8, '2028-02-29')->validarLiga()), 'el 29 de febrero de un bisiesto, si');
$individual = new Torneo(1, 'Liga de prueba', new Disciplina(1, 'Ajedrez'), new TipoTorneo(1, 'Individual', 0),
                         new ModuloCompetencia(1, 'Liga'), $organizador, null, null, 8);
chk(!empty($individual->validarLiga()), 'una liga es por equipos');

$t = liga(8);
chk(!empty($t->cerrarInscripcion(3)) && $t->tieneInscripcionAbierta(), 'con 3 equipos la inscripcion no se cierra');
chk(empty($t->cerrarInscripcion(4)) && $t->estaEnCurso(), 'con 4 se cierra y la liga queda en curso');
chk(!empty($t->cerrarInscripcion(10)), 'una inscripcion cerrada no se cierra otra vez');

# --- ConfiguracionTorneo ----------------------------------------------------
$una = new ConfiguracionTorneo(1);
$dos = new ConfiguracionTorneo(1, 3, 1, 0, 1, 0, 1);
chk($una->rondasDeLiga(12) === 11 && $una->rondasDeLiga(5) === 5 && $dos->rondasDeLiga(12) === 22 && $dos->rondasDeLiga(5) === 10,
    'fechas: 12 equipos 11 (22 de ida y vuelta), 5 equipos 5 (10)');
chk($una->enfrentamientosDeLiga(12) === 66 && $dos->enfrentamientosDeLiga(5) === 20, 'partidos: 12 equipos 66, 5 de ida y vuelta 20');
chk(!empty((new ConfiguracionTorneo(1, 0))->validar()) && !empty((new ConfiguracionTorneo(1, 11))->validar()),
    'la victoria vale de 1 a 10');
chk(!empty((new ConfiguracionTorneo(1, 2, 3, 0))->validar()), 'el empate no vale mas que la victoria');
chk(!empty((new ConfiguracionTorneo(1, 3, 1, 2))->validar()), 'la derrota no vale mas que el empate');
chk(!empty((new ConfiguracionTorneo(1, 3, 1, 0, 1, 0, 0, null, null, 'azar'))->validar()), 'un criterio de desempate inventado, no');
chk(empty((new ConfiguracionTorneo(1, 3, 1, 0, 1, 0, 0, null, null, 'favor'))->validar()), '"favor" si');

# El orden de la tabla segun el criterio.
function fila($nombre, $g, $e, $p, $favor, $contra)
{
    return new PosicionTabla(new Participante(null, null, new Equipo(null, $nombre)), $g, $e, $p, $favor, $contra);
}
function orden($config, $filas)
{
    $nombres = array();
    foreach ($config->ordenarPosiciones($filas) as $f) { $nombres[] = $f->getNombreVisible(); }
    return implode(', ', $nombres);
}
# A y B con los mismos puntos: A tiene mas diferencia, B mas tantos a favor.
$filas = array(fila('B', 2, 0, 1, 10, 8), fila('A', 2, 0, 1, 6, 2), fila('C', 3, 0, 0, 3, 0), fila('D', 0, 0, 3, 1, 9));
chk(orden(new ConfiguracionTorneo(1), $filas) === 'C, A, B, D', 'por diferencia: C, A (+4), B (+2), D');
chk(orden(new ConfiguracionTorneo(1, 3, 1, 0, 1, 0, 0, null, null, 'favor'), $filas) === 'C, B, A, D', 'por favor: C, B (10), A (6), D');
$iguales = array(fila('Zeta', 1, 0, 0, 2, 1), fila('Alfa', 1, 0, 0, 2, 1));
chk(orden(new ConfiguracionTorneo(1), $iguales) === 'Alfa, Zeta', 'si todo coincide, el orden alfabetico');
# Sin mirar tildes ni mayusculas: por bytes, "Ómnibus" y "Álamo" irian
# despues de "Zeta", y "ZZ" antes que "aa".
$con_tildes = array(fila('Zeta', 1, 0, 0, 2, 1), fila('Ómnibus', 1, 0, 0, 2, 1), fila('Álamo', 1, 0, 0, 2, 1),
                    fila('Nova', 1, 0, 0, 2, 1), fila('Aurora', 1, 0, 0, 2, 1));
chk(orden(new ConfiguracionTorneo(1), $con_tildes) === 'Álamo, Aurora, Nova, Ómnibus, Zeta',
    'el orden alfabetico no mira tildes: Álamo, Aurora, Nova, Ómnibus, Zeta');
chk(ConfiguracionTorneo::compararNombres('ZZ Esports', 'aa Esports') > 0 && ConfiguracionTorneo::compararNombres('Vortex', 'vortex') !== 0,
    'ni mayusculas; y dos nombres que solo cambian en eso no quedan empatados');
chk(strpos((new ConfiguracionTorneo(1))->textoDesempate('mapas'), 'diferencia de mapas') !== false,
    'el texto del desempate lleva la unidad de la disciplina');

# --- PedidoInscripcion ------------------------------------------------------------
$equipo = new Equipo(5, 'Los de prueba', null, $capitan);
$pedido = new PedidoInscripcion(1, liga(8), $equipo, $capitan);
chk(empty($pedido->validar()), 'lo pide el capitan: bien');
chk(!empty((new PedidoInscripcion(1, liga(8), $equipo, $otro))->validar()), 'lo pide otro que no es el capitan: no');
chk(!empty((new PedidoInscripcion(1, liga(8), new Equipo(6, 'Sin capitan'), $capitan))->validar()), 'un equipo sin capitan no pide');
chk(empty($pedido->motivosParaNoResolver($organizador)), 'lo resuelve quien organiza la liga');
chk(!empty($pedido->motivosParaNoResolver($otro)) && !empty($pedido->motivosParaNoResolver(null)), 'otra cuenta, o nadie, no');
$resuelto = new PedidoInscripcion(1, liga(8), $equipo, $capitan, 'aceptado');
chk(!empty($resuelto->motivosParaNoResolver($organizador)), 'un pedido ya resuelto no se resuelve otra vez');

# --- Disciplina y Usuario ----------------------------------------------------------
chk((new Disciplina(1, 'Esports'))->getUnidad() === 'mapas' && (new Disciplina(1, 'Fútbol 5'))->getUnidad() === 'goles'
    && (new Disciplina(1, 'Ajedrez'))->getUnidad() === 'tantos', 'unidades: mapas, goles, tantos');
$muestra = new Usuario(1, 'x@ejemplo.invalid', '!cuenta-de-muestra:sin-clave', 'A', 'B', null, null, 1, null, null, null, 1);
chk($muestra->esDeMuestra() && !$muestra->verificarClave('!cuenta-de-muestra:sin-clave'), 'una cuenta de muestra: marcada, y su texto no es una clave');
chk(!(new Usuario(1, 'y@ejemplo.invalid', null, 'A', 'B'))->esDeMuestra(), 'una cuenta comun no es de muestra');

fin();
