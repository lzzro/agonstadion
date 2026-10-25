<?php
# =====================================================================
# Modelo: Medicion   ->   tabla "medicion" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Una medicion de rendimiento fisico (RF-29, el modulo de Fisica): la
# prueba, sus datos crudos y la fecha. Los tres modelos fisicos son los
# de las entregas de Fisica Mecanica Clasica:
#
#   reaccion  Tiempo de reaccion (caida libre). Dato crudo: la distancia
#             d que cae la regla antes de atraparla.
#                 t = raiz(2d / g)
#   sprint    Aceleracion de salida (dinamica). Datos crudos: el tiempo t
#             en recorrer 10 m desde parado, y la masa m.
#                 v = d / t        (velocidad media)
#                 a = 2d / t^2     (de d = a t^2 / 2, partiendo del reposo
#                                   con aceleracion constante)
#                 F = m a          (fuerza neta media)
#   salto     Potencia de salto (trabajo y energia). Datos crudos: el
#             tiempo de vuelo t y la masa m.
#                 h  = g t^2 / 8   (sube la mitad del vuelo, t/2)
#                 Ep = m g h
#                 P  = Ep / t_impulso, con t_impulso = 0,25 s
#
# Con g = 9,8 m/s^2, d = 10 m en el sprint y t_impulso = 0,25 s, los
# mismos valores de la segunda entrega de Fisica.
#
# Solo se guardan los datos crudos: los indicadores son datos derivados
# y se calculan aca cada vez, la misma decision que los puntos de la
# tabla de posiciones (ver PosicionTabla). Asi, si se corrige una
# formula, todas las mediciones se recalculan solas.
#
# Los rangos de validar() son los de la restriccion ck_med_datos: la
# regla mide 30 cm, y los tiempos y las masas, lo posible para una
# persona.
# =====================================================================

require_once __DIR__ . '/Usuario.php';

class Medicion
{
    # Las constantes de los modelos.
    const G           = 9.8;    # aceleracion de la gravedad, m/s^2
    const DISTANCIA   = 10.0;   # tramo del sprint, m
    const T_IMPULSO   = 0.25;   # duracion del empuje del salto, s

    #region ATRIBUTOS
    private $id_medicion;
    private $usuario;            # objeto Usuario
    private $prueba;             # 'reaccion', 'sprint' o 'salto'
    private $distancia_cm;       # solo en reaccion
    private $tiempo_s;           # sprint y salto
    private $masa_kg;            # sprint y salto
    private $fecha;              # AAAA-MM-DD
    private $fecha_carga;
    #endregion

    #region FUNCIONES

    public function __construct($id_medicion, Usuario $usuario, $prueba, $distancia_cm, $tiempo_s,
                                $masa_kg, $fecha, $fecha_carga = null)
    {
        $this->id_medicion  = $id_medicion;
        $this->usuario      = $usuario;
        $this->prueba       = $prueba;
        $this->distancia_cm = ($distancia_cm === null || $distancia_cm === '') ? null : (float)$distancia_cm;
        $this->tiempo_s     = ($tiempo_s === null || $tiempo_s === '') ? null : (float)$tiempo_s;
        $this->masa_kg      = ($masa_kg === null || $masa_kg === '') ? null : (float)$masa_kg;
        $this->fecha        = $fecha;
        $this->fecha_carga  = $fecha_carga;
    }

    public function getIdMedicion()  { return $this->id_medicion; }

    # Unico setter: el id que genera el AUTO_INCREMENT despues del INSERT.
    public function setIdMedicion($id_medicion)
    {
        $this->id_medicion = (int)$id_medicion;
    }

    public function getUsuario()     { return $this->usuario; }
    public function getPrueba()      { return $this->prueba; }
    public function getDistanciaCm() { return $this->distancia_cm; }
    public function getTiempoS()     { return $this->tiempo_s; }
    public function getMasaKg()      { return $this->masa_kg; }
    public function getFecha()       { return $this->fecha; }
    public function getFechaCarga()  { return $this->fecha_carga; }

    # Las pruebas, con su indicador, su unidad y el modelo de Fisica.
    public static function pruebas()
    {
        return array(
            'reaccion' => array('indicador' => 'Tiempo de reacción', 'unidad' => 'milisegundos', 'corta' => 'ms',
                                'modelo' => 'Cinemática · caída libre', 'mejor' => 'menor'),
            'sprint'   => array('indicador' => 'Aceleración de salida', 'unidad' => 'metros / s²', 'corta' => 'm/s²',
                                'modelo' => 'Dinámica · fuerza neta', 'mejor' => 'mayor'),
            'salto'    => array('indicador' => 'Potencia de salto', 'unidad' => 'watts', 'corta' => 'W',
                                'modelo' => 'Trabajo y energía', 'mejor' => 'mayor')
        );
    }

    # --- Modelo 1: tiempo de reaccion -------------------------------------
    public function tiempoReaccionMs()
    {
        if ($this->prueba !== 'reaccion' || $this->distancia_cm === null) {
            return null;
        }
        return sqrt(2 * ($this->distancia_cm / 100) / self::G) * 1000;
    }

    # --- Modelo 2: sprint -------------------------------------------------
    public function velocidadMedia()
    {
        return ($this->prueba === 'sprint' && $this->tiempo_s > 0) ? self::DISTANCIA / $this->tiempo_s : null;
    }

    public function aceleracion()
    {
        return ($this->prueba === 'sprint' && $this->tiempo_s > 0)
             ? 2 * self::DISTANCIA / ($this->tiempo_s * $this->tiempo_s) : null;
    }

    public function fuerza()
    {
        $a = $this->aceleracion();
        return ($a === null || $this->masa_kg === null) ? null : $this->masa_kg * $a;
    }

    # --- Modelo 3: salto --------------------------------------------------
    public function altura()
    {
        return ($this->prueba === 'salto' && $this->tiempo_s !== null)
             ? self::G * $this->tiempo_s * $this->tiempo_s / 8 : null;
    }

    public function energiaPotencial()
    {
        $h = $this->altura();
        return ($h === null || $this->masa_kg === null) ? null : $this->masa_kg * self::G * $h;
    }

    public function potencia()
    {
        $ep = $this->energiaPotencial();
        return ($ep === null) ? null : $ep / self::T_IMPULSO;
    }

    # El indicador de la prueba: ms, m/s^2 o W.
    public function indicador()
    {
        if ($this->prueba === 'reaccion') {
            return $this->tiempoReaccionMs();
        }
        if ($this->prueba === 'sprint') {
            return $this->aceleracion();
        }
        return $this->potencia();
    }

    # El indicador listo para mostrar: el tiempo en ms enteros, la
    # aceleracion con dos decimales, la potencia en W enteros (como en
    # las tablas de Fisica). Coma decimal, como se escribe en castellano.
    public static function formatear($prueba, $valor)
    {
        if ($valor === null) {
            return '—';
        }
        $decimales = ($prueba === 'sprint') ? 2 : 0;
        return number_format($valor, $decimales, ',', '.');
    }

    # Si $a es mejor marca que $b en esa prueba: en la reaccion, el
    # tiempo menor; en las otras, el valor mayor.
    public static function esMejor($prueba, $a, $b)
    {
        return ($prueba === 'reaccion') ? $a < $b : $a > $b;
    }

    public function validar()
    {
        $errores = array();
        if (!array_key_exists($this->prueba, self::pruebas())) {
            $errores[] = 'La prueba no es una de las previstas.';
            return $errores;
        }
        # Los mismos rangos que ck_med_datos.
        if ($this->prueba === 'reaccion') {
            if ($this->distancia_cm === null || $this->distancia_cm < 0.5 || $this->distancia_cm > 30) {
                $errores[] = 'La regla mide 30 cm: la distancia va de 0,5 a 30.';
            }
        } else {
            $minimo = ($this->prueba === 'sprint') ? 1.0 : 0.1;
            $maximo = ($this->prueba === 'sprint') ? 6.0 : 1.0;
            if ($this->tiempo_s === null || $this->tiempo_s < $minimo || $this->tiempo_s > $maximo) {
                $errores[] = ($this->prueba === 'sprint')
                           ? 'El tiempo en 10 m va de 1 a 6 segundos.'
                           : 'El tiempo de vuelo va de 0,1 a 1 segundo.';
            }
            if ($this->masa_kg === null || $this->masa_kg < 20 || $this->masa_kg > 200) {
                $errores[] = 'La masa va de 20 a 200 kg.';
            }
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$this->fecha, $p)
            || !checkdate((int)$p[2], (int)$p[3], (int)$p[1])) {
            $errores[] = 'La fecha de la medición no es una fecha válida.';
        }
        return $errores;
    }

    #endregion
}
