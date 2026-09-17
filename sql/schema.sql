-- =====================================================================
-- SGDM - Sistema de Gestion Deportiva Modular
-- Producto: Stadion (Agon) - Lucas Martiarena - 3.o MN - ITS Arias Balparda
-- Archivo: sql/schema.sql
-- Segunda entrega: modelo relacional normalizado (3FN) + DDL + DCL
--
-- Motor: MariaDB con InnoDB. Probado ejecutando este mismo archivo en
-- MariaDB 10.11, que es la version de la VM del instituto. Necesita
-- MariaDB 10.2 o superior, porque antes de esa version las
-- restricciones CHECK se aceptaban pero no se verificaban.
-- El script se puede correr varias veces seguidas: empieza borrando las
-- tablas en orden inverso al de creacion.
-- =====================================================================
--
-- MODELO RELACIONAL (resumen textual)
-- -------------------------------------------------------------------
-- rol (id_rol, nombre, descripcion)
-- usuario (id_usuario, correo, hash_password, nombre, apellido, alias,
--          presentacion, activo, fecha_alta)
-- usuario_rol (id_usuario*, id_rol*, fecha_asignacion)
-- equipo (id_equipo, nombre, ciudad, id_usuario_capitan, activo, fecha_alta)
-- integrante_equipo (id_equipo*, id_usuario*, dorsal, activo, fecha_alta)
-- disciplina (id_disciplina, nombre)
-- tipo_torneo (id_tipo_torneo, nombre, compite_equipo)
-- modulo_competencia (id_modulo, nombre, descripcion)
-- torneo (id_torneo, nombre, id_disciplina, id_tipo_torneo, id_modulo,
--         id_usuario_organizador, fecha_inicio, fecha_fin,
--         max_participantes, sede, estado, fecha_creacion)
-- configuracion_torneo (id_torneo*, puntos_victoria, puntos_empate,
--         puntos_derrota, admite_empate, clasifican_playoffs,
--         ida_y_vuelta, rondas_previstas, reglas)
-- participante (id_participante, id_torneo, id_usuario, id_equipo,
--         estado, fecha_inscripcion)
-- ronda (id_ronda, id_torneo, numero, nombre, fecha_inicio, fecha_fin, estado)
-- enfrentamiento (id_enfrentamiento, id_ronda, numero, id_participante_local,
--         id_participante_visitante, fecha_hora, lugar, estado)
-- resultado (id_enfrentamiento*, puntaje_local, puntaje_visitante,
--         id_participante_ganador, walkover, observaciones,
--         id_usuario_carga, fecha_carga)
-- tabla_posiciones (id_participante*, ganados, empatados, perdidos,
--         favor, contra, fecha_actualizacion)
-- auditoria (id_auditoria, id_usuario, tabla_afectada, id_registro,
--         accion, detalle, direccion_ip, fecha_hora)
--
-- (*) atributo que ademas es clave foranea de la clave primaria.
--
-- NOTAS DE NORMALIZACION
-- -------------------------------------------------------------------
-- 1FN: todos los atributos son atomicos. No hay listas ni campos
--      repetidos: los roles de un usuario, los integrantes de un equipo
--      y los participantes de un torneo viven en tablas propias.
-- 2FN: en las tablas de clave compuesta (usuario_rol, integrante_equipo)
--      no quedan atributos que dependan de una sola parte de la clave;
--      el nombre del rol esta en "rol" y los datos de la persona en
--      "usuario", no repetidos en la tabla intermedia.
-- 3FN: se eliminaron las dependencias transitivas. La disciplina, el
--      tipo de torneo y el modulo de competencia son catalogos y en
--      "torneo" solo queda su clave foranea, no su nombre. Los datos
--      del organizador no se repiten en "torneo": se llega por
--      id_usuario_organizador.
--
-- Datos derivados que NO se almacenan (dependerian de atributos no
-- clave y romperian la 3FN); se calculan en la consulta:
--   * partidos jugados = ganados + empatados + perdidos
--   * diferencia = favor - contra
--   * puntos = ganados * puntos_victoria + empatados * puntos_empate
--              + perdidos * puntos_derrota  (de configuracion_torneo)
--   * puesto en la tabla = orden del SELECT (ORDER BY puntos, diferencia)
-- Consulta de ejemplo para la tabla de posiciones, al final del archivo.
--
-- Claves subrogadas: todas las entidades fuertes usan un id numerico
-- AUTO_INCREMENT como clave primaria y guardan la clave natural
-- (correo, nombre del torneo, etc.) con restriccion UNIQUE.
--
-- Bajas logicas: usuario y equipo no se borran fisicamente, se marcan
-- con activo = 0, y el torneo con estado = 'cancelado', para no perder
-- el historial de enfrentamientos ni la auditoria.
--
-- Criterio de las acciones referenciales: lo que es estructura o
-- dependiente se borra en cascada (configuracion_torneo, ronda,
-- enfrentamiento, resultado, tabla_posiciones, las tablas intermedias);
-- lo que guarda historia deportiva se retiene con RESTRICT (no se borra
-- un participante que ya jugo, ni un torneo con inscriptos). La
-- auditoria nunca se pierde: al borrarse un usuario su id queda en NULL.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 0. BASE DE DATOS
-- ---------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS sgdm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sgdm;

-- Borrado en orden inverso al de creacion, para no violar las claves
-- foraneas al volver a ejecutar el script.
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS tabla_posiciones;
DROP TABLE IF EXISTS resultado;
DROP TABLE IF EXISTS enfrentamiento;
DROP TABLE IF EXISTS ronda;
DROP TABLE IF EXISTS participante;
DROP TABLE IF EXISTS configuracion_torneo;
DROP TABLE IF EXISTS torneo;
DROP TABLE IF EXISTS modulo_competencia;
DROP TABLE IF EXISTS tipo_torneo;
DROP TABLE IF EXISTS disciplina;
DROP TABLE IF EXISTS integrante_equipo;
DROP TABLE IF EXISTS equipo;
DROP TABLE IF EXISTS usuario_rol;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS rol;


-- ---------------------------------------------------------------------
-- 1. USUARIOS Y ROLES
-- ---------------------------------------------------------------------

CREATE TABLE rol (
  id_rol      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30)  NOT NULL,
  descripcion VARCHAR(150) NULL,
  CONSTRAINT pk_rol     PRIMARY KEY (id_rol),
  CONSTRAINT uq_rol_nom UNIQUE (nombre)
) ENGINE=InnoDB;

-- La contrasena se guarda como hash generado en PHP con password_hash()
-- (algoritmo por defecto, bcrypt = 60 caracteres). Se reservan 255 por
-- si el algoritmo por defecto de PHP cambia. Nunca se guarda en claro.
CREATE TABLE usuario (
  id_usuario    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  correo        VARCHAR(120) NOT NULL,
  hash_password VARCHAR(255) NOT NULL,
  nombre        VARCHAR(40)  NOT NULL,
  apellido      VARCHAR(40)  NOT NULL,
  alias         VARCHAR(20)  NULL,
  presentacion  VARCHAR(300) NULL,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_alta    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_usuario     PRIMARY KEY (id_usuario),
  CONSTRAINT uq_usuario_cor UNIQUE (correo),
  CONSTRAINT uq_usuario_ali UNIQUE (alias),
  CONSTRAINT ck_usuario_act CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

-- Un usuario puede tener mas de un rol (jugador y organizador a la vez),
-- y un rol lo tienen muchos usuarios: relacion N:M con tabla intermedia.
CREATE TABLE usuario_rol (
  id_usuario       INT UNSIGNED NOT NULL,
  id_rol           INT UNSIGNED NOT NULL,
  fecha_asignacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_usuario_rol     PRIMARY KEY (id_usuario, id_rol),
  CONSTRAINT fk_usurol_usuario  FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usurol_rol      FOREIGN KEY (id_rol) REFERENCES rol (id_rol)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 2. EQUIPOS
-- ---------------------------------------------------------------------

CREATE TABLE equipo (
  id_equipo          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre             VARCHAR(40)  NOT NULL,
  ciudad             VARCHAR(40)  NULL,
  id_usuario_capitan INT UNSIGNED NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_alta         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_equipo      PRIMARY KEY (id_equipo),
  CONSTRAINT uq_equipo_nom  UNIQUE (nombre),
  CONSTRAINT fk_equipo_cap  FOREIGN KEY (id_usuario_capitan) REFERENCES usuario (id_usuario)
      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT ck_equipo_act  CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

-- Un usuario puede integrar varios equipos (de distintas disciplinas) y
-- un equipo tiene varios integrantes: otra relacion N:M.
CREATE TABLE integrante_equipo (
  id_equipo  INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  dorsal     TINYINT UNSIGNED NULL,
  activo     TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_alta DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_integrante      PRIMARY KEY (id_equipo, id_usuario),
  CONSTRAINT fk_integ_equipo    FOREIGN KEY (id_equipo) REFERENCES equipo (id_equipo)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_integ_usuario   FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT uq_integ_dorsal    UNIQUE (id_equipo, dorsal),
  CONSTRAINT ck_integ_act       CHECK (activo IN (0, 1))
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 3. CATALOGOS DEL TORNEO
-- ---------------------------------------------------------------------

CREATE TABLE disciplina (
  id_disciplina INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(40)  NOT NULL,
  CONSTRAINT pk_disciplina     PRIMARY KEY (id_disciplina),
  CONSTRAINT uq_disciplina_nom UNIQUE (nombre)
) ENGINE=InnoDB;

-- Tipo de torneo: define QUIEN compite (una persona o un equipo).
-- compite_equipo = 1 -> los participantes son equipos
-- compite_equipo = 0 -> los participantes son usuarios
CREATE TABLE tipo_torneo (
  id_tipo_torneo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(30)  NOT NULL,
  compite_equipo TINYINT(1)   NOT NULL,
  descripcion    VARCHAR(150) NULL,
  CONSTRAINT pk_tipo_torneo     PRIMARY KEY (id_tipo_torneo),
  CONSTRAINT uq_tipo_torneo_nom UNIQUE (nombre),
  CONSTRAINT ck_tipo_torneo_eq  CHECK (compite_equipo IN (0, 1))
) ENGINE=InnoDB;

-- Modulo de competencia: define COMO se arma el fixture (liga,
-- eliminacion directa, suizo). Los modulos en si son de la tercera
-- entrega; la tabla queda creada porque el torneo ya la referencia.
CREATE TABLE modulo_competencia (
  id_modulo   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30)  NOT NULL,
  descripcion VARCHAR(150) NULL,
  CONSTRAINT pk_modulo     PRIMARY KEY (id_modulo),
  CONSTRAINT uq_modulo_nom UNIQUE (nombre)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 4. TORNEO Y SU CONFIGURACION
-- ---------------------------------------------------------------------

CREATE TABLE torneo (
  id_torneo              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre                 VARCHAR(80)  NOT NULL,
  id_disciplina          INT UNSIGNED NOT NULL,
  id_tipo_torneo         INT UNSIGNED NOT NULL,
  id_modulo              INT UNSIGNED NOT NULL,
  id_usuario_organizador INT UNSIGNED NOT NULL,
  fecha_inicio           DATE         NOT NULL,
  fecha_fin              DATE         NULL,
  max_participantes      SMALLINT UNSIGNED NOT NULL DEFAULT 16,
  sede                   VARCHAR(80)  NULL,
  estado                 VARCHAR(15)  NOT NULL DEFAULT 'borrador',
  fecha_creacion         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_torneo        PRIMARY KEY (id_torneo),
  CONSTRAINT uq_torneo_nom    UNIQUE (nombre, fecha_inicio),
  CONSTRAINT fk_torneo_disc   FOREIGN KEY (id_disciplina) REFERENCES disciplina (id_disciplina)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_torneo_tipo   FOREIGN KEY (id_tipo_torneo) REFERENCES tipo_torneo (id_tipo_torneo)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_torneo_modulo FOREIGN KEY (id_modulo) REFERENCES modulo_competencia (id_modulo)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_torneo_org    FOREIGN KEY (id_usuario_organizador) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_torneo_max    CHECK (max_participantes BETWEEN 2 AND 128),
  CONSTRAINT ck_torneo_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
  CONSTRAINT ck_torneo_estado CHECK (estado IN ('borrador', 'inscripcion', 'en_curso', 'finalizado', 'cancelado'))
) ENGINE=InnoDB;

-- Relacion 1:1 con torneo. Se separo en su propia tabla porque son los
-- parametros de puntaje y reglas, que solo existen una vez publicado el
-- torneo y se editan aparte de los datos de cabecera. La clave primaria
-- es la misma clave foranea, lo que garantiza el 1:1.
CREATE TABLE configuracion_torneo (
  id_torneo           INT UNSIGNED NOT NULL,
  puntos_victoria     TINYINT UNSIGNED NOT NULL DEFAULT 3,
  puntos_empate       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  puntos_derrota      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  admite_empate       TINYINT(1)   NOT NULL DEFAULT 1,
  clasifican_playoffs TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ida_y_vuelta        TINYINT(1)   NOT NULL DEFAULT 0,
  rondas_previstas    TINYINT UNSIGNED NULL,
  reglas              TEXT         NULL,
  CONSTRAINT pk_config        PRIMARY KEY (id_torneo),
  CONSTRAINT fk_config_torneo FOREIGN KEY (id_torneo) REFERENCES torneo (id_torneo)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT ck_config_emp    CHECK (admite_empate IN (0, 1)),
  CONSTRAINT ck_config_iv     CHECK (ida_y_vuelta IN (0, 1)),
  CONSTRAINT ck_config_pts    CHECK (puntos_victoria >= puntos_empate
                                 AND puntos_empate >= puntos_derrota)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 5. PARTICIPANTES
-- ---------------------------------------------------------------------
-- Un participante es la inscripcion de un competidor EN UN TORNEO. Segun
-- el tipo de torneo, el competidor es un usuario (individual) o un
-- equipo, nunca los dos: lo garantiza ck_part_competidor. El resto del
-- sistema (enfrentamiento, resultado, posiciones) referencia siempre a
-- participante y no le importa cual de los dos casos sea.
-- El torneo al que pertenece queda en esta tabla y NO se repite en las
-- tablas que dependen de ella, para no arrastrar una dependencia
-- transitiva.

CREATE TABLE participante (
  id_participante   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_torneo         INT UNSIGNED NOT NULL,
  id_usuario        INT UNSIGNED NULL,
  id_equipo         INT UNSIGNED NULL,
  estado            VARCHAR(12)  NOT NULL DEFAULT 'inscripto',
  fecha_inscripcion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_participante    PRIMARY KEY (id_participante),
  -- RESTRICT y no CASCADE: si el torneo arrastrara a sus participantes,
  -- el borrado igual quedaria trabado por los enfrentamientos, que los
  -- retienen. Un torneo con inscriptos no se borra, se cancela
  -- (estado = 'cancelado'); solo se puede borrar un borrador vacio.
  CONSTRAINT fk_part_torneo     FOREIGN KEY (id_torneo) REFERENCES torneo (id_torneo)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  -- ON UPDATE RESTRICT y no CASCADE: MariaDB no acepta un CHECK sobre una
  -- columna que una clave foranea pueda modificar (error 1901), y estas
  -- dos columnas las controla ck_part_competidor. No se pierde nada,
  -- porque las claves primarias son subrogadas y nunca cambian de valor.
  CONSTRAINT fk_part_usuario    FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_part_equipo     FOREIGN KEY (id_equipo) REFERENCES equipo (id_equipo)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  -- un mismo usuario o equipo no se puede inscribir dos veces al mismo
  -- torneo (en MySQL/MariaDB un UNIQUE admite varios NULL, asi que las
  -- filas del otro caso no molestan)
  CONSTRAINT uq_part_usuario    UNIQUE (id_torneo, id_usuario),
  CONSTRAINT uq_part_equipo     UNIQUE (id_torneo, id_equipo),
  CONSTRAINT ck_part_competidor CHECK ((id_usuario IS NOT NULL AND id_equipo IS NULL)
                                    OR (id_usuario IS NULL AND id_equipo IS NOT NULL)),
  CONSTRAINT ck_part_estado     CHECK (estado IN ('inscripto', 'confirmado', 'baja', 'descalificado'))
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 6. RONDAS Y ENFRENTAMIENTOS
-- ---------------------------------------------------------------------

CREATE TABLE ronda (
  id_ronda     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_torneo    INT UNSIGNED NOT NULL,
  numero       TINYINT UNSIGNED NOT NULL,
  nombre       VARCHAR(40)  NULL,   -- "Octavos", "Final", "Fecha 7"
  fecha_inicio DATE         NULL,
  fecha_fin    DATE         NULL,
  estado       VARCHAR(12)  NOT NULL DEFAULT 'pendiente',
  CONSTRAINT pk_ronda        PRIMARY KEY (id_ronda),
  CONSTRAINT fk_ronda_torneo FOREIGN KEY (id_torneo) REFERENCES torneo (id_torneo)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT uq_ronda_num    UNIQUE (id_torneo, numero),
  CONSTRAINT ck_ronda_num    CHECK (numero >= 1),
  CONSTRAINT ck_ronda_fechas CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio),
  CONSTRAINT ck_ronda_estado CHECK (estado IN ('pendiente', 'en_curso', 'cerrada'))
) ENGINE=InnoDB;

-- id_participante_visitante admite NULL para el caso de "libre" (bye),
-- cuando la cantidad de participantes es impar.
CREATE TABLE enfrentamiento (
  id_enfrentamiento         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_ronda                  INT UNSIGNED NOT NULL,
  numero                    TINYINT UNSIGNED NOT NULL,
  id_participante_local     INT UNSIGNED NOT NULL,
  id_participante_visitante INT UNSIGNED NULL,
  fecha_hora                DATETIME     NULL,
  lugar                     VARCHAR(80)  NULL,
  estado                    VARCHAR(12)  NOT NULL DEFAULT 'programado',
  CONSTRAINT pk_enfrentamiento  PRIMARY KEY (id_enfrentamiento),
  CONSTRAINT fk_enfr_ronda      FOREIGN KEY (id_ronda) REFERENCES ronda (id_ronda)
      ON DELETE CASCADE ON UPDATE CASCADE,
  -- mismo caso que en participante: ck_enfr_distintos controla estas dos
  -- columnas, asi que sus claves foraneas van con ON UPDATE RESTRICT.
  CONSTRAINT fk_enfr_local      FOREIGN KEY (id_participante_local) REFERENCES participante (id_participante)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_enfr_visitante  FOREIGN KEY (id_participante_visitante) REFERENCES participante (id_participante)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT uq_enfr_numero     UNIQUE (id_ronda, numero),
  CONSTRAINT ck_enfr_distintos  CHECK (id_participante_visitante IS NULL
                                    OR id_participante_visitante <> id_participante_local),
  CONSTRAINT ck_enfr_estado     CHECK (estado IN ('programado', 'en_vivo', 'jugado', 'suspendido', 'anulado'))
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 7. RESULTADOS
-- ---------------------------------------------------------------------
-- Relacion 1:1 con enfrentamiento: un enfrentamiento tiene a lo sumo un
-- resultado, y mientras no se jugo simplemente no hay fila. Por eso la
-- clave primaria es la del enfrentamiento.
-- El ganador se guarda porque no siempre se deduce del puntaje (walkover,
-- desempate por fuera del marcador); en un empate queda en NULL.

CREATE TABLE resultado (
  id_enfrentamiento        INT UNSIGNED NOT NULL,
  puntaje_local            SMALLINT     NOT NULL DEFAULT 0,
  puntaje_visitante        SMALLINT     NOT NULL DEFAULT 0,
  id_participante_ganador  INT UNSIGNED NULL,
  walkover                 TINYINT(1)   NOT NULL DEFAULT 0,
  observaciones            VARCHAR(200) NULL,
  id_usuario_carga         INT UNSIGNED NULL,
  fecha_carga              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_resultado     PRIMARY KEY (id_enfrentamiento),
  CONSTRAINT fk_res_enfr      FOREIGN KEY (id_enfrentamiento) REFERENCES enfrentamiento (id_enfrentamiento)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_res_ganador   FOREIGN KEY (id_participante_ganador) REFERENCES participante (id_participante)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_res_usuario   FOREIGN KEY (id_usuario_carga) REFERENCES usuario (id_usuario)
      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT ck_res_walkover  CHECK (walkover IN (0, 1)),
  CONSTRAINT ck_res_puntajes  CHECK (puntaje_local >= 0 AND puntaje_visitante >= 0)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 8. TABLA DE POSICIONES
-- ---------------------------------------------------------------------
-- Acumulado por participante. Como participante ya pertenece a un
-- torneo, NO se repite id_torneo aca: seria una dependencia transitiva
-- (id_participante -> id_torneo). La clave primaria es id_participante,
-- que ademas asegura una sola fila de posiciones por inscripcion.
-- Solo se guardan los contadores; puntos, diferencia, partidos jugados y
-- puesto se calculan al consultar (ver consulta de ejemplo al final).
-- Los contadores son SMALLINT con signo, no UNSIGNED, aunque nunca sean
-- negativos: en MariaDB la resta de dos columnas UNSIGNED da error 1690
-- cuando el resultado es negativo, y la diferencia (favor - contra) lo es
-- para todo el que va perdiendo. El dominio lo cuida ck_pos_contadores.
-- Por lo mismo son SMALLINT con signo los puntajes de "resultado".

CREATE TABLE tabla_posiciones (
  id_participante     INT UNSIGNED NOT NULL,
  ganados             SMALLINT NOT NULL DEFAULT 0,
  empatados           SMALLINT NOT NULL DEFAULT 0,
  perdidos            SMALLINT NOT NULL DEFAULT 0,
  favor               SMALLINT NOT NULL DEFAULT 0,
  contra              SMALLINT NOT NULL DEFAULT 0,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_posiciones   PRIMARY KEY (id_participante),
  CONSTRAINT fk_pos_part     FOREIGN KEY (id_participante) REFERENCES participante (id_participante)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT ck_pos_contadores CHECK (ganados >= 0 AND empatados >= 0 AND perdidos >= 0
                                  AND favor >= 0 AND contra >= 0)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 9. AUDITORIA
-- ---------------------------------------------------------------------
-- Registro de las operaciones sensibles (altas, bajas, modificaciones,
-- inicios de sesion). Las filas se insertan desde PHP, no con triggers.
-- id_usuario queda en NULL si el usuario se elimina o si la accion es
-- anonima (por ejemplo un intento de login fallido), para que el
-- historial no se pierda.

CREATE TABLE auditoria (
  id_auditoria   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario     INT UNSIGNED NULL,
  tabla_afectada VARCHAR(40)  NOT NULL,
  id_registro    INT UNSIGNED NULL,
  accion         VARCHAR(15)  NOT NULL,
  detalle        VARCHAR(255) NULL,
  direccion_ip   VARCHAR(45)  NULL,   -- 45 = largo maximo de una IPv6
  fecha_hora     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_auditoria     PRIMARY KEY (id_auditoria),
  CONSTRAINT fk_audit_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT ck_audit_accion  CHECK (accion IN ('alta', 'baja', 'modificacion',
                                                'login_ok', 'login_error', 'logout'))
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 10. INDICES DE APOYO
-- ---------------------------------------------------------------------
-- InnoDB crea solo el indice de cada clave foranea. Estos son los de las
-- busquedas mas frecuentes del sistema.
CREATE INDEX ix_torneo_estado   ON torneo (estado, fecha_inicio);
CREATE INDEX ix_enfr_fecha      ON enfrentamiento (fecha_hora);
CREATE INDEX ix_audit_fecha     ON auditoria (fecha_hora);


-- ---------------------------------------------------------------------
-- 11. DATOS INICIALES DE LOS CATALOGOS
-- ---------------------------------------------------------------------

INSERT INTO rol (nombre, descripcion) VALUES
  ('administrador', 'Administra la plataforma completa'),
  ('organizador',   'Crea y gestiona sus propios torneos'),
  ('jugador',       'Se inscribe y compite'),
  ('arbitro',       'Carga resultados de los enfrentamientos');

INSERT INTO disciplina (nombre) VALUES
  ('Esports'), ('Ajedrez'), ('Tenis de mesa'), ('Futbol'), ('Cartas');

INSERT INTO tipo_torneo (nombre, compite_equipo, descripcion) VALUES
  ('Individual', 0, 'Compiten personas'),
  ('Por equipos', 1, 'Compiten equipos');

INSERT INTO modulo_competencia (nombre, descripcion) VALUES
  ('Liga',                'Todos contra todos, calendario completo desde el inicio'),
  ('Eliminacion directa', 'Llaves, el que pierde queda fuera'),
  ('Suizo',               'Emparejamiento por puntaje, sin repetir rivales');


-- =====================================================================
-- 12. DCL - USUARIOS DE BASE DE DATOS Y PERMISOS
-- =====================================================================
-- IMPORTANTE: las contrasenas de abajo son marcadores. Hay que
-- cambiarlas al instalar y no versionar las reales en el repositorio.
--
-- Tres usuarios, por nivel de privilegio:
--   sgdm_app     -> el que usa la aplicacion PHP. Solo DML sobre sgdm:
--                   no puede crear ni borrar tablas, asi que un error o
--                   una inyeccion no pueden alterar la estructura.
--   sgdm_consulta-> solo lectura, para reportes y para la tabla de
--                   posiciones publica.
--   sgdm_admin   -> mantenimiento del esquema y respaldos.

CREATE USER IF NOT EXISTS 'sgdm_app'@'localhost'      IDENTIFIED BY 'CAMBIAR_CLAVE_APP';
CREATE USER IF NOT EXISTS 'sgdm_consulta'@'localhost' IDENTIFIED BY 'CAMBIAR_CLAVE_CONSULTA';
CREATE USER IF NOT EXISTS 'sgdm_admin'@'localhost'    IDENTIFIED BY 'CAMBIAR_CLAVE_ADMIN';

-- Aplicacion: lectura en todo el esquema y escritura solo donde la
-- necesita. No tiene ningun permiso de DDL, asi que un error de la
-- aplicacion o una inyeccion no pueden alterar la estructura de la base.
GRANT SELECT ON sgdm.* TO 'sgdm_app'@'localhost';

-- Tablas operativas. usuario y equipo van sin DELETE a proposito: la
-- baja es logica (activo = 0), de modo que la propia base impide que la
-- aplicacion borre una persona o un equipo con historial.
GRANT INSERT, UPDATE         ON sgdm.usuario              TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.usuario_rol          TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE         ON sgdm.equipo               TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.integrante_equipo    TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.torneo               TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.configuracion_torneo TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.participante         TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.ronda                TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.enfrentamiento       TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.resultado            TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.tabla_posiciones     TO 'sgdm_app'@'localhost';

-- La auditoria solo admite altas: ni la aplicacion puede modificar o
-- borrar el historial.
GRANT INSERT ON sgdm.auditoria TO 'sgdm_app'@'localhost';

-- Los catalogos (rol, disciplina, tipo_torneo, modulo_competencia)
-- quedan de solo lectura para la aplicacion: los mantiene sgdm_admin.

-- Consulta: solo lectura.
GRANT SELECT ON sgdm.* TO 'sgdm_consulta'@'localhost';

-- Administrador del esquema: todo sobre esta base, y nada fuera de ella.
GRANT ALL PRIVILEGES ON sgdm.* TO 'sgdm_admin'@'localhost';

-- GRANT y REVOKE toman efecto de inmediato. FLUSH PRIVILEGES solo hace
-- falta si se editan a mano las tablas de la base mysql.


-- =====================================================================
-- 13. CONSULTA DE EJEMPLO: TABLA DE POSICIONES
-- =====================================================================
-- Muestra como se arman los datos derivados que no se almacenan
-- (partidos jugados, diferencia, puntos y puesto). Reemplazar el 1 por
-- el id del torneo.
--
-- SELECT
--     COALESCE(e.nombre, CONCAT(u.nombre, ' ', u.apellido)) AS competidor,
--     (p.ganados + p.empatados + p.perdidos)                AS pj,
--     p.ganados, p.empatados, p.perdidos,
--     (p.favor - p.contra)                                  AS diferencia,
--     (p.ganados   * c.puntos_victoria
--    + p.empatados * c.puntos_empate
--    + p.perdidos  * c.puntos_derrota)                      AS puntos
-- FROM tabla_posiciones p
--     INNER JOIN participante         pa ON pa.id_participante = p.id_participante
--     INNER JOIN configuracion_torneo c  ON c.id_torneo = pa.id_torneo
--     LEFT  JOIN equipo               e  ON e.id_equipo = pa.id_equipo
--     LEFT  JOIN usuario              u  ON u.id_usuario = pa.id_usuario
-- WHERE pa.id_torneo = 1
--   AND pa.estado <> 'baja'
-- ORDER BY puntos DESC, diferencia DESC, p.favor DESC;
-- =====================================================================
