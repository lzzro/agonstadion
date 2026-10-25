<?php
# =====================================================================
# Modelo: FixtureRepositorio   ->   tablas "ronda", "enfrentamiento",
#                                   "resultado" y "tabla_posiciones"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Guarda el fixture que arma Fixture.php y lee las fechas de una liga.
# Desde la fase 3 tambien la primera ronda de una eliminacion directa
# (Llave.php: las demas se arman al cerrar cada una) y la de un suizo
# (Suizo.php), con insertarRonda(), que usa tambien ResultadoRepositorio.
#
# Generar el fixture es todo o nada, en una transaccion y con la fila
# del torneo bloqueada:
#   1. la liga es de esta cuenta, tiene la inscripcion cerrada (esta en
#      curso) y 4 equipos o mas;
#   2. si ya tiene fixture: sin resultados cargados se borra (solo si se
#      pidio rehacerlo; el panel pide confirmarlo antes), y con
#      resultados no se toca nunca;
#   3. se guardan las fechas ("Fecha 1", "Fecha 2"...) y sus cruces, sin
#      dia ni hora: eso se carga despues;
#   4. cada equipo queda con su fila en la tabla de posiciones, en cero;
#   5. la configuracion anota cuantas fechas tiene la liga.
# Borrar una ronda borra sus enfrentamientos (ON DELETE CASCADE).
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Fixture.php';
require_once __DIR__ . '/Llave.php';
require_once __DIR__ . '/Suizo.php';
require_once __DIR__ . '/Ronda.php';
require_once __DIR__ . '/Enfrentamiento.php';
require_once __DIR__ . '/Resultado.php';
require_once __DIR__ . '/TorneoRepositorio.php';
require_once __DIR__ . '/ParticipanteRepositorio.php';

class FixtureRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # Genera y guarda el fixture. $rehacer dice si se confirmo borrar el
    # que ya hay. Devuelve array('errores' => ..., 'fechas' => N,
    # 'partidos' => N).
    public function generar(Torneo $torneo, Usuario $organizador, $rehacer)
    {
        $salida    = array('errores' => array(), 'fechas' => 0, 'partidos' => 0);
        $torneos   = new TorneoRepositorio($this->conexion);
        $inscritos = new ParticipanteRepositorio($this->conexion);
        $id_torneo = (int)$torneo->getIdTorneo();
        $id_org    = (int)$organizador->getIdUsuario();

        $this->conexion->begin_transaction();

        # 1. La liga, con el bloqueo puesto.
        $cantidad = $torneos->bloquearYContar($id_torneo, $id_org);
        if ($cantidad === null) {
            $this->conexion->rollback();
            $salida['errores'][] = 'Esa competencia no está a cargo de esta cuenta.';
            return $salida;
        }
        $actual = $torneos->buscarPorId($id_torneo);
        if ($actual === null || $actual->getConfiguracion() === null) {
            $this->conexion->rollback();
            $salida['errores'][] = 'Esa competencia no existe.';
            return $salida;
        }
        if (!$actual->estaEnCurso()) {
            $this->conexion->rollback();
            $salida['errores'][] = $actual->tieneInscripcionAbierta()
                                 ? 'El fixture se arma con la inscripción cerrada.'
                                 : 'Esa liga ya no admite un fixture nuevo.';
            return $salida;
        }

        # 2. El fixture que ya hubiera.
        $estado = $this->estado($id_torneo);
        if ($estado['fechas'] > 0) {
            if ($estado['resultados'] > 0) {
                $this->conexion->rollback();
                $salida['errores'][] = 'El fixture ya tiene resultados: no se rehace.';
                return $salida;
            }
            if (!$rehacer) {
                $this->conexion->rollback();
                $salida['errores'][] = 'La liga ya tiene fixture. Rehacerlo pide confirmación.';
                return $salida;
            }
            $sql = 'DELETE FROM ronda WHERE id_torneo = ?';
            $sentencia = $this->conexion->prepare($sql);
            if ($sentencia === false || !$sentencia->bind_param('i', $id_torneo) || !$sentencia->execute()) {
                $this->conexion->rollback();
                $salida['errores'][] = 'El fixture no se puede rehacer por ahora.';
                return $salida;
            }
            $sentencia->close();
        }

        # 3. Las fechas, con los equipos en el orden en que se inscribieron.
        $participantes = $inscritos->listarDeTorneo($id_torneo);
        if ($participantes === null) {
            $this->conexion->rollback();
            $salida['errores'][] = 'El fixture no se puede armar por ahora.';
            return $salida;
        }
        # Las rondas que se guardan ahora, segun el formato: la liga
        # entera, o la primera ronda de la llave o del suizo.
        $configuracion = $actual->getConfiguracion();
        $cantidad = count($participantes);
        $rondas   = array();   # cada una: array('nombre' => ..., 'cruces' => ...)
        if ($actual->esEliminacion()) {
            $llave   = new Llave($participantes);
            $errores = $llave->generar();
            $rondas[] = array('nombre' => Llave::nombreRonda(count($llave->getCruces())), 'cruces' => $llave->getCruces());
            $previstas = Llave::cantidadRondas($cantidad);
        } elseif ($actual->esSuizo()) {
            $errores = ($cantidad < 4) ? array('El suizo necesita al menos 4 participantes.') : array();
            $pedidas = $configuracion->getRondasPrevistas();
            if ($pedidas === null) {
                $pedidas = Suizo::rondasSugeridas($cantidad);
            }
            # Nunca mas rondas que las que admite la cantidad sin repetir rival.
            $previstas = min($pedidas, Suizo::rondasMaximas($cantidad));
            $rondas[] = array('nombre' => 'Ronda 1', 'cruces' => Suizo::primeraRonda($participantes));
        } else {
            $fixture = new Fixture($participantes, $configuracion->getIdaYVuelta());
            $errores = $fixture->generar();
            foreach ($fixture->getFechas() as $i => $cruces) {
                $rondas[] = array('nombre' => 'Fecha ' . ($i + 1), 'cruces' => $cruces);
            }
            $previstas = $fixture->getCantidadFechas();
        }
        if (!empty($errores)) {
            $this->conexion->rollback();
            $salida['errores'] = $errores;
            return $salida;
        }

        $partidos = 0;
        foreach ($rondas as $i => $ronda) {
            if (!$this->insertarRonda($id_torneo, $i + 1, $ronda['nombre'], $ronda['cruces'])) {
                $this->conexion->rollback();
                $salida['errores'][] = 'El fixture no se puede armar por ahora.';
                return $salida;
            }
            foreach ($ronda['cruces'] as $cruce) {
                if ($cruce['visitante'] !== null) { $partidos++; }
            }
        }

        # 4. La fila de cada equipo en la tabla, en cero, si no la tiene.
        $sql = "INSERT INTO tabla_posiciones (id_participante)
                SELECT p.id_participante FROM participante p
                    LEFT JOIN tabla_posiciones tp ON tp.id_participante = p.id_participante
                WHERE p.id_torneo = ? AND p.estado IN ('inscripto', 'confirmado')
                  AND tp.id_participante IS NULL";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false || !$sentencia->bind_param('i', $id_torneo) || !$sentencia->execute()) {
            $this->conexion->rollback();
            $salida['errores'][] = 'El fixture no se puede armar por ahora.';
            return $salida;
        }
        $sentencia->close();

        # 5. Cuantas fechas (o rondas) tiene la competencia.
        $fechas = $previstas;
        $sql = 'UPDATE configuracion_torneo SET rondas_previstas = ? WHERE id_torneo = ?';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false || !$sentencia->bind_param('ii', $fechas, $id_torneo) || !$sentencia->execute()) {
            $this->conexion->rollback();
            $salida['errores'][] = 'El fixture no se puede armar por ahora.';
            return $salida;
        }
        $sentencia->close();

        $this->conexion->commit();
        $salida['fechas']   = $fechas;
        $salida['partidos'] = $partidos;
        return $salida;
    }

    # Guarda una ronda con sus cruces, en el orden en que vienen (el
    # numero de cada cruce es su lugar: en la llave, ese orden decide
    # quien se cruza despues). Tiene que llamarse con una transaccion
    # abierta. true si quedo guardada.
    public function insertarRonda($id_torneo, $numero, $nombre, $cruces)
    {
        $id_torneo = (int)$id_torneo;
        $numero    = (int)$numero;
        $ronda_st = $this->conexion->prepare("INSERT INTO ronda (id_torneo, numero, nombre, estado) VALUES (?, ?, ?, 'pendiente')");
        $cruce_st = $this->conexion->prepare("INSERT INTO enfrentamiento (id_ronda, numero, id_participante_local,
                                                                         id_participante_visitante, estado)
                                              VALUES (?, ?, ?, ?, 'programado')");
        if ($ronda_st === false || $cruce_st === false
            || !$ronda_st->bind_param('iis', $id_torneo, $numero, $nombre) || !$ronda_st->execute()) {
            return false;
        }
        $id_ronda = (int)$this->conexion->insert_id;
        $ronda_st->close();
        $orden = 0;
        foreach ($cruces as $cruce) {
            $orden     = $orden + 1;
            $local     = (int)$cruce['local']->getIdParticipante();
            $visitante = ($cruce['visitante'] === null) ? null : (int)$cruce['visitante']->getIdParticipante();
            if (!$cruce_st->bind_param('iiii', $id_ronda, $orden, $local, $visitante) || !$cruce_st->execute()) {
                $cruce_st->close();
                return false;
            }
        }
        $cruce_st->close();
        return true;
    }

    # Cuantas fechas y cuantos resultados tiene una liga.
    public function estado($id_torneo)
    {
        $sql = 'SELECT (SELECT COUNT(*) FROM ronda r WHERE r.id_torneo = ?) AS fechas,
                       (SELECT COUNT(*) FROM resultado re
                            INNER JOIN enfrentamiento en ON en.id_enfrentamiento = re.id_enfrentamiento
                            INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                        WHERE r.id_torneo = ?) AS resultados';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return array('fechas' => 0, 'resultados' => 0);
        }
        $id = (int)$id_torneo;
        $sentencia->bind_param('ii', $id, $id);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return array('fechas' => (int)$fila['fechas'], 'resultados' => (int)$fila['resultados']);
    }

    # Las fechas de una liga, cada una con sus enfrentamientos y, si se
    # jugaron, su resultado. Dentro de cada fecha, por dia y hora (los
    # que no tienen, al final, en el orden del fixture). $participantes es el arreglo de
    # ParticipanteRepositorio::listarDeTorneo (con las bajas): los cruces
    # usan esos mismos objetos. null si la consulta no se puede hacer.
    public function fechasDe($id_torneo, $participantes)
    {
        $sql = 'SELECT r.id_ronda, r.numero AS ronda, r.nombre AS nombre_ronda, r.fecha_inicio,
                       r.fecha_fin, r.estado AS estado_ronda,
                       en.id_enfrentamiento, en.numero, en.id_participante_local,
                       en.id_participante_visitante, en.fecha_hora, en.lugar, en.estado,
                       re.puntaje_local, re.puntaje_visitante, re.id_participante_ganador,
                       re.walkover, re.fecha_carga
                FROM ronda r
                    LEFT JOIN enfrentamiento en ON en.id_ronda = r.id_ronda
                    LEFT JOIN resultado re      ON re.id_enfrentamiento = en.id_enfrentamiento
                WHERE r.id_torneo = ?
                ORDER BY r.numero, en.fecha_hora IS NULL, en.fecha_hora, en.numero';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$id_torneo;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $rondas = array();
        while ($fila = $resultado->fetch_assoc()) {
            $numero = (int)$fila['ronda'];
            if (!isset($rondas[$numero])) {
                $rondas[$numero] = new Ronda($fila['id_ronda'], $numero, $fila['nombre_ronda'],
                                             $fila['fecha_inicio'], $fila['fecha_fin'],
                                             $fila['estado_ronda']);
            }
            if ($fila['id_enfrentamiento'] === null
                || !isset($participantes[(int)$fila['id_participante_local']])) {
                continue;
            }
            $enfrentamiento = self::construirEnfrentamiento($fila, $participantes);
            if ($enfrentamiento !== null) {
                $rondas[$numero]->cargarEnfrentamiento($enfrentamiento);
            }
        }
        $sentencia->close();
        return array_values($rondas);
    }

    # Arma un Enfrentamiento, con su Resultado si tiene, a partir de una
    # fila. Los participantes salen del arreglo que se pasa. Lo usa
    # tambien el calendario del sitio.
    public static function construirEnfrentamiento($fila, $participantes)
    {
        $id_local = (int)$fila['id_participante_local'];
        if (!isset($participantes[$id_local])) {
            return null;
        }
        $local     = $participantes[$id_local];
        $visitante = null;
        if ($fila['id_participante_visitante'] !== null) {
            $id_visitante = (int)$fila['id_participante_visitante'];
            if (!isset($participantes[$id_visitante])) {
                return null;
            }
            $visitante = $participantes[$id_visitante];
        }
        $enfrentamiento = new Enfrentamiento($fila['id_enfrentamiento'], $fila['numero'],
                                             $local, $visitante, $fila['fecha_hora'],
                                             $fila['lugar'], $fila['estado']);
        if ($fila['puntaje_local'] !== null) {
            $ganador = null;
            if ($fila['id_participante_ganador'] !== null) {
                $id_ganador = (int)$fila['id_participante_ganador'];
                $ganador = ($id_ganador === $id_local) ? $local : $visitante;
            }
            $enfrentamiento->cargarResultado(new Resultado($fila['puntaje_local'],
                $fila['puntaje_visitante'], $ganador, $fila['walkover'], null, null,
                $fila['fecha_carga']));
        }
        return $enfrentamiento;
    }

    #endregion
}
