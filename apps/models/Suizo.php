<?php
# Modelo: Suizo -> tablas "ronda" y "enfrentamiento"

require_once __DIR__ . '/Participante.php';

class Suizo
{
    #region FUNCIONES

    # Las rondas que se sugieren para esa cantidad: las de una llave (con 8, 3; con 16, 4), y al menos 3.
    public static function rondasSugeridas($cantidad)
    {
        $rondas = 0;
        for ($t = 1; $t < (int)$cantidad; $t = $t * 2) {
            $rondas++;
        }
        return max(3, $rondas);
    }

    public static function rondasMaximas($cantidad)
    {
        $cantidad = (int)$cantidad;
        return ($cantidad % 2 === 0) ? $cantidad - 1 : $cantidad;
    }

    # La primera ronda, con los participantes en orden de inscripcion.
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
