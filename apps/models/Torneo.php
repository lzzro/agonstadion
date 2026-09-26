<?php
# =====================================================================
# Modelo: Torneo   ->   tabla "torneo" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La clase mas compuesta del modelo: contiene una Disciplina, un
# TipoTorneo, un ModuloCompetencia, el Usuario organizador, su
# ConfiguracionTorneo (relacion 1:1) y los arreglos de Participante y
# de Ronda.
#
# Un torneo con inscriptos no se borra, se cancela: es la misma
# decision que las acciones referenciales de la tabla, donde
# participante retiene al torneo con ON DELETE RESTRICT.
#
# Una liga (fase 2 del motor de torneos) nace con la inscripcion abierta
# y un cupo de 4 a 32 equipos (validarLiga). La fecha de inicio es
# opcional: puede quedar a definir. Cerrar la inscripcion la pone en
# curso, y pide al menos 4 inscriptos, los mismos que necesita el
# fixture (ver Fixture.php): con menos, la liga quedaria en curso sin
# poder armar su calendario.
# =====================================================================

require_once __DIR__ . '/Disciplina.php';
require_once __DIR__ . '/TipoTorneo.php';
require_once __DIR__ . '/ModuloCompetencia.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/ConfiguracionTorneo.php';
require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Ronda.php';

class Torneo
{
    #region ATRIBUTOS
    private $id_torneo;
    private $nombre;
    private $disciplina;        # objeto Disciplina
    private $tipo_torneo;       # objeto TipoTorneo
    private $modulo;            # objeto ModuloCompetencia
    private $organizador;       # objeto Usuario
    private $fecha_inicio;
    private $fecha_fin;
    private $max_participantes;
    private $sede;
    private $estado;
    private $fecha_creacion;
    private $configuracion;     # objeto ConfiguracionTorneo o null
    private $participantes;     # arreglo de objetos Participante
    private $rondas;            # arreglo de objetos Ronda
    #endregion

    #region FUNCIONES

    public function __construct($id_torneo, $nombre, Disciplina $disciplina,
                                TipoTorneo $tipo_torneo, ModuloCompetencia $modulo,
                                Usuario $organizador, $fecha_inicio, $fecha_fin = null,
                                $max_participantes = 16, $sede = null,
                                $estado = 'borrador', $fecha_creacion = null)
    {
        $this->id_torneo         = $id_torneo;
        $this->nombre            = $nombre;
        $this->disciplina        = $disciplina;
        $this->tipo_torneo       = $tipo_torneo;
        $this->modulo            = $modulo;
        $this->organizador       = $organizador;
        $this->fecha_inicio      = $fecha_inicio;
        $this->fecha_fin         = $fecha_fin;
        $this->max_participantes = (int)$max_participantes;
        $this->sede              = $sede;
        $this->estado            = $estado;
        $this->fecha_creacion    = $fecha_creacion;
        $this->configuracion     = null;
        $this->participantes     = array();
        $this->rondas            = array();
    }

    public function getIdTorneo()         { return $this->id_torneo; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdTorneo($id_torneo)
    {
        $this->id_torneo = (int)$id_torneo;
    }

    public function getNombre()           { return $this->nombre; }
    public function getDisciplina()       { return $this->disciplina; }
    public function getTipoTorneo()       { return $this->tipo_torneo; }
    public function getModulo()           { return $this->modulo; }
    public function getOrganizador()      { return $this->organizador; }
    public function getFechaInicio()      { return $this->fecha_inicio; }
    public function getFechaFin()         { return $this->fecha_fin; }
    public function getMaxParticipantes() { return $this->max_participantes; }
    public function getSede()             { return $this->sede; }
    public function getEstado()           { return $this->estado; }
    public function getFechaCreacion()    { return $this->fecha_creacion; }
    public function getConfiguracion()    { return $this->configuracion; }
    public function getParticipantes()    { return $this->participantes; }
    public function getRondas()           { return $this->rondas; }

    public function getCantidadParticipantes()
    {
        return count($this->participantes);
    }

    public function getCantidadRondas()
    {
        return count($this->rondas);
    }

    public function estaEnCurso()
    {
        return $this->estado === 'en_curso';
    }

    public function estaCancelado()
    {
        return $this->estado === 'cancelado';
    }

    public function tieneInscripcionAbierta()
    {
        return $this->estado === 'inscripcion';
    }

    public function esLiga()
    {
        return $this->modulo->getNombre() === 'Liga';
    }

    # De muestra: lo organiza una de las cuentas de muestra de la
    # migracion 005. Las paginas lo marcan en pantalla.
    public function esDeMuestra()
    {
        return $this->organizador->esDeMuestra();
    }

    # --- Transiciones de estado ---
    # Las reglas de que estado puede pasar a cual son del dominio, asi
    # que viven en el modelo y no en el controlador. Cada una devuelve
    # un arreglo de errores, vacio si la transicion se pudo hacer.
    # El recorrido normal es:
    #   borrador -> inscripcion -> en_curso -> finalizado
    # y desde cualquier punto se puede cancelar.

    # Baja logica del torneo, la alternativa al borrado que la base no
    # permite cuando ya hay inscriptos.
    public function cancelar()
    {
        $this->estado = 'cancelado';
    }

    # De 'borrador' a 'inscripcion': publica el torneo para que se
    # puedan anotar. Pide la configuracion asignada, porque sin los
    # puntajes no hay con que armar la tabla de posiciones.
    public function publicar()
    {
        $errores = array();

        if ($this->estado !== 'borrador') {
            $errores[] = 'Solo se puede publicar un torneo que este en borrador.';
        }
        if ($this->configuracion === null) {
            $errores[] = 'El torneo necesita su configuracion antes de publicarse.';
        }

        if (empty($errores)) {
            $this->estado = 'inscripcion';
        }
        return $errores;
    }

    # De 'inscripcion' a 'en_curso': cierra las altas y arranca la
    # competencia. Con menos de dos participantes no hay torneo.
    public function comenzar()
    {
        $errores = array();

        if ($this->estado !== 'inscripcion') {
            $errores[] = 'Solo se puede comenzar un torneo que este en inscripcion.';
        }
        if ($this->getCantidadParticipantes() < 2) {
            $errores[] = 'El torneo necesita al menos 2 participantes para comenzar.';
        }

        if (empty($errores)) {
            $this->estado = 'en_curso';
        }
        return $errores;
    }

    # De 'inscripcion' a 'en_curso' en una liga: cierra la inscripcion.
    # Pide 4 inscriptos, el minimo del fixture. $cantidad es la cuenta de
    # la base (el repositorio la hace dentro de la transaccion); si no
    # viene, vale la de los participantes cargados.
    public function cerrarInscripcion($cantidad = null)
    {
        $errores  = array();
        $cantidad = ($cantidad === null) ? $this->getCantidadParticipantes() : (int)$cantidad;

        if ($this->estado !== 'inscripcion') {
            $errores[] = 'La inscripción de esta liga ya está cerrada.';
        } elseif ($cantidad < 4) {
            $errores[] = 'Con menos de 4 equipos la inscripción sigue abierta.';
        }

        if (empty($errores)) {
            $this->estado = 'en_curso';
        }
        return $errores;
    }

    # De 'en_curso' a 'finalizado'.
    public function finalizar()
    {
        $errores = array();

        if ($this->estado !== 'en_curso') {
            $errores[] = 'Solo se puede finalizar un torneo que este en curso.';
        }

        if (empty($errores)) {
            $this->estado = 'finalizado';
        }
        return $errores;
    }

    public function asignarConfiguracion(ConfiguracionTorneo $configuracion)
    {
        $this->configuracion = $configuracion;
    }

    # Solo el estado 'inscripcion' admite altas: un torneo en borrador
    # todavia no se publico.
    public function admiteInscripciones()
    {
        return $this->estado === 'inscripcion'
               && $this->getCantidadParticipantes() < $this->max_participantes;
    }

    # Agrega una inscripcion controlando las tres reglas del modelo:
    # el participante es valido, el cupo alcanza, y el competidor
    # coincide con lo que pide el tipo de torneo.
    public function agregarParticipante(Participante $participante)
    {
        $errores = $participante->validar();

        if (!$this->admiteInscripciones()) {
            if ($this->getCantidadParticipantes() >= $this->max_participantes) {
                $errores[] = 'El torneo ya tiene sus ' . $this->max_participantes
                           . ' participantes.';
            } else {
                $errores[] = 'El torneo no esta aceptando inscripciones.';
            }
        }

        if ($this->tipo_torneo->competeEquipo() && !$participante->esEquipo()) {
            $errores[] = 'Este torneo es por equipos: la inscripcion tiene que ser de un equipo.';
        }
        if (!$this->tipo_torneo->competeEquipo() && !$participante->esIndividual()) {
            $errores[] = 'Este torneo es individual: la inscripcion tiene que ser de una persona.';
        }

        if ($this->yaEstaInscripto($participante)) {
            $errores[] = $participante->getNombreVisible() . ' ya esta inscripto en este torneo.';
        }

        if (empty($errores)) {
            $this->participantes[] = $participante;
        }
        return $errores;
    }

    # Equivale a las restricciones uq_part_usuario y uq_part_equipo.
    public function yaEstaInscripto(Participante $participante)
    {
        foreach ($this->participantes as $inscripto) {
            if ($participante->esEquipo() && $inscripto->esEquipo()
                && $inscripto->getEquipo()->getNombre() === $participante->getEquipo()->getNombre()) {
                return true;
            }
            if ($participante->esIndividual() && $inscripto->esIndividual()
                && $inscripto->getUsuario()->getCorreo() === $participante->getUsuario()->getCorreo()) {
                return true;
            }
        }
        return false;
    }

    public function agregarRonda(Ronda $ronda)
    {
        $errores = $ronda->validar();

        # Dos rondas no pueden llevar el mismo numero, igual que la
        # restriccion uq_ronda_num de la tabla.
        foreach ($this->rondas as $existente) {
            if ($existente->getNumero() === $ronda->getNumero()) {
                $errores[] = 'Ya existe la ronda numero ' . $ronda->getNumero() . '.';
            }
        }

        if (empty($errores)) {
            $this->rondas[] = $ronda;
        }
        return $errores;
    }

    public function validar()
    {
        $errores = array();

        if (empty($this->nombre) || mb_strlen($this->nombre) < 4 || mb_strlen($this->nombre) > 80) {
            $errores[] = 'El nombre del torneo tiene que tener entre 4 y 80 caracteres.';
        }

        if ($this->max_participantes < 2 || $this->max_participantes > 128) {
            $errores[] = 'El maximo de participantes tiene que estar entre 2 y 128.';
        }

        # La fecha de inicio es opcional, pero si viene tiene que ser una
        # fecha que exista, con la forma de la base (2026-10-04).
        if (!empty($this->fecha_inicio) && !self::fechaValida($this->fecha_inicio)) {
            $errores[] = 'La fecha de inicio no es una fecha válida.';
        }

        # Misma regla que ck_torneo_fechas.
        if (!empty($this->fecha_fin) && !empty($this->fecha_inicio)
            && $this->fecha_fin < $this->fecha_inicio) {
            $errores[] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        $estados = array('borrador', 'inscripcion', 'en_curso', 'finalizado', 'cancelado');
        if (!in_array($this->estado, $estados)) {
            $errores[] = 'El estado del torneo no es uno de los previstos.';
        }

        if (!empty($this->sede) && mb_strlen($this->sede) > 80) {
            $errores[] = 'La sede no puede pasar de 80 caracteres.';
        }

        return $errores;
    }

    # Las reglas propias de una liga, ademas de las de validar(): el
    # cupo va de 4 a 32 equipos (el formulario de crear.php dice lo
    # mismo), y la liga es por equipos.
    public function validarLiga()
    {
        $errores = $this->validar();
        if ($this->max_participantes < 4 || $this->max_participantes > 32) {
            $errores[] = 'El cupo de una liga va de 4 a 32 equipos.';
        }
        if (!$this->tipo_torneo->competeEquipo()) {
            $errores[] = 'Una liga es por equipos.';
        }
        return $errores;
    }

    # Una fecha AAAA-MM-DD que existe en el calendario (no 2026-02-30).
    public static function fechaValida($fecha)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$fecha, $partes)) {
            return false;
        }
        return checkdate((int)$partes[2], (int)$partes[3], (int)$partes[1]);
    }

    #endregion
}
