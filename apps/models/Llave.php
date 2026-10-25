<?php
# =====================================================================
# Modelo: Llave   ->   tablas "ronda" y "enfrentamiento"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Arma la llave de una eliminacion directa (RF-19): quien pierde queda
# afuera y quien gana pasa a la ronda siguiente, hasta la final. Es una
# clase del dominio, como Fixture: recibe los participantes y devuelve
# los cruces, sin saber nada de la base (la guardan FixtureRepositorio
# y, ronda a ronda, ResultadoRepositorio).
#
# El tamano de la llave es la potencia de 2 que alcanza a todos (con 6,
# una llave de 8). Los lugares que sobran son pases libres (bye): el
# que le toca uno pasa a la segunda ronda sin jugar. En la base, un
# libre es un enfrentamiento sin visitante, como en la liga.
#
# La siembra: cada participante tiene su numero (1, 2, 3...) por orden
# de inscripcion, y los numeros se ubican en la llave de forma que el 1
# y el 2 solo puedan cruzarse en la final, el 1 y el 3 en la semifinal,
# y asi. Para una llave de 8 el orden es 1-8, 4-5, 2-7, 3-6. Se arma
# duplicando: de (1) sale (1, 2); de (1, 2) sale (1, 4, 2, 3); de ahi
# (1, 8, 4, 5, 2, 7, 3, 6). Cada numero n se aparea con (tamano + 1 - n).
# Los numeros que pasan la cantidad de inscriptos son los libres, y por
# la siembra les tocan a los primeros: nunca hay dos libres juntos.
#
# Las rondas siguientes se arman al cerrar cada una, con los ganadores
# en el orden de los cruces: el del cruce 1 con el del 2, el del 3 con
# el del 4. Asi la llave dibujada coincide con lo que se juega.
# =====================================================================

require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Enfrentamiento.php';

class Llave
{
    #region ATRIBUTOS
    private $participantes;   # arreglo de Participante, en orden de inscripcion (la siembra)
    private $cruces;          # la primera ronda: array('local' => Participante, 'visitante' => Participante o null)
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

    # Cuantas rondas tiene la llave: con 8 lugares, 3 (cuartos, semi y
    # final).
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

    # El nombre de una ronda por los cruces que tiene: 1 es la final, 2
    # la semifinal, 4 los cuartos...
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

    # Arma la primera ronda. Devuelve un arreglo de errores, vacio si se
    # armo.
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
            # Por la siembra, $a es siempre el numero mas chico del par, y
            # nunca pasa la cantidad de inscriptos.
            $this->cruces[] = array(
                'local'     => $this->participantes[$a - 1],
                'visitante' => ($b <= $cantidad) ? $this->participantes[$b - 1] : null
            );
        }
        return array();
    }

    # La ronda que sigue, con los ganadores en el orden de los cruces de
    # la anterior. Un numero impar de ganadores no puede pasar en una
    # llave bien armada: devuelve un arreglo vacio.
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

    # Quien pasa de un enfrentamiento: el libre, o el ganador de su
    # resultado. null si todavia no se sabe.
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
