<?php
# =====================================================================
# Modelo: Suizo   ->   tablas "ronda" y "enfrentamiento"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Empareja las rondas de un sistema suizo (RF-20): nadie queda afuera,
# se juega una cantidad fija de rondas, y en cada una se cruzan los que
# van con un puntaje parecido, sin repetir un cruce. Es una clase del
# dominio, como Fixture y Llave: recibe los participantes (y, desde la
# segunda ronda, la tabla y lo ya jugado) y devuelve los cruces.
#
# Primera ronda: por orden de inscripcion, la mitad de arriba contra la
# de abajo (con 8: 1-5, 2-6, 3-7, 4-8).
#
# Las siguientes: los participantes en el orden de la tabla (puntos y
# desempate de la competencia). El primero sin rival se cruza con el
# siguiente con quien todavia no jugo; si eso deja a alguno sin rival
# posible mas abajo, se prueba con el que sigue (se vuelve atras). Es
# local el que va mas arriba en la tabla. Si no hay forma de armar la
# ronda sin repetir (pasa solo con muchas rondas para pocos
# participantes), se aparean en orden, 1-2, 3-4, aunque alguno repita.
#
# Con una cantidad impar, uno queda libre: el de mas abajo en la tabla
# que todavia no quedo libre. En el suizo, quedar libre suma como un
# partido ganado, sin tantos (ver TablaPosiciones): si no, el libre
# perderia puntos por algo que no eligio.
# =====================================================================

require_once __DIR__ . '/Participante.php';

class Suizo
{
    #region FUNCIONES

    # Las rondas que se sugieren para esa cantidad: las de una llave
    # (con 8, 3; con 16, 4), y al menos 3.
    public static function rondasSugeridas($cantidad)
    {
        $rondas = 0;
        for ($t = 1; $t < (int)$cantidad; $t = $t * 2) {
            $rondas++;
        }
        return max(3, $rondas);
    }

    # Las rondas que admite esa cantidad sin que nadie repita rival: una
    # menos que los participantes (o tantas como ellos, si son impares,
    # como en una liga).
    public static function rondasMaximas($cantidad)
    {
        $cantidad = (int)$cantidad;
        return ($cantidad % 2 === 0) ? $cantidad - 1 : $cantidad;
    }

    # La primera ronda, con los participantes en orden de inscripcion.
    # Cada cruce: array('local' => Participante, 'visitante' =>
    # Participante o null). El libre, si hay, va al final.
    public static function primeraRonda($participantes)
    {
        $lista = array_values($participantes);
        $libre = null;
        if (count($lista) % 2 === 1) {
            $libre = array_pop($lista);
        }
        $mitad  = intdiv(count($lista), 2);
        $cruces = array();
        for ($i = 0; $i < $mitad; $i++) {
            $cruces[] = array('local' => $lista[$i], 'visitante' => $lista[$i + $mitad]);
        }
        if ($libre !== null) {
            $cruces[] = array('local' => $libre, 'visitante' => null);
        }
        return $cruces;
    }

    # La clave de un cruce, sin importar quien fue local: "3-7".
    public static function clave($id_a, $id_b)
    {
        return min((int)$id_a, (int)$id_b) . '-' . max((int)$id_a, (int)$id_b);
    }

    # Una ronda siguiente.
    #   $ordenados  los participantes en el orden de la tabla
    #   $cruzados   clave => true de cada cruce ya jugado (ver clave())
    #   $libres     id de participante => true de los que ya quedaron libres
    # Devuelve los cruces, como primeraRonda().
    public static function emparejar($ordenados, $cruzados, $libres)
    {
        $lista = array_values($ordenados);
        $libre = null;
        if (count($lista) % 2 === 1) {
            for ($i = count($lista) - 1; $i >= 0; $i--) {
                if (!isset($libres[(int)$lista[$i]->getIdParticipante()])) {
                    $libre = $lista[$i];
                    array_splice($lista, $i, 1);
                    break;
                }
            }
            # Si ya quedaron libres todos, le toca al ultimo otra vez.
            if ($libre === null) {
                $libre = array_pop($lista);
            }
        }

        $pares = self::buscar($lista, $cruzados);
        if ($pares === null) {
            $pares = array();
            for ($i = 0; $i < count($lista); $i = $i + 2) {
                $pares[] = array($lista[$i], $lista[$i + 1]);
            }
        }

        $cruces = array();
        foreach ($pares as $par) {
            $cruces[] = array('local' => $par[0], 'visitante' => $par[1]);
        }
        if ($libre !== null) {
            $cruces[] = array('local' => $libre, 'visitante' => null);
        }
        return $cruces;
    }

    # Aparea la lista de arriba hacia abajo sin repetir cruces, volviendo
    # atras cuando hace falta. null si no hay forma.
    private static function buscar($lista, $cruzados)
    {
        if (empty($lista)) {
            return array();
        }
        $primero = $lista[0];
        for ($j = 1; $j < count($lista); $j++) {
            if (isset($cruzados[self::clave($primero->getIdParticipante(), $lista[$j]->getIdParticipante())])) {
                continue;
            }
            $resto = $lista;
            unset($resto[$j], $resto[0]);
            $siguen = self::buscar(array_values($resto), $cruzados);
            if ($siguen !== null) {
                return array_merge(array(array($primero, $lista[$j])), $siguen);
            }
        }
        return null;
    }

    #endregion
}
