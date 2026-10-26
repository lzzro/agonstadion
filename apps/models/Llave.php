<?php
# Modelo: Llave -> tablas "ronda" y "enfrentamiento"

require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Enfrentamiento.php';

class Llave
{
    #region ATRIBUTOS
    private $participantes;
    private $cruces;
    #endregion

    #region FUNCIONES

    public function __construct($participantes)
    {
        $this->participantes = array_values($participantes);
        $this->cruces        = array();
    }

    public function getCruces() { return $this->cruces; }

    # La potencia de 2 que alcanza a $cantidad: 5 a 8 da 8.
    public static function tamano($cantidad)
    {
        $tamano = 1;
        while ($tamano < (int)$cantidad) {
            $tamano = $tamano * 2;
        }
        return $tamano;
    }

    # Cuantas rondas tiene la llave: con 8 lugares, 3 (cuartos, semi y final).
    public static function cantidadRondas($cantidad)
    {
        $rondas = 0;
        for ($t = self::tamano($cantidad); $t > 1; $t = intdiv($t, 2)) {
            $rondas++;
        }
        return $rondas;
    }

    # El orden de los numeros de siembra en una llave de ese tamano.
    public static function ordenSiembra($tamano)
    {
        $orden = array(1);
        while (count($orden) < $tamano) {
            $doble = count($orden) * 2;
            $nuevo = array();
            foreach ($orden as $numero) {
                $nuevo[] = $numero;
                $nuevo[] = $doble + 1 - $numero;
            }
            $orden = $nuevo;
        }
        return $orden;
    }

    # El nombre de una ronda por los cruces que tiene: 1 es la final, 2 la semifinal, 4 los cuartos...
    public static function nombreRonda($cruces)
    {
        $nombres = array(1 => 'Final', 2 => 'Semifinal', 4 => 'Cuartos de final',
                         8 => 'Octavos de final', 16 => 'Dieciseisavos de final');
        return isset($nombres[(int)$cruces]) ? $nombres[(int)$cruces] : 'Ronda de ' . ((int)$cruces * 2);
    }

    # De 4 a 32, el mismo cupo de todas las competencias.
    public function validar()
    {
        $errores  = array();
        $cantidad = count($this->participantes);
        if ($cantidad < 4) {
            $errores[] = 'La llave necesita al menos 4 participantes.';
        } elseif ($cantidad > 32) {
            $errores[] = 'La llave admite hasta 32 participantes.';
        }
        foreach ($this->participantes as $participante) {
            if (!($participante instanceof Participante)) {
                $errores[] = 'Hay un participante que no es válido.';
                break;
            }
        }
        return $errores;
    }

    # Arma la primera ronda.
    public function generar()
    {
        $errores = $this->validar();
        if (!empty($errores)) {
            return $errores;
        }
        $cantidad = count($this->participantes);
        $orden    = self::ordenSiembra(self::tamano($cantidad));
        $this->cruces = array();
        for ($i = 0; $i < count($orden); $i = $i + 2) {
            $a = $orden[$i];
            $b = $orden[$i + 1];
            # Por la siembra, $a es siempre el numero mas chico del par, y nunca pasa la cantidad de inscriptos.
            $this->cruces[] = array(
                'local'     => $this->participantes[$a - 1],
                'visitante' => ($b <= $cantidad) ? $this->participantes[$b - 1] : null
            );
        }
        return array();
    }

    # La ronda que sigue, con los ganadores en el orden de los cruces de la anterior.
    public static function siguienteRonda($ganadores)
    {
        $ganadores = array_values($ganadores);
        if (count($ganadores) < 2 || count($ganadores) % 2 === 1) {
            return array();
        }
        $cruces = array();
        for ($i = 0; $i < count($ganadores); $i = $i + 2) {
            $cruces[] = array('local' => $ganadores[$i], 'visitante' => $ganadores[$i + 1]);
        }
        return $cruces;
    }

    # Quien pasa de un enfrentamiento: el libre, o el ganador de su resultado. null si todavia no se sabe.
    public static function quienPasa(Enfrentamiento $enfrentamiento)
    {
        if ($enfrentamiento->esLibre()) {
            return $enfrentamiento->getLocal();
        }
        $resultado = $enfrentamiento->getResultado();
        if (!$enfrentamiento->estaJugado() || $resultado === null) {
            return null;
        }
        return $resultado->getGanador();
    }

    #endregion
}
