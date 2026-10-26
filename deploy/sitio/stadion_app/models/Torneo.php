<?php
# Modelo: Torneo -> tabla "torneo" de sql/schema.sql

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

    # Los otros dos formatos (fase 3): la llave y el suizo.
    public function esEliminacion()
    {
        return $this->modulo->getNombre() === 'Eliminación directa';
    }

    public function esSuizo()
    {
        return $this->modulo->getNombre() === 'Suizo';
    }

    # Como se llama cada ronda en pantalla: en una liga, "fecha"; en la llave y el suizo, "ronda".
    public function palabraRonda()
    {
        return $this->esLiga() ? 'fecha' : 'ronda';
    }

    public function validarConfiguracion(ConfiguracionTorneo $configuracion)
    {
        $errores = array();
        if ($this->esEliminacion() && $configuracion->admiteEmpate()) {
            $errores[] = 'En una eliminación directa ningún partido termina empatado.';
        }
        if (!$this->esLiga() && $configuracion->esIdaYVuelta()) {
            $errores[] = 'Solo una liga se juega de ida y vuelta.';
        }
        # Cuantos clasifican a playoffs (RF-14): marca a los primeros de la tabla de una liga.
        if (!$this->esLiga() && $configuracion->getClasificanPlayoffs() > 0) {
            $errores[] = 'Solo en una liga clasifican a playoffs.';
        } elseif ($configuracion->getClasificanPlayoffs() > (int)$this->max_participantes) {
            $errores[] = 'A los playoffs no clasifican más que el cupo.';
        }
        return $errores;
    }

    # De muestra: lo organiza una de las cuentas de muestra de la migracion 005.
    public function esDeMuestra()
    {
        return $this->organizador->esDeMuestra();
    }

    public function cancelar()
    {
        $errores = array();
        if ($this->estado === 'finalizado') {
            $errores[] = 'Una competencia terminada queda como historia: no se cancela.';
        } elseif ($this->estado === 'cancelado') {
            $errores[] = 'Esa competencia ya está cancelada.';
        }
        if (empty($errores)) {
            $this->estado = 'cancelado';
        }
        return $errores;
    }

    # De 'borrador' a 'inscripcion': publica el torneo para que se puedan anotar.
    public function publicar()
    {
        $errores = array();

        if ($this->estado !== 'borrador') {
            $errores[] = 'Solo se puede publicar un torneo que esté en borrador.';
        }
        if ($this->configuracion === null) {
            $errores[] = 'El torneo necesita su configuración antes de publicarse.';
        }

        if (empty($errores)) {
            $this->estado = 'inscripcion';
        }
        return $errores;
    }

    # De 'inscripcion' a 'en_curso': cierra las altas y arranca la competencia.
    public function comenzar()
    {
        $errores = array();

        if ($this->estado !== 'inscripcion') {
            $errores[] = 'Solo se puede comenzar un torneo que esté en inscripción.';
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
    public function cerrarInscripcion($cantidad = null)
    {
        $errores  = array();
        $cantidad = ($cantidad === null) ? $this->getCantidadParticipantes() : (int)$cantidad;

        if ($this->estado !== 'inscripcion') {
            $errores[] = 'La inscripción de esta competencia ya está cerrada.';
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
            $errores[] = 'Solo se puede finalizar un torneo que esté en curso.';
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

    # Solo el estado 'inscripcion' admite altas: un torneo en borrador todavia no se publico.
    public function admiteInscripciones()
    {
        return $this->estado === 'inscripcion'
               && $this->getCantidadParticipantes() < $this->max_participantes;
    }

    public function agregarParticipante(Participante $participante)
    {
        $errores = $participante->validar();

        if (!$this->admiteInscripciones()) {
            if ($this->getCantidadParticipantes() >= $this->max_participantes) {
                $errores[] = 'El torneo ya tiene sus ' . $this->max_participantes
                           . ' participantes.';
            } else {
                $errores[] = 'El torneo no está aceptando inscripciones.';
            }
        }

        if ($this->tipo_torneo->competeEquipo() && !$participante->esEquipo()) {
            $errores[] = 'Este torneo es por equipos: la inscripción tiene que ser de un equipo.';
        }
        if (!$this->tipo_torneo->competeEquipo() && !$participante->esIndividual()) {
            $errores[] = 'Este torneo es individual: la inscripción tiene que ser de una persona.';
        }

        if ($this->yaEstaInscripto($participante)) {
            $errores[] = $participante->getNombreVisible() . ' ya está inscripto en este torneo.';
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

        # Dos rondas no pueden llevar el mismo numero, igual que la restriccion uq_ronda_num de la tabla.
        foreach ($this->rondas as $existente) {
            if ($existente->getNumero() === $ronda->getNumero()) {
                $errores[] = 'Ya existe la ronda número ' . $ronda->getNumero() . '.';
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
            $errores[] = 'El máximo de participantes tiene que estar entre 2 y 128.';
        }

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

    public function validarLiga()
    {
        $errores = $this->validar();
        if ($this->max_participantes < 4 || $this->max_participantes > 32) {
            $errores[] = 'El cupo de una liga va de 4 a 32 participantes.';
        }
        return $errores;
    }

    # Si compiten equipos (o personas, en uno individual).
    public function esPorEquipos()
    {
        return $this->tipo_torneo->competeEquipo();
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
