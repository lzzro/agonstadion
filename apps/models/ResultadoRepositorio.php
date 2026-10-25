<?php
# =====================================================================
# Modelo: ResultadoRepositorio   ->   tablas "resultado", "enfrentamiento",
#                                     "ronda", "tabla_posiciones" y "torneo"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Fase 3 del motor de torneos: lo que pasa en una liga despues del
# fixture (CU-15 Cargar resultado y CU-16 Publicar ronda; RF-21, RF-22 y
# RF-27).
#   - cargarResultado   el marcador de un partido, o un walkover; si ya
#                       tenia uno, lo corrige
#   - programar         el dia, la hora y el lugar de un partido
#   - cambiarEnVivo     marca un partido en vivo mientras se juega, o lo
#                       saca
#   - cerrarFecha       cierra la fecha en juego, con todos sus partidos
#                       jugados; la ultima cierra la liga con su campeon
#
# Las reglas (que marcador vale, que se puede cerrar) son de los modelos:
# ConfiguracionTorneo::erroresMarcador, Enfrentamiento::asignarResultado,
# programar y cambiarEnVivo, Ronda::cerrar y Torneo::finalizar. Aca se
# lee lo que hace falta, se les pregunta y se guarda.
#
# Cada accion va en una transaccion, con la fila del torneo bloqueada
# (TorneoRepositorio::bloquearYContar, SELECT ... FOR UPDATE), igual que
# las de la fase 2: dos cargas a la vez no se pisan, y la liga es de la
# cuenta que actua o no se hace nada.
#
# Las fechas se juegan en orden: los resultados se cargan solo en la
# fecha en juego (la primera sin cerrar), y cerrarla habilita la
# siguiente (RF-27). Un resultado se corrige mientras su fecha no se
# cierra. Programar se puede en cualquier fecha sin cerrar.
#
# La tabla de posiciones se vuelve a calcular entera desde los partidos
# jugados cada vez que se carga o se corrige un resultado
# (TablaPosiciones), y se guardan solo sus contadores (RF-22). Asi una
# correccion no deja restos del marcador anterior.
#
# Los playoffs (clasifican_playoffs > 0) quedan para cuando una liga
# pueda seguir en una llave: hoy "Nuevo torneo" guarda 0, y la liga
# termina con el primero de la tabla.
#
# Los otros dos formatos (tambien fase 3) se juegan igual, ronda por
# ronda, con una diferencia al cerrar: la ronda siguiente no existe
# todavia, y se arma en ese momento.
#   - eliminacion directa: con los que pasan de cada cruce, en el orden
#     de la llave (Llave.php); cerrada la final, su ganador es el
#     campeon. Un partido de la llave no termina empatado (la
#     configuracion no admite empate: ver Torneo::validarConfiguracion).
#   - suizo: con la tabla de ese momento, sin repetir cruces (Suizo.php),
#     hasta las rondas previstas; despues, el primero de la tabla. Ahi
#     quedar libre suma como un partido ganado.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Resultado.php';
require_once __DIR__ . '/TablaPosiciones.php';
require_once __DIR__ . '/TorneoRepositorio.php';
require_once __DIR__ . '/ParticipanteRepositorio.php';
require_once __DIR__ . '/FixtureRepositorio.php';
require_once __DIR__ . '/Llave.php';
require_once __DIR__ . '/Suizo.php';

class ResultadoRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # --- Cargar o corregir un resultado --------------------------------
    # $desenlace: 'marcador' (con $local y $visitante, numeros o null si
    # no llegaron), 'wo-local' o 'wo-visitante' (gana por walkover ese
    # lado, sin marcador). Devuelve array('errores' => ..., 'detalle' =>
    # lo que va a la auditoria, 'correccion' => si ya tenia resultado,
    # 'numero' => la fecha).
    public function cargarResultado($id_torneo, $id_enfrentamiento, Usuario $organizador,
                                    $local, $visitante, $desenlace)
    {
        $salida = array('errores' => array(), 'detalle' => '', 'correccion' => false, 'numero' => 0);

        $this->conexion->begin_transaction();
        $liga = $this->abrirLiga($id_torneo, $organizador);
        if (isset($liga['error'])) {
            return $this->frenar($salida, $liga['error']);
        }
        $lugar = self::buscarPartido($liga['rondas'], $id_enfrentamiento);
        if ($lugar === null) {
            return $this->frenar($salida, 'Ese partido no es de esta competencia.');
        }
        $ronda   = $lugar['ronda'];
        $partido = $lugar['partido'];
        $salida['numero'] = $ronda->getNumero();

        $error = self::fueraDeTurno($liga['torneo'], $liga['rondas'], $ronda);
        if ($error !== '') {
            return $this->frenar($salida, $error);
        }
        if ($partido->esLibre()) {
            return $this->frenar($salida, 'Un equipo libre no tiene resultado que cargar.');
        }

        # El resultado, segun como termina.
        $configuracion = $liga['torneo']->getConfiguracion();
        if ($desenlace === 'wo-local' || $desenlace === 'wo-visitante') {
            $walkover = 1;
            $local    = 0;
            $visitante = 0;
            $ganador  = ($desenlace === 'wo-local') ? $partido->getLocal() : $partido->getVisitante();
        } elseif ($desenlace === 'marcador') {
            if ($local === null || $visitante === null) {
                return $this->frenar($salida, 'El marcador lleva un número de 0 a '
                                    . ConfiguracionTorneo::MARCADOR_MAXIMO . ' de cada lado.');
            }
            $walkover  = 0;
            $local     = (int)$local;
            $visitante = (int)$visitante;
            $ganador   = null;
            if ($local > $visitante) {
                $ganador = $partido->getLocal();
            } elseif ($visitante > $local) {
                $ganador = $partido->getVisitante();
            }
        } else {
            return $this->frenar($salida, 'Ese resultado no es uno de los previstos.');
        }

        $errores = $configuracion->erroresMarcador($local, $visitante, $walkover === 1);
        $salida['correccion'] = ($partido->getResultado() !== null);
        if (empty($errores)) {
            $errores = $partido->asignarResultado(new Resultado($local, $visitante, $ganador,
                                                                $walkover, null, $organizador));
        }
        if (!empty($errores)) {
            $this->conexion->rollback();
            $salida['errores'] = $errores;
            return $salida;
        }

        # Guardar: el resultado (nuevo o corregido), el partido jugado, la
        # fecha en curso y la tabla.
        $id_partido    = (int)$partido->getIdEnfrentamiento();
        $id_ganador    = ($ganador === null) ? null : (int)$ganador->getIdParticipante();
        $id_carga      = (int)$organizador->getIdUsuario();
        if ($salida['correccion']) {
            $sql = 'UPDATE resultado SET puntaje_local = ?, puntaje_visitante = ?,
                                         id_participante_ganador = ?, walkover = ?,
                                         id_usuario_carga = ?, fecha_carga = NOW()
                    WHERE id_enfrentamiento = ?';
        } else {
            $sql = 'INSERT INTO resultado (puntaje_local, puntaje_visitante, id_participante_ganador,
                                           walkover, id_usuario_carga, id_enfrentamiento)
                    VALUES (?, ?, ?, ?, ?, ?)';
        }
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false
            || !$sentencia->bind_param('iiiiii', $local, $visitante, $id_ganador, $walkover, $id_carga, $id_partido)
            || !$sentencia->execute()) {
            return $this->frenar($salida, 'El resultado no se puede guardar por ahora.');
        }
        $sentencia->close();

        if (!$this->ejecutar("UPDATE enfrentamiento SET estado = 'jugado' WHERE id_enfrentamiento = ?",
                             'i', array($id_partido))
            || !$this->fechaEnCurso($ronda)
            || !$this->guardarTabla($liga)) {
            return $this->frenar($salida, 'El resultado no se puede guardar por ahora.');
        }

        $this->conexion->commit();

        $marcador = ($walkover === 1)
                  ? 'W.O. a favor de ' . $ganador->getNombreVisible()
                  : $partido->getLocal()->getNombreVisible() . ' ' . $local . '-' . $visitante . ' '
                    . $partido->getVisitante()->getNombreVisible();
        $salida['detalle'] = mb_substr($liga['torneo']->getNombre() . ' · ' . $ronda->getNombreVisible()
                                       . ' · ' . $marcador . ($salida['correccion'] ? ' · corrección' : ''), 0, 255);
        return $salida;
    }

    # --- Dia, hora y lugar -------------------------------------------
    # $fecha_hora: AAAA-MM-DD HH:MM o null (a confirmar); $lugar: texto,
    # vacio si no se sabe. Las fechas de la ronda pasan a ser el primer y
    # el ultimo dia de sus partidos. Devuelve array('errores' =>,
    # 'detalle' =>, 'numero' =>).
    public function programar($id_torneo, $id_enfrentamiento, Usuario $organizador, $fecha_hora, $lugar)
    {
        $salida = array('errores' => array(), 'detalle' => '', 'numero' => 0);

        $this->conexion->begin_transaction();
        $liga = $this->abrirLiga($id_torneo, $organizador);
        if (isset($liga['error'])) {
            return $this->frenar($salida, $liga['error']);
        }
        $encontrado = self::buscarPartido($liga['rondas'], $id_enfrentamiento);
        if ($encontrado === null) {
            return $this->frenar($salida, 'Ese partido no es de esta competencia.');
        }
        $ronda   = $encontrado['ronda'];
        $partido = $encontrado['partido'];
        $salida['numero'] = $ronda->getNumero();
        if ($ronda->estaCerrada()) {
            return $this->frenar($salida, ($liga['torneo']->esLiga() ? 'La ' . $ronda->getNombreVisible() : 'Esa ronda')
                                          . ' está cerrada: sus partidos quedan como están.');
        }

        $errores = $partido->programar($fecha_hora, $lugar);
        if (!empty($errores)) {
            $this->conexion->rollback();
            $salida['errores'] = $errores;
            return $salida;
        }

        $id_partido = (int)$partido->getIdEnfrentamiento();
        $id_ronda   = (int)$ronda->getIdRonda();
        $cuando     = $partido->getFechaHora();
        $donde      = $partido->getLugar();
        if (!$this->ejecutar('UPDATE enfrentamiento SET fecha_hora = ?, lugar = ? WHERE id_enfrentamiento = ?',
                             'ssi', array($cuando, $donde, $id_partido))
            || !$this->ejecutar('UPDATE ronda
                                    SET fecha_inicio = (SELECT DATE(MIN(e.fecha_hora)) FROM enfrentamiento e WHERE e.id_ronda = ?),
                                        fecha_fin    = (SELECT DATE(MAX(e.fecha_hora)) FROM enfrentamiento e WHERE e.id_ronda = ?)
                                  WHERE id_ronda = ?',
                                'iii', array($id_ronda, $id_ronda, $id_ronda))) {
            return $this->frenar($salida, 'El partido no se puede programar por ahora.');
        }

        $this->conexion->commit();
        $salida['detalle'] = mb_substr($liga['torneo']->getNombre() . ' · ' . $ronda->getNombreVisible() . ' · '
                                       . $partido->getTitulo() . ' · '
                                       . (($cuando === null) ? 'día a confirmar' : substr($cuando, 0, 16))
                                       . (($donde === null) ? '' : ' · ' . $donde), 0, 255);
        return $salida;
    }

    # --- En vivo -----------------------------------------------------
    # Solo en la fecha en juego. Devuelve array('errores' =>, 'detalle'
    # =>, 'numero' =>).
    public function cambiarEnVivo($id_torneo, $id_enfrentamiento, Usuario $organizador, $en_vivo)
    {
        $salida = array('errores' => array(), 'detalle' => '', 'numero' => 0);

        $this->conexion->begin_transaction();
        $liga = $this->abrirLiga($id_torneo, $organizador);
        if (isset($liga['error'])) {
            return $this->frenar($salida, $liga['error']);
        }
        $encontrado = self::buscarPartido($liga['rondas'], $id_enfrentamiento);
        if ($encontrado === null) {
            return $this->frenar($salida, 'Ese partido no es de esta competencia.');
        }
        $ronda   = $encontrado['ronda'];
        $partido = $encontrado['partido'];
        $salida['numero'] = $ronda->getNumero();

        $error = self::fueraDeTurno($liga['torneo'], $liga['rondas'], $ronda);
        if ($error !== '') {
            return $this->frenar($salida, $error);
        }
        $errores = $partido->cambiarEnVivo($en_vivo);
        if (!empty($errores)) {
            $this->conexion->rollback();
            $salida['errores'] = $errores;
            return $salida;
        }

        $estado = $partido->getEstado();
        $id_partido = (int)$partido->getIdEnfrentamiento();
        if (!$this->ejecutar('UPDATE enfrentamiento SET estado = ? WHERE id_enfrentamiento = ?',
                             'si', array($estado, $id_partido))
            || ($en_vivo && !$this->fechaEnCurso($ronda))) {
            return $this->frenar($salida, 'El partido no se puede cambiar por ahora.');
        }

        $this->conexion->commit();
        $salida['detalle'] = mb_substr($liga['torneo']->getNombre() . ' · ' . $ronda->getNombreVisible() . ' · '
                                       . $partido->getTitulo() . ($en_vivo ? ' · en vivo' : ' · fuera de vivo'), 0, 255);
        return $salida;
    }

    # --- Cerrar la fecha en juego --------------------------------------
    # Con todos sus partidos jugados (o libres). Si era la ultima, la
    # liga queda finalizada, con la fecha de fin de hoy (o la de inicio,
    # si todavia no llego) y su campeon: el primero de la tabla.
    # Devuelve array('errores' =>, 'numero' =>, 'id_ronda' =>, 'detalle'
    # =>, 'final' => si cerro la competencia, 'campeon' => su nombre,
    # 'siguiente' => el nombre de la ronda que se armo, en la llave y el
    # suizo).
    public function cerrarFecha($id_torneo, $id_ronda, Usuario $organizador)
    {
        $salida = array('errores' => array(), 'numero' => 0, 'id_ronda' => (int)$id_ronda,
                        'detalle' => '', 'final' => false, 'campeon' => '', 'siguiente' => '');

        $this->conexion->begin_transaction();
        $liga = $this->abrirLiga($id_torneo, $organizador);
        if (isset($liga['error'])) {
            return $this->frenar($salida, $liga['error']);
        }
        $ronda = null;
        foreach ($liga['rondas'] as $candidata) {
            if ((int)$candidata->getIdRonda() === (int)$id_ronda) {
                $ronda = $candidata;
            }
        }
        if ($ronda === null) {
            return $this->frenar($salida, 'Esa ronda no es de esta competencia.');
        }
        $salida['numero'] = $ronda->getNumero();
        if ($ronda->estaCerrada()) {
            return $this->frenar($salida, ($liga['torneo']->esLiga() ? 'La ' . $ronda->getNombreVisible() : 'Esa ronda') . ' ya está cerrada.');
        }
        $en_juego = self::fechaEnJuego($liga['rondas']);
        if ($en_juego !== $ronda) {
            return $this->frenar($salida, $liga['torneo']->esLiga()
                ? 'Las fechas se cierran en orden: primero la ' . $en_juego->getNombreVisible() . '.'
                : 'Las rondas se cierran en orden: falta cerrar ' . $en_juego->getNombreVisible() . '.');
        }
        $faltan = self::faltanResultados($ronda);
        if ($faltan > 0) {
            $falta = ($faltan === 1) ? 'falta 1 resultado.' : 'faltan ' . $faltan . ' resultados.';
            return $this->frenar($salida, $liga['torneo']->esLiga() ? 'A la ' . $ronda->getNombreVisible() . ' le ' . $falta
                                                                    : $ronda->getNombreVisible() . ': ' . $falta);
        }
        $errores = $ronda->cerrar();
        if (!empty($errores)) {
            $this->conexion->rollback();
            $salida['errores'] = $errores;
            return $salida;
        }

        $id = (int)$ronda->getIdRonda();
        $sentencia = $this->conexion->prepare("UPDATE ronda SET estado = 'cerrada' WHERE id_ronda = ? AND estado <> 'cerrada'");
        if ($sentencia === false || !$sentencia->bind_param('i', $id) || !$sentencia->execute()
            || $sentencia->affected_rows !== 1) {
            return $this->frenar($salida, 'La ronda no se puede cerrar por ahora.');
        }
        $sentencia->close();
        $salida['detalle'] = mb_substr($liga['torneo']->getNombre() . ' · ' . $ronda->getNombreVisible(), 0, 255);
        $torneo = $liga['torneo'];

        # En la llave y el suizo, la ronda siguiente se arma ahora.
        $siguiente = null;    # array('nombre' => ..., 'cruces' => ...)
        $campeon   = null;    # Participante, si ya se sabe
        if ($torneo->esEliminacion()) {
            $pasan = array();
            foreach ($ronda->getEnfrentamientos() as $partido) {
                $pasa = Llave::quienPasa($partido);
                if ($pasa === null) {
                    return $this->frenar($salida, 'La ronda no se puede cerrar por ahora.');
                }
                $pasan[] = $pasa;
            }
            if (count($pasan) > 1) {
                $siguiente = array('nombre' => Llave::nombreRonda(intdiv(count($pasan), 2)),
                                   'cruces' => Llave::siguienteRonda($pasan));
            } else {
                $campeon = $pasan[0];
            }
        } elseif ($torneo->esSuizo()
                  && $ronda->getNumero() < (int)$torneo->getConfiguracion()->getRondasPrevistas()) {
            $siguiente = array('nombre' => 'Ronda ' . ($ronda->getNumero() + 1),
                               'cruces' => $this->emparejarSuizo($liga));
        }
        if ($siguiente !== null) {
            if (empty($siguiente['cruces'])
                || !(new FixtureRepositorio($this->conexion))->insertarRonda($torneo->getIdTorneo(), $ronda->getNumero() + 1,
                                                                             $siguiente['nombre'], $siguiente['cruces'])) {
                return $this->frenar($salida, 'La ronda siguiente no se puede armar por ahora.');
            }
            $salida['siguiente'] = $siguiente['nombre'];
        }
        if (!$this->guardarTabla($liga)) {
            return $this->frenar($salida, 'La ronda no se puede cerrar por ahora.');
        }

        # La ultima: la competencia termina.
        if ($siguiente === null && self::fechaEnJuego($liga['rondas']) === null) {
            $errores = $torneo->finalizar();
            if (!empty($errores)) {
                $this->conexion->rollback();
                $salida['errores'] = $errores;
                return $salida;
            }
            $sql = "UPDATE torneo
                       SET estado = 'finalizado',
                           fecha_fin = CASE WHEN fecha_inicio IS NULL OR fecha_inicio <= CURDATE()
                                            THEN CURDATE() ELSE fecha_inicio END
                     WHERE id_torneo = ? AND estado = 'en_curso'";
            $id_t = (int)$torneo->getIdTorneo();
            $sentencia = $this->conexion->prepare($sql);
            if ($sentencia === false || !$sentencia->bind_param('i', $id_t) || !$sentencia->execute()
                || $sentencia->affected_rows !== 1) {
                return $this->frenar($salida, 'La competencia no se puede cerrar por ahora.');
            }
            $sentencia->close();

            # El campeon: el ganador de la final, o el primero de la tabla.
            if ($campeon === null) {
                $campeon = self::campeon($torneo, $liga['participantes'], $liga['rondas']);
            }
            $salida['final']   = true;
            $salida['campeon'] = ($campeon === null) ? '' : $campeon->getNombreVisible();
        }

        $this->conexion->commit();
        return $salida;
    }

    # --- Auxiliares (de lectura) -------------------------------------

    # La fecha en juego: la primera sin cerrar, o null si estan todas
    # cerradas.
    public static function fechaEnJuego($rondas)
    {
        foreach ($rondas as $ronda) {
            if (!$ronda->estaCerrada()) {
                return $ronda;
            }
        }
        return null;
    }

    # El campeon de una competencia terminada (un Participante, o null):
    # en la eliminacion directa, el que pasa de la final; en la liga y el
    # suizo, el primero de la tabla, calculada desde los partidos.
    public static function campeon(Torneo $torneo, $participantes, $rondas)
    {
        if ($torneo->esEliminacion()) {
            $ultima = empty($rondas) ? null : $rondas[count($rondas) - 1];
            if ($ultima === null || $ultima->getCantidadEnfrentamientos() !== 1) {
                return null;
            }
            $final = $ultima->getEnfrentamientos();
            return Llave::quienPasa($final[0]);
        }
        if ($torneo->getConfiguracion() === null) {
            return null;
        }
        $tabla = new TablaPosiciones($torneo->getConfiguracion(), $participantes, $torneo->esSuizo());
        $tabla->sumarRondas($rondas);
        $ordenadas = $tabla->getOrdenadas();
        return empty($ordenadas) ? null : $ordenadas[0]->getParticipante();
    }

    # Cuantos partidos de la fecha no estan jugados (un libre no cuenta).
    public static function faltanResultados(Ronda $ronda)
    {
        $faltan = 0;
        foreach ($ronda->getEnfrentamientos() as $partido) {
            if (!$partido->esLibre() && !$partido->estaJugado()) {
                $faltan++;
            }
        }
        return $faltan;
    }

    # El partido con ese id y su fecha, o null.
    private static function buscarPartido($rondas, $id_enfrentamiento)
    {
        foreach ($rondas as $ronda) {
            foreach ($ronda->getEnfrentamientos() as $partido) {
                if ((int)$partido->getIdEnfrentamiento() === (int)$id_enfrentamiento) {
                    return array('ronda' => $ronda, 'partido' => $partido);
                }
            }
        }
        return null;
    }

    # '' si la fecha (o ronda) es la que esta en juego; si no, el
    # porque. En la llave y el suizo los nombres de las rondas no llevan
    # articulo ("Cuartos de final"), asi que se nombran sin el.
    private static function fueraDeTurno(Torneo $torneo, $rondas, Ronda $ronda)
    {
        if ($ronda->estaCerrada()) {
            return ($torneo->esLiga() ? 'La ' . $ronda->getNombreVisible() : 'Esa ronda')
                 . ' está cerrada: sus resultados quedan como están.';
        }
        $en_juego = self::fechaEnJuego($rondas);
        if ($en_juego !== $ronda) {
            return $torneo->esLiga() ? 'Los resultados se cargan en la fecha en juego, la ' . $en_juego->getNombreVisible() . '.'
                                     : 'Los resultados se cargan en la ronda en juego: ' . $en_juego->getNombreVisible() . '.';
        }
        return '';
    }

    # Los cruces de la ronda siguiente de un suizo: la tabla de ahora
    # (con los libres sumados), sin los dados de baja, sin repetir
    # cruces, y libre el de mas abajo que todavia no lo estuvo.
    private function emparejarSuizo($liga)
    {
        $en_competencia = array();
        foreach ($liga['participantes'] as $id => $participante) {
            if ($participante->estaEnCompetencia()) {
                $en_competencia[$id] = $participante;
            }
        }
        $tabla = new TablaPosiciones($liga['torneo']->getConfiguracion(), $en_competencia, true);
        $tabla->sumarRondas($liga['rondas']);
        $ordenados = array();
        foreach ($tabla->getOrdenadas() as $fila) {
            $ordenados[] = $fila->getParticipante();
        }
        $cruzados = array();
        $libres   = array();
        foreach ($liga['rondas'] as $ronda) {
            foreach ($ronda->getEnfrentamientos() as $partido) {
                if ($partido->esLibre()) {
                    $libres[(int)$partido->getLocal()->getIdParticipante()] = true;
                } else {
                    $cruzados[Suizo::clave($partido->getLocal()->getIdParticipante(),
                                           $partido->getVisitante()->getIdParticipante())] = true;
                }
            }
        }
        return Suizo::emparejar($ordenados, $cruzados, $libres);
    }

    # --- Auxiliares (de escritura) -----------------------------------

    # Con la transaccion abierta: bloquea la liga, comprueba que sea de
    # esta cuenta y este en curso con su fixture, y lee sus participantes
    # (con las bajas, que figuran en lo que jugaron) y sus fechas.
    # Devuelve array('torneo', 'participantes', 'rondas') o
    # array('error' => texto).
    private function abrirLiga($id_torneo, Usuario $organizador)
    {
        $torneos = new TorneoRepositorio($this->conexion);
        if ($torneos->bloquearYContar($id_torneo, $organizador->getIdUsuario()) === null) {
            return array('error' => 'Esa competencia no está a cargo de esta cuenta.');
        }
        $torneo = $torneos->buscarPorId($id_torneo);
        if ($torneo === null || $torneo->getConfiguracion() === null) {
            return array('error' => 'Esa competencia no existe.');
        }
        if ($torneo->getEstado() === 'finalizado') {
            return array('error' => ($torneo->esLiga() ? 'La liga está finalizada' : 'La competencia está terminada')
                                    . ': sus partidos quedan como están.');
        }
        if ($torneo->estaCancelado()) {
            return array('error' => 'La competencia está cancelada.');
        }
        if (!$torneo->estaEnCurso()) {
            return array('error' => 'Con la inscripción abierta todavía no hay partidos.');
        }
        $participantes = (new ParticipanteRepositorio($this->conexion))->listarDeTorneo($id_torneo, true);
        $rondas = ($participantes === null) ? null
                : (new FixtureRepositorio($this->conexion))->fechasDe($id_torneo, $participantes);
        if ($rondas === null) {
            return array('error' => 'Los partidos no se pueden leer por ahora.');
        }
        if (empty($rondas)) {
            return array('error' => 'La liga todavía no tiene fixture.');
        }
        return array('torneo' => $torneo, 'participantes' => $participantes, 'rondas' => $rondas);
    }

    # La fecha pasa a en curso con su primer partido en vivo o jugado.
    private function fechaEnCurso(Ronda $ronda)
    {
        return $this->ejecutar("UPDATE ronda SET estado = 'en_curso' WHERE id_ronda = ? AND estado = 'pendiente'",
                               'i', array((int)$ronda->getIdRonda()));
    }

    # Vuelve a calcular la tabla desde los partidos jugados y guarda los
    # contadores de cada fila.
    private function guardarTabla($liga)
    {
        $tabla = new TablaPosiciones($liga['torneo']->getConfiguracion(), $liga['participantes'], $liga['torneo']->esSuizo());
        $tabla->sumarRondas($liga['rondas']);
        $sentencia = $this->conexion->prepare('UPDATE tabla_posiciones
                                                  SET ganados = ?, empatados = ?, perdidos = ?, favor = ?,
                                                      contra = ?, fecha_actualizacion = NOW()
                                                WHERE id_participante = ?');
        if ($sentencia === false) {
            return false;
        }
        foreach ($tabla->getPosiciones() as $id_participante => $fila) {
            $g = $fila->getGanados();
            $e = $fila->getEmpatados();
            $p = $fila->getPerdidos();
            $f = $fila->getFavor();
            $c = $fila->getContra();
            $id = (int)$id_participante;
            if (!$sentencia->bind_param('iiiiii', $g, $e, $p, $f, $c, $id) || !$sentencia->execute()) {
                $sentencia->close();
                return false;
            }
        }
        $sentencia->close();
        return true;
    }

    # Una sentencia preparada que no devuelve filas. true si anduvo.
    private function ejecutar($sql, $tipos, $valores)
    {
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return false;
        }
        $bien = $sentencia->bind_param($tipos, ...$valores) && $sentencia->execute();
        $sentencia->close();
        return $bien;
    }

    # Deshace la transaccion y devuelve la salida con ese error.
    private function frenar($salida, $error)
    {
        $this->conexion->rollback();
        $salida['errores'][] = $error;
        return $salida;
    }

    #endregion
}
