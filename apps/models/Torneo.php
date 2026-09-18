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

    # Baja logica del torneo, la alternativa al borrado que la base no
    # permite cuando ya hay inscriptos.
    public function cancelar()
    {
        $this->estado = 'cancelado';
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

        if (empty($this->fecha_inicio)) {
            $errores[] = 'La fecha de inicio es obligatoria.';
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

    #endregion
}
