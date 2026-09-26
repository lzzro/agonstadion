<?php
# =====================================================================
# Modelo: ConfiguracionTorneo   ->   tabla "configuracion_torneo"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Relacion 1:1 con torneo: los parametros de puntaje y las reglas.
#
# Aca vive calcularPuntos(), que es la contracara de la decision del
# schema de NO guardar los puntos en tabla_posiciones. Los puntos se
# calculan siempre a partir de estos parametros, asi que si el
# organizador cambia el puntaje, la tabla de posiciones se acomoda sola.
#
# El criterio de desempate decide el orden entre dos con los mismos
# puntos: 'diferencia' mira primero la diferencia de tantos y despues
# los tantos a favor; 'favor', al reves. Si todo coincide, el orden es
# alfabetico, para que la tabla salga siempre igual: sin mirar
# mayusculas ni tildes (ver claveAlfabetica), como ordena la base.
# =====================================================================

class ConfiguracionTorneo
{
    #region ATRIBUTOS
    private $id_torneo;
    private $puntos_victoria;
    private $puntos_empate;
    private $puntos_derrota;
    private $admite_empate;
    private $clasifican_playoffs;
    private $ida_y_vuelta;
    private $criterio_desempate;   # 'diferencia' o 'favor'
    private $rondas_previstas;
    private $reglas;
    #endregion

    #region FUNCIONES

    public function __construct($id_torneo, $puntos_victoria = 3, $puntos_empate = 1,
                                $puntos_derrota = 0, $admite_empate = 1,
                                $clasifican_playoffs = 0, $ida_y_vuelta = 0,
                                $rondas_previstas = null, $reglas = null,
                                $criterio_desempate = 'diferencia')
    {
        $this->id_torneo           = $id_torneo;
        $this->puntos_victoria     = (int)$puntos_victoria;
        $this->puntos_empate       = (int)$puntos_empate;
        $this->puntos_derrota      = (int)$puntos_derrota;
        $this->admite_empate       = (int)$admite_empate;
        $this->clasifican_playoffs = (int)$clasifican_playoffs;
        $this->ida_y_vuelta        = (int)$ida_y_vuelta;
        $this->rondas_previstas    = ($rondas_previstas === null || $rondas_previstas === '')
                                     ? null : (int)$rondas_previstas;
        $this->reglas              = $reglas;
        $this->criterio_desempate  = $criterio_desempate;
    }

    public function getIdTorneo()          { return $this->id_torneo; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdTorneo($id_torneo)
    {
        $this->id_torneo = (int)$id_torneo;
    }

    public function getPuntosVictoria()    { return $this->puntos_victoria; }
    public function getPuntosEmpate()      { return $this->puntos_empate; }
    public function getPuntosDerrota()     { return $this->puntos_derrota; }
    public function getAdmiteEmpate()      { return $this->admite_empate; }
    public function getClasificanPlayoffs(){ return $this->clasifican_playoffs; }
    public function getIdaYVuelta()        { return $this->ida_y_vuelta; }
    public function getRondasPrevistas()   { return $this->rondas_previstas; }
    public function getReglas()            { return $this->reglas; }
    public function getCriterioDesempate() { return $this->criterio_desempate; }

    # Los criterios de desempate, con su nombre para el formulario de
    # crear.php. La clave es lo que se guarda en la base.
    public static function criterios()
    {
        return array(
            'diferencia' => 'Diferencia de tantos; si persiste, tantos a favor',
            'favor'      => 'Tantos a favor; si persiste, diferencia de tantos'
        );
    }

    # El criterio contado en una frase, con la unidad de la disciplina
    # ("mapas", "goles", "tantos"): lo que muestra la pestana Reglas.
    public function textoDesempate($unidad)
    {
        if ($this->criterio_desempate === 'favor') {
            return 'Primero los ' . $unidad . ' a favor; si persiste, la diferencia de '
                 . $unidad . ' (a favor menos en contra). Si todo coincide, el orden alfabético.';
        }
        return 'Primero la diferencia de ' . $unidad . ' (a favor menos en contra); si persiste, los '
             . $unidad . ' a favor. Si todo coincide, el orden alfabético.';
    }

    public function admiteEmpate()
    {
        return $this->admite_empate === 1;
    }

    public function esIdaYVuelta()
    {
        return $this->ida_y_vuelta === 1;
    }

    # Dato derivado: no se guarda en la base, se calcula.
    public function calcularPuntos($ganados, $empatados, $perdidos)
    {
        return ((int)$ganados   * $this->puntos_victoria)
             + ((int)$empatados * $this->puntos_empate)
             + ((int)$perdidos  * $this->puntos_derrota);
    }

    # Cuantos enfrentamientos genera una liga con esta configuracion.
    # Es el numero que muestra la pantalla de creacion de torneo.
    public function enfrentamientosDeLiga($cantidad_participantes)
    {
        $cantidad = (int)$cantidad_participantes;
        if ($cantidad < 2) {
            return 0;
        }
        $partidos = ($cantidad * ($cantidad - 1)) / 2;
        if ($this->esIdaYVuelta()) {
            $partidos = $partidos * 2;
        }
        return (int)$partidos;
    }

    # Cuantas fechas tiene una liga de esa cantidad de participantes: con
    # un numero par, uno menos (cada uno juega en todas); con uno impar,
    # tantas como participantes (en cada fecha uno queda libre). El doble
    # si es de ida y vuelta.
    public function rondasDeLiga($cantidad_participantes)
    {
        $cantidad = (int)$cantidad_participantes;
        if ($cantidad < 2) {
            return 0;
        }
        $rondas = ($cantidad % 2 === 0) ? $cantidad - 1 : $cantidad;
        if ($this->esIdaYVuelta()) {
            $rondas = $rondas * 2;
        }
        return $rondas;
    }

    # Ordena un arreglo de objetos PosicionTabla como la tabla de la
    # pantalla: por puntos, y entre iguales segun el criterio de
    # desempate (ver la cabecera). Es el mismo orden de la consulta de
    # ejemplo del schema, y vive aca porque depende de estos puntajes. El
    # puesto de cada uno es su lugar en el arreglo devuelto.
    public function ordenarPosiciones($posiciones)
    {
        $ordenadas = $posiciones;
        $cantidad  = count($ordenadas);

        for ($i = 0; $i < $cantidad - 1; $i++) {
            for ($j = 0; $j < $cantidad - 1 - $i; $j++) {
                $actual    = $ordenadas[$j];
                $siguiente = $ordenadas[$j + 1];

                $puntos_actual    = $this->calcularPuntos($actual->getGanados(),
                                        $actual->getEmpatados(), $actual->getPerdidos());
                $puntos_siguiente = $this->calcularPuntos($siguiente->getGanados(),
                                        $siguiente->getEmpatados(), $siguiente->getPerdidos());

                # El primer y el segundo criterio, segun el desempate.
                if ($this->criterio_desempate === 'favor') {
                    $primero_actual    = $actual->getFavor();
                    $primero_siguiente = $siguiente->getFavor();
                    $segundo_actual    = $actual->getDiferencia();
                    $segundo_siguiente = $siguiente->getDiferencia();
                } else {
                    $primero_actual    = $actual->getDiferencia();
                    $primero_siguiente = $siguiente->getDiferencia();
                    $segundo_actual    = $actual->getFavor();
                    $segundo_siguiente = $siguiente->getFavor();
                }

                $va_despues = false;
                if ($puntos_actual < $puntos_siguiente) {
                    $va_despues = true;
                } elseif ($puntos_actual === $puntos_siguiente) {
                    if ($primero_actual < $primero_siguiente) {
                        $va_despues = true;
                    } elseif ($primero_actual === $primero_siguiente) {
                        if ($segundo_actual < $segundo_siguiente) {
                            $va_despues = true;
                        } elseif ($segundo_actual === $segundo_siguiente
                                  && self::compararNombres($actual->getNombreVisible(), $siguiente->getNombreVisible()) > 0) {
                            $va_despues = true;
                        }
                    }
                }

                if ($va_despues) {
                    $ordenadas[$j]     = $siguiente;
                    $ordenadas[$j + 1] = $actual;
                }
            }
        }

        return $ordenadas;
    }

    # El nombre listo para ordenar: en minuscula y sin tildes ni otras
    # marcas, asi "Ómnibus" va con las o, "atlántida" antes que "Aurora" y
    # "São Paulo" antes que "Sur". Comparar los bytes tal cual (strcmp)
    # pone toda mayuscula antes de toda minuscula, y toda letra con marca
    # despues de la z. Se sigue a utf8mb4_unicode_ci, el cotejo de la base:
    # cada letra con marca va con su letra base (la ñ con la n, la ß como
    # ss, la œ como oe), y las que ese cotejo trata como letras propias
    # (æ, đ, ħ, ı, ł, ŋ, ø, ŧ, þ) van despues de todas las de su letra
    # base: se les pone "~", que en la comparacion va despues de la z.
    public static function claveAlfabetica($nombre)
    {
        $letras = array(
            'a' => 'áàâäãåāăą', 'c' => 'çćĉċč', 'd' => 'ď', 'e' => 'éèêëēĕėęě', 'g' => 'ĝğġģ',
            'h' => 'ĥ', 'i' => 'íìîïĩīĭįİ', 'j' => 'ĵ', 'k' => 'ķ', 'l' => 'ĺļľŀ', 'n' => 'ñńņňŉ',
            'o' => 'óòôöõōŏő', 'r' => 'ŕŗř', 's' => 'śŝşšș', 't' => 'ţťț', 'u' => 'úùûüũūŭůűų',
            'w' => 'ŵ', 'y' => 'ýÿŷ', 'z' => 'źżž',
            'a~' => 'æ', 'd~' => 'đ', 'd~~' => 'ð', 'h~' => 'ħ', 'i~' => 'ı', 'l~' => 'ł', 'n~' => 'ŋ',
            'o~' => 'ø', 't~' => 'ŧ', 'z~' => 'þ');
        # La İ en minuscula es una i con un punto combinado aparte: el
        # punto se saca.
        $cambios = array('ß' => 'ss', 'œ' => 'oe', "\u{307}" => '');
        foreach ($letras as $base => $con_marca) {
            foreach (preg_split('//u', $con_marca, -1, PREG_SPLIT_NO_EMPTY) as $letra) {
                $cambios[$letra] = $base;
            }
        }
        return strtr(mb_strtolower((string)$nombre, 'UTF-8'), $cambios);
    }

    # Menor que 0 si $a va antes, mayor si va despues. Si las dos claves
    # coinciden ("Vortex" y "vortex"), decide el texto tal cual: el orden
    # nunca queda librado al azar.
    public static function compararNombres($a, $b)
    {
        $orden = strcmp(self::claveAlfabetica($a), self::claveAlfabetica($b));
        return ($orden !== 0) ? $orden : strcmp((string)$a, (string)$b);
    }

    public function validar()
    {
        $errores = array();

        # Misma regla que la restriccion ck_config_pts de la tabla.
        if ($this->puntos_victoria < $this->puntos_empate
            || $this->puntos_empate < $this->puntos_derrota) {
            $errores[] = 'Los puntos por victoria no pueden ser menores que los de '
                       . 'empate, ni los de empate menores que los de derrota.';
        }
        # Minimo 1, el mismo min="1" del formulario de crear.php: una
        # victoria que no suma puntos no distingue al que gana. Mismo
        # rango que la restriccion ck_config_victoria de la tabla.
        if ($this->puntos_victoria < 1 || $this->puntos_victoria > 10) {
            $errores[] = 'Los puntos por victoria tienen que estar entre 1 y 10.';
        }
        if ($this->puntos_empate < 0 || $this->puntos_empate > 10) {
            $errores[] = 'Los puntos por empate tienen que estar entre 0 y 10.';
        }
        if ($this->puntos_derrota < 0 || $this->puntos_derrota > 10) {
            $errores[] = 'Los puntos por derrota tienen que estar entre 0 y 10.';
        }
        # Misma lista que la restriccion ck_config_desempate.
        if (!array_key_exists($this->criterio_desempate, self::criterios())) {
            $errores[] = 'El criterio de desempate no es uno de los previstos.';
        }
        if ($this->admite_empate !== 0 && $this->admite_empate !== 1) {
            $errores[] = 'La opcion de empate solo admite si o no.';
        }
        if ($this->ida_y_vuelta !== 0 && $this->ida_y_vuelta !== 1) {
            $errores[] = 'La opcion de ida y vuelta solo admite si o no.';
        }
        if ($this->clasifican_playoffs < 0) {
            $errores[] = 'La cantidad que clasifica a playoffs no puede ser negativa.';
        }
        return $errores;
    }

    #endregion
}
