<?php
# =====================================================================
# Vista parcial: como se muestra una liga
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Lo que repiten las paginas que leen ligas de la base (torneos.php,
# torneo.php, el inicio, calendario.php y el panel): el chip de estado,
# la linea de datos de las tarjetas y la barra de avance. Asi una liga
# se nombra igual en todas partes.
#
# El chip sigue la tabla de estados de DESIGN.md (color y forma):
#   inscripcion                  Inscripcion abierta (punto hueco, olivo)
#   en curso, con un partido en vivo   En vivo (punto lleno, cinabrio)
#   en curso                     En juego (cuadrado)
#   finalizado, cancelado        Finalizado / Cancelado (raya)
# =====================================================================

require_once __DIR__ . '/fechas.php';
require_once __DIR__ . '/models/Llave.php';

# array(clase, texto) del chip.
function chipLiga(Torneo $torneo, $en_vivo)
{
    $estado = $torneo->getEstado();
    if ($estado === 'inscripcion') {
        return array('estado-inscripcion', 'Inscripción abierta');
    }
    if ($estado === 'en_curso') {
        return $en_vivo ? array('estado-en-vivo', 'En vivo') : array('estado-en-juego', 'En juego');
    }
    if ($estado === 'finalizado') {
        return array('estado-cerrado', 'Finalizado');
    }
    if ($estado === 'cancelado') {
        return array('estado-cerrado', 'Cancelado');
    }
    return array('estado-cerrado', 'En preparación');
}

function chipLigaHtml(Torneo $torneo, $en_vivo)
{
    $chip = chipLiga($torneo, $en_vivo);
    return '<span class="estado ' . $chip[0] . '">' . $chip[1] . '</span>';
}

# El chip del vencedor (DESIGN.md: color vencedor y la rama de olivo en
# miniatura, el unico lugar donde va la rama fuera del pie). Va solo al
# lado del campeon de una liga terminada.
function chipVencedor()
{
    return '<span class="estado estado-vencedor"><svg width="14" height="10" viewBox="0 0 14 10" aria-hidden="true">'
         . '<path d="M1,7 C4,6.4 9,4.4 13,1" fill="none" stroke="currentColor" stroke-width="1.3"/>'
         . '<path d="M4,6.6 l-1.4,-2.6 M7.5,5.2 l-1.4,-2.6 M11,3.4 l-1.3,-2.2" stroke="currentColor" stroke-width="1.1" fill="none"/>'
         . '</svg> Campeón</span>';
}

function marcaMuestra($texto = 'De muestra')
{
    return '<span class="muestra">' . htmlspecialchars($texto) . '</span>';
}

# La marca de un total: "De muestra" si todo lo que suma sale de ligas de
# muestra, "Incluye muestra" si solo una parte, nada si ninguna.
function marcaTotal($de_muestra, $en_total)
{
    if ($de_muestra <= 0) {
        return '';
    }
    return marcaMuestra(($de_muestra >= $en_total) ? 'De muestra' : 'Incluye muestra');
}

# "equipo" o "equipos"
function plural($cantidad, $singular, $plural)
{
    return (int)$cantidad . ' ' . (((int)$cantidad === 1) ? $singular : $plural);
}

# Los que compiten, con su cantidad: "8 equipos" o, en una liga
# individual (fase 3), "8 jugadores".
function participantesDe(Torneo $torneo, $cantidad)
{
    return $torneo->esPorEquipos() ? plural($cantidad, 'equipo', 'equipos') : plural($cantidad, 'jugador', 'jugadores');
}

# Solo la palabra, en plural: "equipos" o "jugadores".
function nombreParticipantes(Torneo $torneo)
{
    return $torneo->esPorEquipos() ? 'equipos' : 'jugadores';
}

# Las palabras de cada formato (fase 3): una liga juega "fechas" y arma
# un "fixture"; la eliminacion directa y el suizo juegan "rondas", y
# arman una llave o la primera ronda.
function palabrasDe(Torneo $torneo)
{
    if ($torneo->esEliminacion()) {
        return array('el' => 'el torneo', 'El' => 'El torneo', 'Un' => 'Un torneo', 'ronda' => 'ronda', 'rondas' => 'rondas',
                     'calendario' => 'la llave', 'Calendario' => 'Llave', 'pagina' => 'Rondas y resultados',
                     'formato' => 'Eliminación directa');
    }
    if ($torneo->esSuizo()) {
        return array('el' => 'el torneo', 'El' => 'El torneo', 'Un' => 'Un torneo', 'ronda' => 'ronda', 'rondas' => 'rondas',
                     'calendario' => 'la primera ronda', 'Calendario' => 'Primera ronda', 'pagina' => 'Rondas y resultados',
                     'formato' => 'Suizo');
    }
    return array('el' => 'la liga', 'El' => 'La liga', 'Un' => 'Una liga', 'ronda' => 'fecha', 'rondas' => 'fechas',
                 'calendario' => 'el fixture', 'Calendario' => 'Fixture', 'pagina' => 'Fechas y resultados',
                 'formato' => 'Liga');
}

# El formato en pocas palabras: "Liga · una vuelta", "Eliminación
# directa", "Suizo a 4 rondas".
function formatoTexto(Torneo $torneo)
{
    $config = $torneo->getConfiguracion();
    if ($torneo->esEliminacion()) {
        return 'Eliminación directa';
    }
    if ($torneo->esSuizo()) {
        $rondas = ($config === null) ? null : $config->getRondasPrevistas();
        return ($rondas === null) ? 'Suizo' : 'Suizo a ' . plural($rondas, 'ronda', 'rondas');
    }
    return 'Liga · ' . vueltasTexto($config);
}

# Donde esta la liga, en pocas palabras, para las tarjetas: la fecha en
# juego sobre el total, o la inscripcion con el cupo.
#   $fila   un elemento de TorneoRepositorio::listarPublicos()
function avanceLiga($fila)
{
    $torneo = $fila['torneo'];
    if ($torneo->tieneInscripcionAbierta()) {
        $texto = $fila['inscriptos'] . ' de ' . $torneo->getMaxParticipantes() . ' ' . nombreParticipantes($torneo);
        if (!empty($torneo->getFechaInicio())) {
            $texto .= ' · desde el ' . fechaTexto($torneo->getFechaInicio());
        }
        return $texto;
    }
    $texto = participantesDe($torneo, $fila['inscriptos']);
    $palabras = palabrasDe($torneo);
    if ($fila['fechas'] === 0) {
        return $texto . ' · ' . ($torneo->esLiga() ? 'fixture por armar' : str_replace('la ', '', $palabras['calendario']) . ' por armar');
    }
    if ($fila['actual'] === null) {
        return $texto . ' · ' . plural($fila['fechas'], $palabras['ronda'] . ' jugada', $palabras['rondas'] . ' jugadas');
    }
    if ($torneo->esLiga()) {
        return $texto . ' · Fecha ' . $fila['actual'] . ' de ' . $fila['fechas'];
    }
    # En la llave y el suizo las rondas se arman de a una: el total es el
    # previsto en la configuracion.
    $config = $torneo->getConfiguracion();
    $total  = ($config === null || $config->getRondasPrevistas() === null) ? $fila['fechas'] : $config->getRondasPrevistas();
    return $texto . ' · Ronda ' . $fila['actual'] . ' de ' . $total;
}

# El ancho de la barra, en por ciento: la fecha en juego sobre el total
# (la misma regla de siempre: ronda actual sobre rondas totales), o el
# cupo ocupado mientras la inscripcion esta abierta.
function porcentajeLiga($fila)
{
    $torneo = $fila['torneo'];
    if ($torneo->tieneInscripcionAbierta()) {
        $cupo = max(1, $torneo->getMaxParticipantes());
        return (int)round(100 * $fila['inscriptos'] / $cupo);
    }
    if ($fila['fechas'] === 0) {
        return 0;
    }
    $total = $fila['fechas'];
    $config = $torneo->getConfiguracion();
    if (!$torneo->esLiga() && $config !== null && $config->getRondasPrevistas() !== null) {
        $total = max($total, $config->getRondasPrevistas());
    }
    $actual = ($fila['actual'] === null) ? $total : $fila['actual'];
    return (int)round(100 * $actual / $total);
}

# "una vuelta" o "ida y vuelta"
function vueltasTexto($configuracion)
{
    return ($configuracion !== null && $configuracion->esIdaYVuelta()) ? 'ida y vuelta' : 'una vuelta';
}

# El marcador de un resultado, como se muestra: "2 – 1", o "W.O." si se
# gano por walkover (sin marcador: ver Resultado).
function marcadorTexto(Resultado $resultado)
{
    if ($resultado->esWalkover()) {
        return 'W.O.';
    }
    return $resultado->getPuntajeLocal() . ' – ' . $resultado->getPuntajeVisitante();
}

# Un partido jugado visto desde uno de sus dos lados ("Mis resultados"
# del perfil, RF-26): el rival, el marcador con el propio primero y el
# desenlace, 'G', 'E' o 'P' (un walkover se decide por su ganador).
function partidoDesde(Enfrentamiento $enfrentamiento, Participante $propio)
{
    $resultado = $enfrentamiento->getResultado();
    $es_local  = ((int)$enfrentamiento->getLocal()->getIdParticipante() === (int)$propio->getIdParticipante());
    $rival     = $es_local ? $enfrentamiento->getVisitante() : $enfrentamiento->getLocal();
    if ($resultado->esWalkover()) {
        $marcador = 'W.O.';
    } else {
        $propios = $es_local ? $resultado->getPuntajeLocal() : $resultado->getPuntajeVisitante();
        $ajenos  = $es_local ? $resultado->getPuntajeVisitante() : $resultado->getPuntajeLocal();
        $marcador = $propios . ' – ' . $ajenos;
    }
    $ganador = $resultado->getGanador();
    if ($ganador === null) {
        $desenlace = 'E';
    } elseif ((int)$ganador->getIdParticipante() === (int)$propio->getIdParticipante()) {
        $desenlace = 'G';
    } else {
        $desenlace = 'P';
    }
    return array('rival' => $rival, 'marcador' => $marcador, 'desenlace' => $desenlace,
                 'walkover' => $resultado->esWalkover());
}

# El renglon de un partido, igual en el calendario de la liga y en el
# del sitio. A la izquierda la hora (o el marcador, si ya se jugo, o una
# raya si no tiene hora); arriba de los equipos, $arriba_html (el dia,
# o la liga y la fecha), ya escapado por quien llama; a la derecha, el
# chip si esta en vivo. Un marcador nunca se parte (.hora lleva
# white-space: nowrap en el CSS).
function filaPartido(Enfrentamiento $enfrentamiento, $arriba_html)
{
    $resultado = $enfrentamiento->getResultado();
    if ($resultado !== null && $enfrentamiento->estaJugado()) {
        $hora = marcadorTexto($resultado);
    } elseif (horaTexto($enfrentamiento->getFechaHora()) !== '') {
        $hora = horaTexto($enfrentamiento->getFechaHora());
    } else {
        $hora = '—';
    }
    $local = htmlspecialchars($enfrentamiento->getLocal()->getNombreVisible());
    if ($enfrentamiento->esLibre()) {
        $lados = $local . ' · queda libre';
    } else {
        $lados = $local . ' <span class="vs">vs</span> '
               . htmlspecialchars($enfrentamiento->getVisitante()->getNombreVisible());
        # Un walkover no tiene marcador que diga quien gano: lo dice aca.
        if ($resultado !== null && $enfrentamiento->estaJugado() && $resultado->esWalkover()
            && $resultado->getGanador() !== null) {
            $lados .= ' <span class="vs">· gana ' . htmlspecialchars($resultado->getGanador()->getNombreVisible()) . '</span>';
        }
    }
    $chip = $enfrentamiento->estaEnVivo() ? '<span class="estado estado-en-vivo">En vivo</span>' : '';
    echo '<div class="partido"><span class="hora">' . $hora . '</span><span class="cruce-nombres">'
       . '<span class="torneo">' . $arriba_html . '</span><span class="lados">' . $lados . '</span></span>'
       . $chip . '</div>' . "\n";
}

# Con la primera letra en mayuscula ("Viernes 18 de septiembre"). Los
# dias de la semana empiezan todos con una letra sin tilde.
function mayuscula($texto)
{
    return ucfirst((string)$texto);
}

# +9, 0, -1
function conSigno($numero)
{
    return ((int)$numero > 0) ? '+' . (int)$numero : (string)(int)$numero;
}

# La llave de una eliminacion directa (fase 3), con el marcado de
# siempre (.llave, .llave-ronda, .par, .cruce: ver style.css, que la
# dibuja apilada en el telefono y en columnas con lineas desde 1024 px).
# Las rondas que todavia no se armaron van con "Por definir". El que
# pasa de cada cruce va en olivo y en negrita (.pasa), y lo dice tambien
# en texto para el lector de pantalla.
#   $rondas   las rondas jugadas o en juego (FixtureRepositorio::fechasDe)
#   $total    cuantas rondas tiene la llave
function llaveHtml($rondas, $total)
{
    $html = '<div class="llave">' . "\n";
    for ($r = 1; $r <= $total; $r++) {
        $ronda  = isset($rondas[$r - 1]) ? $rondas[$r - 1] : null;
        $cruces = ($ronda === null) ? array() : $ronda->getEnfrentamientos();
        $cantidad = ($ronda === null) ? (int)pow(2, $total - $r) : count($cruces);
        $nombre = ($ronda === null) ? Llave::nombreRonda($cantidad) : $ronda->getNombreVisible();
        $html .= '  <div class="llave-ronda"><span class="etiqueta">' . htmlspecialchars($nombre) . '</span>' . "\n";
        # De a dos cruces por par: el par es lo que se une con la linea.
        for ($i = 0; $i < $cantidad; $i = $i + 2) {
            $html .= '    <div class="par">';
            for ($k = $i; $k < min($i + 2, $cantidad); $k++) {
                $html .= cruceHtml(isset($cruces[$k]) ? $cruces[$k] : null);
            }
            $html .= '</div>' . "\n";
        }
        $html .= '  </div>' . "\n";
    }
    return $html . '</div>' . "\n";
}

# Un cruce de la llave: dos renglones, cada uno con su nombre y su
# marcador. null es un cruce por definir.
function cruceHtml($partido)
{
    if ($partido === null) {
        return '<div class="cruce"><div class="espera"><span>Por definir</span></div><div class="espera"><span>Por definir</span></div></div>';
    }
    $pasa = Llave::quienPasa($partido);
    $resultado = ($partido->estaJugado()) ? $partido->getResultado() : null;
    $renglon = function ($participante, $puntos) use ($pasa) {
        $gana = ($pasa !== null && $participante === $pasa);
        return '<div' . ($gana ? ' class="pasa"' : '') . '><span>' . htmlspecialchars($participante->getNombreVisible())
             . ($gana ? '<span class="visualmente-oculto"> · pasa</span>' : '') . '</span>'
             . ($puntos === null ? '' : '<span class="puntos">' . $puntos . '</span>') . '</div>';
    };
    if ($partido->esLibre()) {
        return '<div class="cruce">' . $renglon($partido->getLocal(), null)
             . '<div class="espera"><span>Pase libre</span></div></div>';
    }
    $wo = ($resultado !== null && $resultado->esWalkover());
    $pl = ($resultado === null) ? null : ($wo ? (($pasa === $partido->getLocal()) ? 'W.O.' : '') : $resultado->getPuntajeLocal());
    $pv = ($resultado === null) ? null : ($wo ? (($pasa === $partido->getVisitante()) ? 'W.O.' : '') : $resultado->getPuntajeVisitante());
    return '<div class="cruce">' . $renglon($partido->getLocal(), $pl) . $renglon($partido->getVisitante(), $pv) . '</div>';
}
