<?php
# =====================================================================
# Modelo: Enfrentamiento   ->   tabla "enfrentamiento" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Composicion: contiene dos objetos Participante (local y visitante) y,
# si ya se jugo, un objeto Resultado.
#
# El visitante puede quedar en null: es el caso del "libre" (bye),
# cuando la cantidad de participantes es impar. En la tabla la clave
# foranea del visitante admite NULL justamente por eso.
# =====================================================================

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
            }
        }

        if (empty($errores)) {
            $this->resultado = $resultado;
            $this->estado    = 'jugado';
        }
        return $errores;
    }

    public function validar()
    {
        $errores = array();

        if ($this->numero < 1) {
            $errores[] = 'El numero de enfrentamiento arranca en 1.';
        }

        # Misma regla que ck_enfr_distintos.
        if (!$this->esLibre() && $this->visitante === $this->local) {
            $errores[] = 'Un participante no puede enfrentarse a si mismo.';
        }
        if ($this->visitante !== null && !($this->visitante instanceof Participante)) {
            $errores[] = 'El visitante tiene que ser un objeto Participante o quedar vacio.';
        }

        # Los dos tienen que estar en competencia, no dados de baja.
        if (!$this->local->estaEnCompetencia()) {
            $errores[] = 'El participante local no esta en competencia.';
        }
        if (!$this->esLibre() && !$this->visitante->estaEnCompetencia()) {
            $errores[] = 'El participante visitante no esta en competencia.';
        }

        $estados = array('programado', 'en_vivo', 'jugado', 'suspendido', 'anulado');
        if (!in_array($this->estado, $estados)) {
            $errores[] = 'El estado del enfrentamiento no es uno de los previstos.';
        }

        if (!empty($this->lugar) && strlen($this->lugar) > 80) {
            $errores[] = 'El lugar no puede pasar de 80 caracteres.';
        }

        return $errores;
    }

    #endregion
}
