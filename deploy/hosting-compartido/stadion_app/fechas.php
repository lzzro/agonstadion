<?php
# =====================================================================
# Fechas en castellano, para las paginas de las ligas
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La base guarda las fechas como 2026-09-18 y 2026-09-18 19:00:00. En
# pantalla van en castellano: "viernes 18 de septiembre", "19:00". El
# dia de la semana sale de la fecha misma (date('w')), nunca escrito a
# mano: asi no puede quedar un "jueves 18" en un ano en que el 18 es
# viernes.
#
# La hora de "hoy" es la de Montevideo, no la del servidor (que en un
# hosting suele estar en otra zona).
# =====================================================================

date_default_timezone_set('America/Montevideo');

function nombreMes($numero)
{
    $meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                   'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
    return isset($meses[(int)$numero]) ? $meses[(int)$numero] : '';
}

function nombreDiaSemana($fecha)
{
    $dias = array('domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado');
    $marca = strtotime(substr((string)$fecha, 0, 10));
    return ($marca === false) ? '' : $dias[(int)date('w', $marca)];
}

# "18 de septiembre", o con $con_anio "18 de septiembre de 2026".
function fechaTexto($fecha, $con_anio = false)
{
    $partes = explode('-', substr((string)$fecha, 0, 10));
    if (count($partes) !== 3 || nombreMes($partes[1]) === '') {
        return '';
    }
    $texto = (int)$partes[2] . ' de ' . nombreMes($partes[1]);
    return $con_anio ? $texto . ' de ' . $partes[0] : $texto;
}

# "viernes 18 de septiembre"
function fechaConDia($fecha)
{
    $texto = fechaTexto($fecha);
    return ($texto === '') ? '' : nombreDiaSemana($fecha) . ' ' . $texto;
}

# "19:00", de un 2026-09-18 19:00:00. Vacio si no hay hora.
function horaTexto($fecha_hora)
{
    return (strlen((string)$fecha_hora) >= 16) ? substr($fecha_hora, 11, 5) : '';
}

# Dias de $desde a $hasta (AAAA-MM-DD). Negativo si $hasta ya paso.
function diasEntre($desde, $hasta)
{
    $a = strtotime(substr((string)$desde, 0, 10) . ' 12:00:00');
    $b = strtotime(substr((string)$hasta, 0, 10) . ' 12:00:00');
    return (int)round(($b - $a) / 86400);
}

# El lunes de la semana de una fecha.
function lunesDe($fecha)
{
    $marca = strtotime(substr((string)$fecha, 0, 10) . ' 12:00:00');
    $dia   = (int)date('N', $marca);   # 1 = lunes ... 7 = domingo
    return date('Y-m-d', $marca - ($dia - 1) * 86400);
}

function sumarDias($fecha, $dias)
{
    return date('Y-m-d', strtotime(substr((string)$fecha, 0, 10) . ' 12:00:00') + (int)$dias * 86400);
}
