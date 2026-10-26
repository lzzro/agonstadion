<?php
# Modelo: Enfrentamiento -> tabla "enfrentamiento" de sql/schema.sql

require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Resultado.php';

class Enfrentamiento
{
    #region ATRIBUTOS
    private $id_enfrentamiento;
    private $numero;
    private $local;              # objeto Participante
    private $visitante;          # objeto Participante o null (libre)
    private $fecha_hora;
    private $lugar;
    private $estado;
    private $resultado;          # objeto Resultado o null
    #endregion

    #region FUNCIONES

    public function __construct($id_enfrentamiento, $numero, Participante $local,
                                $visitante = null, $fecha_hora = null, $lugar = null,
                                $estado = 'programado')
    {
        $this->id_enfrentamiento = $id_enfrentamiento;
        $this->numero            = (int)$numero;
        $this->local             = $local;
        $this->visitante         = $visitante;
        $this->fecha_hora        = $fecha_hora;
        $this->lugar             = $lugar;
        $this->estado            = $estado;
        $this->resultado         = null;
    }

    public function getIdEnfrentamiento() { return $this->id_enfrentamiento; }

    public function setIdEnfrentamiento($id_enfrentamiento)
    {
        $this->id_enfrentamiento = (int)$id_enfrentamiento;
    }

    public function getNumero()           { return $this->numero; }
    public function getLocal()            { return $this->local; }
    public function getVisitante()        { return $this->visitante; }
    public function getFechaHora()        { return $this->fecha_hora; }
    public function getLugar()            { return $this->lugar; }
    public function getEstado()           { return $this->estado; }
    public function getResultado()        { return $this->resultado; }

    # Sin rival: el local pasa de ronda sin jugar.
    public function esLibre()
    {
        return $this->visitante === null;
    }

    public function estaJugado()
    {
        return $this->estado === 'jugado';
    }

    public function estaEnVivo()
    {
        return $this->estado === 'en_vivo';
    }

    # Titulo para mostrar en el calendario, tipo "Titanes CS vs Vortex".
    public function getTitulo()
    {
        if ($this->esLibre()) {
            return $this->local->getNombreVisible() . ' (libre)';
        }
        return $this->local->getNombreVisible() . ' vs ' . $this->visitante->getNombreVisible();
    }

    public function cargarResultado(Resultado $resultado)
    {
        $this->resultado = $resultado;
    }

    # Carga el resultado y deja el enfrentamiento como jugado.
    public function asignarResultado(Resultado $resultado)
    {
        $errores = $resultado->validar();

        if ($this->esLibre()) {
            $errores[] = 'Un enfrentamiento libre no lleva resultado.';
        }

        # El ganador, si viene, tiene que ser uno de los dos que jugaron.
        $ganador = $resultado->getGanador();
        if ($ganador !== null && !$this->esLibre()) {
            $es_local     = ($ganador === $this->local);
            $es_visitante = ($ganador === $this->visitante);
            if (!$es_local && !$es_visitante) {
                $errores[] = 'El ganador tiene que ser uno de los dos participantes '
                           . 'del enfrentamiento.';
            } elseif (!$resultado->esWalkover() && !$resultado->esEmpate()) {
                # Sin walkover, gana el del marcador mas alto.
                $gana_local = ($resultado->getPuntajeLocal() > $resultado->getPuntajeVisitante());
                if ($gana_local !== $es_local) {
                    $errores[] = 'El ganador es el del marcador más alto.';
                }
            }
        }

        $estados = array('programado', 'en_vivo', 'jugado');
        if (!in_array($this->estado, $estados)) {
            $errores[] = 'Un enfrentamiento suspendido o anulado no lleva resultado.';
        }

        if (empty($errores)) {
            $this->resultado = $resultado;
            $this->estado    = 'jugado';
        }
        return $errores;
    }

    # El dia, la hora y el lugar.
    public function programar($fecha_hora, $lugar)
    {
        $errores = array();
        if ($this->esLibre()) {
            $errores[] = 'Un equipo libre no tiene partido que programar.';
        }
        if ($this->estaJugado()) {
            $errores[] = 'Un partido jugado no se reprograma.';
        }
        if ($fecha_hora !== null && !self::fechaHoraValida($fecha_hora)) {
            $errores[] = 'El día y la hora no tienen un formato válido.';
        }
        $lugar = ($lugar === null) ? '' : trim($lugar);
        if (mb_strlen($lugar) > 80) {
            $errores[] = 'El lugar no puede pasar de 80 caracteres.';
        }
        if (empty($errores)) {
            $this->fecha_hora = ($fecha_hora === null) ? null
                              : str_replace('T', ' ', substr($fecha_hora, 0, 16)) . ':00';
            $this->lugar      = ($lugar === '') ? null : $lugar;
        }
        return $errores;
    }

    # AAAA-MM-DD HH:MM, una fecha que existe (no el 31 de junio) y una hora del dia.
    public static function fechaHoraValida($texto)
    {
        if (!is_string($texto) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(:00)?$/', $texto, $p)) {
            return false;
        }
        return checkdate((int)$p[2], (int)$p[3], (int)$p[1])
            && (int)$p[1] >= 2000 && (int)$p[1] <= 2100
            && (int)$p[4] <= 23 && (int)$p[5] <= 59;
    }

    # Marca el partido en vivo mientras se juega, o lo vuelve a programado.
    public function cambiarEnVivo($en_vivo)
    {
        if ($this->esLibre()) {
            return array('Un equipo libre no juega en esa fecha.');
        }
        if ($this->estaJugado()) {
            return array('Un partido jugado ya no está en vivo.');
        }
        if ($this->estado !== 'programado' && $this->estado !== 'en_vivo') {
            return array('Ese partido no se juega.');
        }
        $this->estado = $en_vivo ? 'en_vivo' : 'programado';
        return array();
    }

    public function validar()
    {
        $errores = array();

        if ($this->numero < 1) {
            $errores[] = 'El número de enfrentamiento arranca en 1.';
        }

        # Misma regla que ck_enfr_distintos.
        if (!$this->esLibre() && $this->visitante === $this->local) {
            $errores[] = 'Un participante no puede enfrentarse a si mismo.';
        }
        if ($this->visitante !== null && !($this->visitante instanceof Participante)) {
            $errores[] = 'El visitante no es un participante válido.';
        }

        # Los dos tienen que estar en competencia, no dados de baja.
        if (!$this->local->estaEnCompetencia()) {
            $errores[] = 'El participante local no está en competencia.';
        }
        if (!$this->esLibre() && !$this->visitante->estaEnCompetencia()) {
            $errores[] = 'El participante visitante no está en competencia.';
        }

        $estados = array('programado', 'en_vivo', 'jugado', 'suspendido', 'anulado');
        if (!in_array($this->estado, $estados)) {
            $errores[] = 'El estado del enfrentamiento no es uno de los previstos.';
        }

        if (!empty($this->lugar) && mb_strlen($this->lugar) > 80) {
            $errores[] = 'El lugar no puede pasar de 80 caracteres.';
        }

        return $errores;
    }

    #endregion
}
