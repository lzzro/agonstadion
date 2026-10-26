<?php
# Modelo: Fixture -> tablas "ronda" y "enfrentamiento"

require_once __DIR__ . '/Participante.php';

class Fixture
{
    #region ATRIBUTOS
    private $participantes;   # arreglo de Participante, en orden de inscripcion
    private $ida_y_vuelta;    # 1 o 0
    private $fechas;
    #endregion

    #region FUNCIONES

    public function __construct($participantes, $ida_y_vuelta = 0)
    {
        $this->participantes = array_values($participantes);
        $this->ida_y_vuelta  = (int)$ida_y_vuelta;
        $this->fechas        = array();
    }

    public function getFechas()   { return $this->fechas; }

    public function getCantidadFechas()
    {
        return count($this->fechas);
    }

    public function getCantidadEnfrentamientos()
    {
        $total = 0;
        foreach ($this->fechas as $cruces) {
            foreach ($cruces as $cruce) {
                if ($cruce['visitante'] !== null) {
                    $total = $total + 1;
                }
            }
        }
        return $total;
    }

    # De 4 a 32 participantes: el mismo cupo de una liga.
    public function validar()
    {
        $errores  = array();
        $cantidad = count($this->participantes);
        if ($cantidad < 4) {
            $errores[] = 'El fixture necesita al menos 4 equipos.';
        } elseif ($cantidad > 32) {
            $errores[] = 'El fixture admite hasta 32 equipos.';
        }
        foreach ($this->participantes as $participante) {
            if (!($participante instanceof Participante)) {
                $errores[] = 'Hay un participante que no es válido.';
                break;
            }
        }
        return $errores;
    }

    # Arma las fechas.
    public function generar()
    {
        $errores = $this->validar();
        if (!empty($errores)) {
            return $errores;
        }

        $lista = $this->participantes;
        # Con una cantidad impar, un lugar vacio: el libre.
        if (count($lista) % 2 === 1) {
            $lista[] = null;
        }
        $n     = count($lista);
        $fijo  = $lista[0];
        $resto = array_slice($lista, 1);

        $this->fechas = array();
        for ($ronda = 0; $ronda < $n - 1; $ronda++) {
            $orden = array_merge(array($fijo), $resto);
            $con_rival = array();
            $libre     = array();

            for ($i = 0; $i < $n / 2; $i++) {
                $a = $orden[$i];
                $b = $orden[$n - 1 - $i];

                if ($i === 0 && $ronda % 2 === 1) {
                    $local = $b;
                    $visitante = $a;
                } else {
                    $local = $a;
                    $visitante = $b;
                }

                # El lugar vacio va siempre como visitante.
                if ($local === null) {
                    $local = $visitante;
                    $visitante = null;
                }

                if ($visitante === null) {
                    $libre[] = array('local' => $local, 'visitante' => null);
                } else {
                    $con_rival[] = array('local' => $local, 'visitante' => $visitante);
                }
            }

            # Los que juegan primero y el libre al final.
            $this->fechas[] = array_merge($con_rival, $libre);

            # El giro: el ultimo pasa adelante y los demas corren un lugar.
            $ultimo = array_pop($resto);
            array_unshift($resto, $ultimo);
        }

        # Segunda vuelta: las mismas fechas, con la localia al reves.
        if ($this->ida_y_vuelta === 1) {
            $vuelta = array();
            foreach ($this->fechas as $cruces) {
                $fecha = array();
                foreach ($cruces as $cruce) {
                    if ($cruce['visitante'] === null) {
                        $fecha[] = $cruce;
                    } else {
                        $fecha[] = array('local' => $cruce['visitante'], 'visitante' => $cruce['local']);
                    }
                }
                $vuelta[] = $fecha;
            }
            $this->fechas = array_merge($this->fechas, $vuelta);
        }

        return array();
    }

    #endregion
}
