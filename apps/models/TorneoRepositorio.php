<?php
# =====================================================================
# Modelo: TorneoRepositorio   ->   tablas "torneo" y "configuracion_torneo"
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Acceso a la base para los torneos:
#   - en que torneos compite una persona (la pestana "Mis torneos" del
#     perfil)
#   - los torneos publicos, para torneos.php, torneo.php y el inicio
#   - las ligas de un organizador, para su panel
#   - crear una liga y cerrar su inscripcion
#
# Una persona compite en un torneo de dos maneras, segun el tipo:
#   - individual: hay un participante con su id_usuario
#   - por equipos: hay un participante con el id de un equipo del que
#     la persona es integrante activa (tabla integrante_equipo)
# La consulta junta los dos casos. Las inscripciones dadas de baja no
# cuentan: quien se bajo de un torneo ya no esta en el.
#
# Publico es todo torneo con la inscripcion abierta, en curso o
# finalizado. Un borrador todavia no se publico, y uno cancelado ya no
# se muestra.
#
# Crear una liga son dos filas (torneo y su configuracion) y cerrar la
# inscripcion toca el torneo y los pedidos pendientes: cada una va en
# una transaccion, todo o nada. Cerrar bloquea antes la fila del torneo
# (SELECT ... FOR UPDATE), igual que aceptar un pedido y agregar un
# equipo: asi dos de esas acciones a la vez no se pisan.
#
# Siempre con sentencias preparadas.
# =====================================================================

require_once __DIR__ . '/Torneo.php';
require_once __DIR__ . '/Participante.php';
require_once __DIR__ . '/Equipo.php';
require_once __DIR__ . '/Usuario.php';

class TorneoRepositorio
{
    #region ATRIBUTOS
    private $conexion;      # objeto mysqli
    #endregion

    #region FUNCIONES

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    # --- Lectura compartida -------------------------------------------
    # Las columnas de un torneo, sus catalogos, su organizador y su
    # configuracion, con los nombres que espera construirTorneo(). Las
    # usan tambien los otros repositorios de las ligas.
    public static function columnas()
    {
        return 't.id_torneo, t.nombre, t.fecha_inicio, t.fecha_fin,
                t.max_participantes, t.sede, t.estado, t.fecha_creacion,
                d.id_disciplina, d.nombre AS disciplina,
                tt.id_tipo_torneo, tt.nombre AS tipo, tt.compite_equipo,
                m.id_modulo, m.nombre AS modulo,
                o.id_usuario AS id_organizador, o.nombre AS org_nombre,
                o.apellido AS org_apellido, o.fecha_alta AS org_alta,
                o.de_muestra AS org_muestra, o.foto_perfil AS org_foto,
                c.id_torneo AS id_config, c.puntos_victoria, c.puntos_empate,
                c.puntos_derrota, c.admite_empate, c.clasifican_playoffs,
                c.ida_y_vuelta, c.criterio_desempate, c.rondas_previstas, c.reglas';
    }

    public static function uniones()
    {
        return 'INNER JOIN disciplina d          ON d.id_disciplina = t.id_disciplina
                INNER JOIN tipo_torneo tt        ON tt.id_tipo_torneo = t.id_tipo_torneo
                INNER JOIN modulo_competencia m  ON m.id_modulo = t.id_modulo
                INNER JOIN usuario o             ON o.id_usuario = t.id_usuario_organizador
                LEFT JOIN configuracion_torneo c ON c.id_torneo = t.id_torneo';
    }

    # Arma el Torneo, con su configuracion si la tiene, a partir de una
    # fila con las columnas de columnas().
    public static function construirTorneo($fila)
    {
        $organizador = new Usuario($fila['id_organizador'], null, null,
                                   $fila['org_nombre'], $fila['org_apellido'], null, null, 1,
                                   $fila['org_alta'], $fila['org_foto'], null, $fila['org_muestra']);
        $torneo = new Torneo(
            $fila['id_torneo'],
            $fila['nombre'],
            new Disciplina($fila['id_disciplina'], $fila['disciplina']),
            new TipoTorneo($fila['id_tipo_torneo'], $fila['tipo'], $fila['compite_equipo']),
            new ModuloCompetencia($fila['id_modulo'], $fila['modulo']),
            $organizador,
            $fila['fecha_inicio'],
            $fila['fecha_fin'],
            $fila['max_participantes'],
            $fila['sede'],
            $fila['estado'],
            $fila['fecha_creacion']
        );
        if ($fila['id_config'] !== null) {
            $torneo->asignarConfiguracion(new ConfiguracionTorneo(
                $fila['id_config'], $fila['puntos_victoria'], $fila['puntos_empate'],
                $fila['puntos_derrota'], $fila['admite_empate'], $fila['clasifican_playoffs'],
                $fila['ida_y_vuelta'], $fila['rondas_previstas'], $fila['reglas'],
                $fila['criterio_desempate']));
        }
        return $torneo;
    }

    # --- Mis torneos (perfil) -----------------------------------------
    # Devuelve un arreglo de inscripciones, cada una con dos objetos:
    #   'torneo'       el Torneo, con su disciplina, tipo, modulo y
    #                  organizador
    #   'participante' como compite la persona en ese torneo (ella
    #                  sola, o su equipo)
    # No se usa agregarParticipante() de Torneo porque esa funcion es la
    # de una inscripcion nueva, y rechaza los torneos que ya empezaron.
    # Si la consulta no se puede hacer, devuelve null (distinto de un
    # arreglo vacio, que quiere decir "ningun torneo").
    public function buscarPorUsuario($id_usuario)
    {
        $sql = 'SELECT ' . self::columnas() . ',
                       p.id_participante, p.estado AS estado_participante,
                       p.fecha_inscripcion,
                       e.id_equipo, e.nombre AS equipo
                FROM participante p
                    INNER JOIN torneo t ON t.id_torneo = p.id_torneo
                    ' . self::uniones() . '
                    LEFT JOIN equipo e  ON e.id_equipo = p.id_equipo
                WHERE p.estado <> \'baja\'
                  AND (p.id_usuario = ?
                       OR p.id_equipo IN (SELECT ie.id_equipo
                                          FROM integrante_equipo ie
                                          WHERE ie.id_usuario = ? AND ie.activo = 1))
                ORDER BY t.fecha_inicio DESC, t.nombre';

        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }

        $id = (int)$id_usuario;
        $sentencia->bind_param('ii', $id, $id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $inscripciones = array();
        while ($fila = $resultado->fetch_assoc()) {
            $torneo = self::construirTorneo($fila);

            # Compite su equipo, o compite la persona sola.
            if ($fila['id_equipo'] !== null) {
                $participante = new Participante($fila['id_participante'], null,
                    new Equipo($fila['id_equipo'], $fila['equipo']),
                    $fila['estado_participante'], $fila['fecha_inscripcion']);
            } else {
                $participante = new Participante($fila['id_participante'],
                    new Usuario($id, null, null, null, null), null,
                    $fila['estado_participante'], $fila['fecha_inscripcion']);
            }

            $inscripciones[] = array('torneo' => $torneo, 'participante' => $participante);
        }
        $sentencia->close();

        return $inscripciones;
    }

    # --- Un torneo ----------------------------------------------------
    # El torneo con ese id, o null si no existe. Un borrador o un
    # cancelado tambien se devuelven: la pagina decide si los muestra.
    public function buscarPorId($id_torneo)
    {
        $lista = $this->listar('WHERE t.id_torneo = ?', 'i', array((int)$id_torneo), '');
        return empty($lista) ? null : $lista[0]['torneo'];
    }

    # El que muestra torneo.php sin numero, y al que apunta "Posiciones"
    # en el menu: primero uno con un partido en vivo, despues el que
    # esta en curso desde hace mas tiempo, despues uno con la inscripcion
    # abierta. null si no hay ningun torneo publico.
    public function idDestacado()
    {
        $sql = "SELECT t.id_torneo
                FROM torneo t
                WHERE t.estado IN ('inscripcion', 'en_curso', 'finalizado')
                ORDER BY EXISTS (SELECT 1 FROM enfrentamiento en
                                     INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                                 WHERE r.id_torneo = t.id_torneo AND en.estado = 'en_vivo') DESC,
                         CASE t.estado WHEN 'en_curso' THEN 0 WHEN 'inscripcion' THEN 1 ELSE 2 END,
                         t.fecha_inicio IS NULL, t.fecha_inicio, t.id_torneo
                LIMIT 1";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return ($fila === null) ? null : (int)$fila['id_torneo'];
    }

    # --- Listados -----------------------------------------------------
    # Los torneos publicos, con lo que muestran sus tarjetas. Primero los
    # que tienen un partido en vivo, despues los en curso, los que tienen
    # la inscripcion abierta y los finalizados. null si la consulta no se
    # puede hacer.
    public function listarPublicos()
    {
        return $this->listar("WHERE t.estado IN ('inscripcion', 'en_curso', 'finalizado')", '', array(),
                             "ORDER BY en_vivo DESC,
                                       CASE t.estado WHEN 'en_curso' THEN 0 WHEN 'inscripcion' THEN 1 ELSE 2 END,
                                       t.fecha_inicio IS NULL, t.fecha_inicio, t.nombre");
    }

    # Las ligas de un organizador, para su panel: todas menos las
    # canceladas, las mas nuevas primero.
    public function listarDeOrganizador($id_usuario)
    {
        return $this->listar("WHERE t.id_usuario_organizador = ? AND t.estado <> 'cancelado'",
                             'i', array((int)$id_usuario),
                             'ORDER BY t.fecha_creacion DESC, t.id_torneo DESC');
    }

    # Cuantos torneos publicos organiza una cuenta, para la tarjeta del
    # organizador de torneo.php.
    public function cantidadDeOrganizador($id_usuario)
    {
        $sql = "SELECT COUNT(*) AS cantidad FROM torneo
                WHERE id_usuario_organizador = ? AND estado IN ('inscripcion', 'en_curso', 'finalizado')";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id = (int)$id_usuario;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return (int)$fila['cantidad'];
    }

    # Si ya hay una liga vigente (no finalizada ni cancelada) con ese
    # nombre. La base no lo puede frenar sola: su UNIQUE es de nombre y
    # fecha de inicio, y la fecha puede quedar sin definir. Compara con
    # el cotejo de la tabla, que no distingue mayusculas ni tildes.
    public function nombreEnUso($nombre)
    {
        $sql = "SELECT 1 FROM torneo
                WHERE nombre = ? AND estado NOT IN ('finalizado', 'cancelado') LIMIT 1";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return false;
        }
        $sentencia->bind_param('s', $nombre);
        $sentencia->execute();
        $hay = ($sentencia->get_result()->fetch_assoc() !== null);
        $sentencia->close();
        return $hay;
    }

    # --- Crear una liga -----------------------------------------------
    # Guarda el torneo con la inscripcion abierta y su configuracion, en
    # una transaccion: sin configuracion no hay con que armar la tabla,
    # asi que una no queda sin la otra. El organizador es el que trae el
    # objeto (el controlador pone siempre la cuenta de la sesion).
    # Devuelve un arreglo de errores, vacio si quedo creada; el id queda
    # en el objeto.
    public function crearLiga(Torneo $torneo, ConfiguracionTorneo $configuracion)
    {
        $errores = array_merge($torneo->validarLiga(), $configuracion->validar());
        if (!empty($errores)) {
            return $errores;
        }
        if ($this->nombreEnUso($torneo->getNombre())) {
            return array('Ya hay una liga en juego con ese nombre.');
        }

        $this->conexion->begin_transaction();

        $sql = "INSERT INTO torneo (nombre, id_disciplina, id_tipo_torneo, id_modulo,
                                    id_usuario_organizador, fecha_inicio, max_participantes, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'inscripcion')";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('La liga no se puede crear por ahora.');
        }
        $nombre      = $torneo->getNombre();
        $disciplina  = (int)$torneo->getDisciplina()->getIdDisciplina();
        $tipo        = (int)$torneo->getTipoTorneo()->getIdTipoTorneo();
        $modulo      = (int)$torneo->getModulo()->getIdModulo();
        $organizador = (int)$torneo->getOrganizador()->getIdUsuario();
        $inicio      = empty($torneo->getFechaInicio()) ? null : $torneo->getFechaInicio();
        $cupo        = (int)$torneo->getMaxParticipantes();
        $sentencia->bind_param('siiiisi', $nombre, $disciplina, $tipo, $modulo,
                               $organizador, $inicio, $cupo);
        if (!$sentencia->execute()) {
            $duplicado = ($sentencia->errno === 1062);
            $sentencia->close();
            $this->conexion->rollback();
            return array($duplicado ? 'Ya hay una liga con ese nombre y esa fecha de inicio.'
                                    : 'La liga no se puede crear por ahora.');
        }
        $torneo->setIdTorneo($this->conexion->insert_id);
        $sentencia->close();

        $sql = 'INSERT INTO configuracion_torneo (id_torneo, puntos_victoria, puntos_empate,
                                                  puntos_derrota, admite_empate, clasifican_playoffs,
                                                  ida_y_vuelta, criterio_desempate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            return array('La liga no se puede crear por ahora.');
        }
        $id        = (int)$torneo->getIdTorneo();
        $victoria  = $configuracion->getPuntosVictoria();
        $empate    = $configuracion->getPuntosEmpate();
        $derrota   = $configuracion->getPuntosDerrota();
        $admite    = $configuracion->getAdmiteEmpate();
        $playoffs  = $configuracion->getClasificanPlayoffs();
        $iv        = $configuracion->getIdaYVuelta();
        $criterio  = $configuracion->getCriterioDesempate();
        $sentencia->bind_param('iiiiiiis', $id, $victoria, $empate, $derrota,
                               $admite, $playoffs, $iv, $criterio);
        if (!$sentencia->execute()) {
            $sentencia->close();
            $this->conexion->rollback();
            return array('La liga no se puede crear por ahora.');
        }
        $sentencia->close();
        $configuracion->setIdTorneo($id);
        $torneo->asignarConfiguracion($configuracion);

        $this->conexion->commit();
        return array();
    }

    # --- Cerrar la inscripcion ----------------------------------------
    # La liga pasa a en curso (Torneo::cerrarInscripcion pone las reglas)
    # y los pedidos que quedaban pendientes se rechazan: ya no hay lugar
    # que dar. Todo en una transaccion, con la fila del torneo bloqueada.
    # Devuelve array('errores' => ..., 'rechazados' => cantidad).
    public function cerrarInscripcion(Torneo $torneo, Usuario $organizador)
    {
        $resultado = array('errores' => array(), 'rechazados' => 0);
        $id_torneo = (int)$torneo->getIdTorneo();
        $id_org    = (int)$organizador->getIdUsuario();

        $this->conexion->begin_transaction();

        $cantidad = $this->bloquearYContar($id_torneo, $id_org);
        if ($cantidad === null) {
            $this->conexion->rollback();
            $resultado['errores'][] = 'Esa liga no está a cargo de esta cuenta.';
            return $resultado;
        }

        # El estado leido con el bloqueo puesto, no el de antes.
        $actual = $this->buscarPorId($id_torneo);
        $errores = ($actual === null) ? array('Esa liga no existe.') : $actual->cerrarInscripcion($cantidad);
        if (!empty($errores)) {
            $this->conexion->rollback();
            $resultado['errores'] = $errores;
            return $resultado;
        }

        $sql = "UPDATE torneo SET estado = 'en_curso' WHERE id_torneo = ? AND estado = 'inscripcion'";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            $resultado['errores'][] = 'La inscripción no se puede cerrar por ahora.';
            return $resultado;
        }
        $sentencia->bind_param('i', $id_torneo);
        $bien  = $sentencia->execute();
        $filas = $sentencia->affected_rows;
        $sentencia->close();
        if (!$bien || $filas !== 1) {
            $this->conexion->rollback();
            $resultado['errores'][] = 'La inscripción de esta liga ya está cerrada.';
            return $resultado;
        }

        $sql = "UPDATE pedido_inscripcion
                   SET estado = 'rechazado', fecha_resolucion = NOW(), id_usuario_resuelve = ?
                 WHERE id_torneo = ? AND estado = 'pendiente'";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            $this->conexion->rollback();
            $resultado['errores'][] = 'La inscripción no se puede cerrar por ahora.';
            return $resultado;
        }
        $sentencia->bind_param('ii', $id_org, $id_torneo);
        if (!$sentencia->execute()) {
            $sentencia->close();
            $this->conexion->rollback();
            $resultado['errores'][] = 'La inscripción no se puede cerrar por ahora.';
            return $resultado;
        }
        $resultado['rechazados'] = $sentencia->affected_rows;
        $sentencia->close();

        $this->conexion->commit();
        $torneo->cerrarInscripcion($cantidad);
        return $resultado;
    }

    # Bloquea la fila del torneo hasta el final de la transaccion y
    # cuenta sus participantes en competencia. Devuelve null si el torneo
    # no existe o no lo organiza esa cuenta. Tiene que llamarse con una
    # transaccion abierta: el bloqueo dura lo que ella. La usan tambien
    # los otros repositorios de las ligas.
    public function bloquearYContar($id_torneo, $id_organizador)
    {
        $sql = 'SELECT id_torneo FROM torneo
                WHERE id_torneo = ? AND id_usuario_organizador = ? FOR UPDATE';
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        $id  = (int)$id_torneo;
        $org = (int)$id_organizador;
        $sentencia->bind_param('ii', $id, $org);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        if ($fila === null) {
            return null;
        }
        return $this->contarParticipantes($id);
    }

    # Participantes en competencia (sin las bajas).
    public function contarParticipantes($id_torneo)
    {
        $sql = "SELECT COUNT(*) AS cantidad FROM participante
                WHERE id_torneo = ? AND estado IN ('inscripto', 'confirmado')";
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return 0;
        }
        $id = (int)$id_torneo;
        $sentencia->bind_param('i', $id);
        $sentencia->execute();
        $fila = $sentencia->get_result()->fetch_assoc();
        $sentencia->close();
        return (int)$fila['cantidad'];
    }

    # --- Auxiliar ---------------------------------------------------
    # Una sola consulta para los listados: cambian el WHERE y el orden.
    # Cada elemento trae el Torneo y lo que muestran las tarjetas:
    #   'inscriptos'  participantes en competencia
    #   'fechas'      rondas del fixture (0 si todavia no hay)
    #   'cerradas'    rondas cerradas
    #   'actual'      numero de la primera ronda sin cerrar, o null
    #   'fin_actual'  el ultimo dia de esa ronda, o null
    #   'en_vivo'     si tiene un partido en vivo
    #   'pedidos'     pedidos de inscripcion pendientes
    #   'resultados'  partidos con resultado cargado
    private function listar($condicion, $tipos, $valores, $orden)
    {
        $sql = "SELECT " . self::columnas() . ",
                       (SELECT COUNT(*) FROM participante p
                         WHERE p.id_torneo = t.id_torneo
                           AND p.estado IN ('inscripto', 'confirmado'))            AS inscriptos,
                       (SELECT COUNT(*) FROM ronda r WHERE r.id_torneo = t.id_torneo) AS fechas,
                       (SELECT COUNT(*) FROM ronda r
                         WHERE r.id_torneo = t.id_torneo AND r.estado = 'cerrada')   AS cerradas,
                       (SELECT MIN(r.numero) FROM ronda r
                         WHERE r.id_torneo = t.id_torneo AND r.estado <> 'cerrada')  AS actual,
                       (SELECT r.fecha_fin FROM ronda r
                         WHERE r.id_torneo = t.id_torneo AND r.estado <> 'cerrada'
                         ORDER BY r.numero LIMIT 1)                                  AS fin_actual,
                       EXISTS (SELECT 1 FROM enfrentamiento en
                                   INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                               WHERE r.id_torneo = t.id_torneo
                                 AND en.estado = 'en_vivo')                         AS en_vivo,
                       (SELECT COUNT(*) FROM pedido_inscripcion pi
                         WHERE pi.id_torneo = t.id_torneo
                           AND pi.estado = 'pendiente')                             AS pedidos,
                       (SELECT COUNT(*) FROM resultado re
                           INNER JOIN enfrentamiento en ON en.id_enfrentamiento = re.id_enfrentamiento
                           INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                         WHERE r.id_torneo = t.id_torneo)                           AS resultados
                FROM torneo t
                    " . self::uniones() . "
                " . $condicion . " " . $orden;
        $sentencia = $this->conexion->prepare($sql);
        if ($sentencia === false) {
            return null;
        }
        if ($tipos !== '') {
            $sentencia->bind_param($tipos, ...$valores);
        }
        $sentencia->execute();
        $resultado = $sentencia->get_result();

        $lista = array();
        while ($fila = $resultado->fetch_assoc()) {
            $lista[] = array(
                'torneo'     => self::construirTorneo($fila),
                'inscriptos' => (int)$fila['inscriptos'],
                'fechas'     => (int)$fila['fechas'],
                'cerradas'   => (int)$fila['cerradas'],
                'actual'     => ($fila['actual'] === null) ? null : (int)$fila['actual'],
                'fin_actual' => $fila['fin_actual'],
                'en_vivo'    => ((int)$fila['en_vivo'] === 1),
                'pedidos'    => (int)$fila['pedidos'],
                'resultados' => (int)$fila['resultados']
            );
        }
        $sentencia->close();
        return $lista;
    }

    #endregion
}
