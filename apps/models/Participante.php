<?php
# =====================================================================
# Modelo: Participante   ->   tabla "participante" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Es la inscripcion de un competidor en un torneo. Por composicion
# contiene un Usuario (torneo individual) o un Equipo (torneo por
# equipos), nunca los dos y nunca ninguno: es la misma regla que la
# restriccion ck_part_competidor de la tabla.
#
# El resto del sistema (Enfrentamiento, Resultado, PosicionTabla)
# trabaja siempre contra Participante y no necesita saber cual de los
# dos casos es.
# =====================================================================

require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Equipo.php';

class Participante
{
    #region ATRIBUTOS
    private $id_participante;
    private $usuario;            # objeto Usuario o null
    private $equipo;             # objeto Equipo o null
    private $estado;
    private $fecha_inscripcion;
    #endregion

    #region FUNCIONES

    public function __construct($id_participante, $usuario = null, $equipo = null,
                                $estado = 'inscripto', $fecha_inscripcion = null)
    {
        $this->id_participante   = $id_participante;
        $this->usuario           = $usuario;
        $this->equipo            = $equipo;
        $this->estado            = $estado;
        $this->fecha_inscripcion = $fecha_inscripcion;
    }

    public function getIdParticipante()   { return $this->id_participante; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdParticipante($id_participante)
    {
        $this->id_participante = (int)$id_participante;
    }

    public function getUsuario()          { return $this->usuario; }
    public function getEquipo()           { return $this->equipo; }
    public function getEstado()           { return $this->estado; }
    public function getFechaInscripcion() { return $this->fecha_inscripcion; }

    public function esEquipo()
    {
        return $this->equipo !== null;
    }

    public function esIndividual()
    {
        return $this->usuario !== null;
    }

    # Equivale al COALESCE(equipo.nombre, CONCAT(usuario...)) de la
    # consulta de posiciones del schema.
    public function getNombreVisible()
    {
        if ($this->esEquipo()) {
            return $this->equipo->getNombre();
        }
        if ($this->esIndividual()) {
            return $this->usuario->getNombreCompleto();
        }
        return '';
    }

    public function estaEnCompetencia()
    {
        return $this->estado === 'inscripto' || $this->estado === 'confirmado';
    }

    # Baja del torneo: no se borra la fila, se marca, para no perder
    # los enfrentamientos ya jugados.
    public function darDeBaja()
    {
        $this->estado = 'baja';
    }

    public function validar()
    {
        $errores = array();

        # Un competidor y solo uno: la misma regla que ck_part_competidor.
        if ($this->usuario === null && $this->equipo === null) {
            $errores[] = 'El participante tiene que ser un usuario o un equipo.';
        }
        if ($this->usuario !== null && $this->equipo !== null) {
            $errores[] = 'El participante no puede ser un usuario y un equipo a la vez.';
        }
        if ($this->usuario !== null && !($this->usuario instanceof Usuario)) {
            $errores[] = 'El competidor individual tiene que ser un objeto Usuario.';
        }
        if ($this->equipo !== null && !($this->equipo instanceof Equipo)) {
            $errores[] = 'El competidor colectivo tiene que ser un objeto Equipo.';
        }

        $estados = array('inscripto', 'confirmado', 'baja', 'descalificado');
        if (!in_array($this->estado, $estados)) {
            $errores[] = 'El estado del participante no es uno de los previstos.';
        }

        return $errores;
    }

    #endregion
}
