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

function marcaMuestra()
{
    return '<span class="muestra">De muestra</span>';
}

# "equipo" o "equipos"
function plural($cantidad, $singular, $plural)
{
    return (int)$cantidad . ' ' . (((int)$cantidad === 1) ? $singular : $plural);
}

# Donde esta la liga, en pocas palabras, para las tarjetas: la fecha en
# juego sobre el total, o la inscripcion con el cupo.
#   $fila   un elemento de TorneoRepositorio::listarPublicos()
function avanceLiga($fila)
{
    $torneo = $fila['torneo'];
    if ($torneo->tieneInscripcionAbierta()) {
        $texto = $fila['inscriptos'] . ' de ' . $torneo->getMaxParticipantes() . ' equipos';
        if (!empty($torneo->getFechaInicio())) {
            $texto .= ' · desde el ' . fechaTexto($torneo->getFechaInicio());
        }
        return $texto;
    }
    $texto = plural($fila['inscriptos'], 'equipo', 'equipos');
    if ($fila['fechas'] === 0) {
        return $texto . ' · fixture por armar';
    }
    if ($fila['actual'] === null) {
        return $texto . ' · ' . plural($fila['fechas'], 'fecha jugada', 'fechas jugadas');
    }
    return $texto . ' · Fecha ' . $fila['actual'] . ' de ' . $fila['fechas'];
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
    $actual = ($fila['actual'] === null) ? $fila['fechas'] : $fila['actual'];
    return (int)round(100 * $actual / $fila['fechas']);
}

# "una vuelta" o "ida y vuelta"
function vueltasTexto($configuracion)
{
    return ($configuracion !== null && $configuracion->esIdaYVuelta()) ? 'ida y vuelta' : 'una vuelta';
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
    if ($resultado !== null) {
        $hora = $resultado->getPuntajeLocal() . ' – ' . $resultado->getPuntajeVisitante();
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
