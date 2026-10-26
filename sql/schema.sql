-- Esquema de la base sgdm: 19 tablas en tercera forma normal, sus restricciones y los usuarios de la base (DCL).
-- Crea una base nueva desde cero; las bases que ya existen se actualizan con sql/migraciones/.

-- 0. BASE DE DATOS
SET NAMES utf8mb4;

-- [solo servidor propio] desde aca
CREATE DATABASE IF NOT EXISTS sgdm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sgdm;

DROP TABLE IF EXISTS medicion;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS tabla_posiciones;
DROP TABLE IF EXISTS resultado;
DROP TABLE IF EXISTS enfrentamiento;
DROP TABLE IF EXISTS ronda;
DROP TABLE IF EXISTS pedido_inscripcion;
DROP TABLE IF EXISTS participante;
DROP TABLE IF EXISTS configuracion_torneo;
DROP TABLE IF EXISTS torneo;
DROP TABLE IF EXISTS modulo_competencia;
DROP TABLE IF EXISTS tipo_torneo;
DROP TABLE IF EXISTS disciplina;
DROP TABLE IF EXISTS integrante_equipo;
DROP TABLE IF EXISTS equipo;
DROP TABLE IF EXISTS pedido_rol;
DROP TABLE IF EXISTS usuario_rol;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS rol;
-- [solo servidor propio] hasta aca

-- La codificacion por defecto de la base, tambien en utf8mb4.
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. USUARIOS Y ROLES

CREATE TABLE rol (
  id_rol      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30)  NOT NULL,
  descripcion VARCHAR(150) NULL,
  CONSTRAINT pk_rol     PRIMARY KEY (id_rol),
  CONSTRAINT uq_rol_nom UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  foto_perfil   VARCHAR(40)  NULL,
  foto_portada  VARCHAR(40)  NULL,
  -- Cuenta de muestra: una de las organizadoras de las ligas de muestra (sql/migraciones/005_ligas.sql).
  de_muestra    TINYINT(1)   NOT NULL DEFAULT 0,
  CONSTRAINT pk_usuario     PRIMARY KEY (id_usuario),
  CONSTRAINT uq_usuario_cor UNIQUE (correo),
  CONSTRAINT uq_usuario_ali UNIQUE (alias),
  CONSTRAINT ck_usuario_act CHECK (activo IN (0, 1)),
  CONSTRAINT ck_usuario_foto    CHECK (foto_perfil IS NULL
                                    OR BINARY foto_perfil REGEXP '^[0-9a-f]{32}[.](jpg|png|webp)$'),
  CONSTRAINT ck_usuario_portada CHECK (foto_portada IS NULL
                                    OR BINARY foto_portada REGEXP '^[0-9a-f]{32}[.](jpg|png|webp)$'),
  CONSTRAINT ck_usuario_muestra CHECK (de_muestra IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_rol (
  id_usuario       INT UNSIGNED NOT NULL,
  id_rol           INT UNSIGNED NOT NULL,
  fecha_asignacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_usuario_rol     PRIMARY KEY (id_usuario, id_rol),
  CONSTRAINT fk_usurol_usuario  FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usurol_rol      FOREIGN KEY (id_rol) REFERENCES rol (id_rol)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido_rol (
  id_pedido_rol       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario          INT UNSIGNED NOT NULL,
  id_rol              INT UNSIGNED NOT NULL,
  estado              VARCHAR(10)  NOT NULL DEFAULT 'pendiente',
  fecha_pedido        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion    DATETIME     NULL,
  id_usuario_resuelve INT UNSIGNED NULL,
  pendiente_de        INT UNSIGNED GENERATED ALWAYS AS
                        (IF(estado = 'pendiente', id_usuario, NULL)) STORED,
  CONSTRAINT pk_pedido_rol       PRIMARY KEY (id_pedido_rol),
  CONSTRAINT fk_pedido_usuario   FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pedido_rol       FOREIGN KEY (id_rol) REFERENCES rol (id_rol)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pedido_resuelve  FOREIGN KEY (id_usuario_resuelve) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT uq_pedido_pendiente UNIQUE (pendiente_de, id_rol),
  CONSTRAINT ck_pedido_estado    CHECK (estado IN ('pendiente', 'aprobado', 'rechazado')),
  CONSTRAINT ck_pedido_resuelto  CHECK ((estado = 'pendiente' AND fecha_resolucion IS NULL
                                                             AND id_usuario_resuelve IS NULL)
                                     OR (estado <> 'pendiente' AND fecha_resolucion IS NOT NULL
                                                               AND id_usuario_resuelve IS NOT NULL)),
  CONSTRAINT ck_pedido_fechas    CHECK (fecha_resolucion IS NULL OR fecha_resolucion >= fecha_pedido),
  CONSTRAINT ck_pedido_propio    CHECK (id_usuario_resuelve IS NULL OR id_usuario_resuelve <> id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. EQUIPOS

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CATALOGOS DEL TORNEO

CREATE TABLE disciplina (
  id_disciplina INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(40)  NOT NULL,
  CONSTRAINT pk_disciplina     PRIMARY KEY (id_disciplina),
  CONSTRAINT uq_disciplina_nom UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tipo_torneo (
  id_tipo_torneo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(30)  NOT NULL,
  compite_equipo TINYINT(1)   NOT NULL,
  descripcion    VARCHAR(150) NULL,
  CONSTRAINT pk_tipo_torneo     PRIMARY KEY (id_tipo_torneo),
  CONSTRAINT uq_tipo_torneo_nom UNIQUE (nombre),
  CONSTRAINT ck_tipo_torneo_eq  CHECK (compite_equipo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modulo de competencia: define COMO se arma el fixture (liga, eliminacion directa, suizo).
CREATE TABLE modulo_competencia (
  id_modulo   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30)  NOT NULL,
  descripcion VARCHAR(150) NULL,
  habilitado  TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_modulo     PRIMARY KEY (id_modulo),
  CONSTRAINT uq_modulo_nom UNIQUE (nombre),
  CONSTRAINT ck_modulo_habilitado CHECK (habilitado IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TORNEO Y SU CONFIGURACION

CREATE TABLE torneo (
  id_torneo              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre                 VARCHAR(80)  NOT NULL,
  id_disciplina          INT UNSIGNED NOT NULL,
  id_tipo_torneo         INT UNSIGNED NOT NULL,
  id_modulo              INT UNSIGNED NOT NULL,
  id_usuario_organizador INT UNSIGNED NOT NULL,
  -- Opcional: una liga nace con la inscripcion abierta y la fecha de inicio puede quedar a definir.
  fecha_inicio           DATE         NULL,
  fecha_fin              DATE         NULL,
  max_participantes      SMALLINT UNSIGNED NOT NULL DEFAULT 16,
  sede                   VARCHAR(80)  NULL,
  estado                 VARCHAR(15)  NOT NULL DEFAULT 'borrador',
  fecha_creacion         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nombre_vigente         VARCHAR(80)  GENERATED ALWAYS AS
                           (IF(estado IN ('borrador', 'inscripcion', 'en_curso'), nombre, NULL)) STORED,
  CONSTRAINT pk_torneo        PRIMARY KEY (id_torneo),
  CONSTRAINT uq_torneo_nom    UNIQUE (nombre, fecha_inicio),
  CONSTRAINT uq_torneo_vigente UNIQUE (nombre_vigente),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relacion 1:1 con torneo.
CREATE TABLE configuracion_torneo (
  id_torneo           INT UNSIGNED NOT NULL,
  puntos_victoria     TINYINT UNSIGNED NOT NULL DEFAULT 3,
  puntos_empate       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  puntos_derrota      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  admite_empate       TINYINT(1)   NOT NULL DEFAULT 1,
  -- Partidos al mejor de N (mapas, sets, partidas): 3, 5 o 7.
  mejor_de            TINYINT UNSIGNED NULL,
  clasifican_playoffs TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ida_y_vuelta        TINYINT(1)   NOT NULL DEFAULT 0,
  criterio_desempate  VARCHAR(10)  NOT NULL DEFAULT 'diferencia',
  rondas_previstas    TINYINT UNSIGNED NULL,
  reglas              TEXT         NULL,
  CONSTRAINT pk_config        PRIMARY KEY (id_torneo),
  CONSTRAINT fk_config_torneo FOREIGN KEY (id_torneo) REFERENCES torneo (id_torneo)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT ck_config_emp    CHECK (admite_empate IN (0, 1)),
  CONSTRAINT ck_config_iv     CHECK (ida_y_vuelta IN (0, 1)),
  CONSTRAINT ck_config_pts    CHECK (puntos_victoria >= puntos_empate
                                 AND puntos_empate >= puntos_derrota),
  CONSTRAINT ck_config_victoria CHECK (puntos_victoria BETWEEN 1 AND 10),
  CONSTRAINT ck_config_desempate CHECK (criterio_desempate IN ('diferencia', 'favor')),
  CONSTRAINT ck_config_mejor_de   CHECK (mejor_de IS NULL OR mejor_de IN (3, 5, 7)),
  CONSTRAINT ck_config_serie_empate CHECK (mejor_de IS NULL OR admite_empate = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PARTICIPANTES

CREATE TABLE participante (
  id_participante   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_torneo         INT UNSIGNED NOT NULL,
  id_usuario        INT UNSIGNED NULL,
  id_equipo         INT UNSIGNED NULL,
  estado            VARCHAR(12)  NOT NULL DEFAULT 'inscripto',
  fecha_inscripcion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_participante    PRIMARY KEY (id_participante),
  CONSTRAINT fk_part_torneo     FOREIGN KEY (id_torneo) REFERENCES torneo (id_torneo)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_part_usuario    FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_part_equipo     FOREIGN KEY (id_equipo) REFERENCES equipo (id_equipo)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT uq_part_usuario    UNIQUE (id_torneo, id_usuario),
  CONSTRAINT uq_part_equipo     UNIQUE (id_torneo, id_equipo),
  CONSTRAINT ck_part_competidor CHECK ((id_usuario IS NOT NULL AND id_equipo IS NULL)
                                    OR (id_usuario IS NULL AND id_equipo IS NOT NULL)),
  CONSTRAINT ck_part_estado     CHECK (estado IN ('inscripto', 'confirmado', 'baja', 'descalificado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido_inscripcion (
  id_pedido_inscripcion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_torneo             INT UNSIGNED NOT NULL,
  id_equipo             INT UNSIGNED NOT NULL,
  id_usuario            INT UNSIGNED NOT NULL,   -- quien pide: el capitan
  estado                VARCHAR(10)  NOT NULL DEFAULT 'pendiente',
  fecha_pedido          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion      DATETIME     NULL,
  id_usuario_resuelve   INT UNSIGNED NULL,
  pendiente_de          INT UNSIGNED GENERATED ALWAYS AS
                          (IF(estado = 'pendiente', id_equipo, NULL)) STORED,
  CONSTRAINT pk_pedido_inscripcion PRIMARY KEY (id_pedido_inscripcion),
  CONSTRAINT fk_pinsc_torneo       FOREIGN KEY (id_torneo) REFERENCES torneo (id_torneo)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pinsc_equipo       FOREIGN KEY (id_equipo) REFERENCES equipo (id_equipo)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pinsc_usuario      FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pinsc_resuelve     FOREIGN KEY (id_usuario_resuelve) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT uq_pinsc_pendiente    UNIQUE (pendiente_de, id_torneo),
  CONSTRAINT ck_pinsc_estado       CHECK (estado IN ('pendiente', 'aceptado', 'rechazado')),
  CONSTRAINT ck_pinsc_resuelto     CHECK ((estado = 'pendiente' AND fecha_resolucion IS NULL
                                                               AND id_usuario_resuelve IS NULL)
                                       OR (estado <> 'pendiente' AND fecha_resolucion IS NOT NULL
                                                                 AND id_usuario_resuelve IS NOT NULL)),
  CONSTRAINT ck_pinsc_fechas       CHECK (fecha_resolucion IS NULL OR fecha_resolucion >= fecha_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. RONDAS Y ENFRENTAMIENTOS

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT fk_enfr_local      FOREIGN KEY (id_participante_local) REFERENCES participante (id_participante)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_enfr_visitante  FOREIGN KEY (id_participante_visitante) REFERENCES participante (id_participante)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT uq_enfr_numero     UNIQUE (id_ronda, numero),
  CONSTRAINT ck_enfr_distintos  CHECK (id_participante_visitante IS NULL
                                    OR id_participante_visitante <> id_participante_local),
  CONSTRAINT ck_enfr_estado     CHECK (estado IN ('programado', 'en_vivo', 'jugado', 'suspendido', 'anulado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. RESULTADOS

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABLA DE POSICIONES

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. AUDITORIA

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
                                                'login_ok', 'login_error', 'logout',
                                                'pedido_rol', 'aprobacion', 'rechazo',
                                                'inscripcion', 'solicitud', 'cierre', 'fixture',
                                                'resultado', 'programacion', 'final',
                                                'cancelacion', 'medicion'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9b. RENDIMIENTO FISICO (RF-29, el modulo de Fisica)

CREATE TABLE medicion (
  id_medicion   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario    INT UNSIGNED NOT NULL,
  prueba        VARCHAR(10)  NOT NULL,
  distancia_cm  DECIMAL(4,1) NULL,
  tiempo_s      DECIMAL(4,2) NULL,
  masa_kg       DECIMAL(4,1) NULL,
  fecha         DATE         NOT NULL,
  fecha_carga   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_medicion     PRIMARY KEY (id_medicion),
  CONSTRAINT fk_med_usuario  FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT ck_med_prueba   CHECK (prueba IN ('reaccion', 'sprint', 'salto')),
  CONSTRAINT ck_med_datos    CHECK (
       (prueba = 'reaccion' AND distancia_cm IS NOT NULL AND distancia_cm BETWEEN 0.5 AND 30
                            AND tiempo_s IS NULL AND masa_kg IS NULL)
    OR (prueba = 'sprint'   AND distancia_cm IS NULL
                            AND tiempo_s IS NOT NULL AND tiempo_s BETWEEN 1 AND 6
                            AND masa_kg IS NOT NULL AND masa_kg BETWEEN 20 AND 200)
    OR (prueba = 'salto'    AND distancia_cm IS NULL
                            AND tiempo_s IS NOT NULL AND tiempo_s BETWEEN 0.1 AND 1
                            AND masa_kg IS NOT NULL AND masa_kg BETWEEN 20 AND 200)),
  KEY ix_med_usuario (id_usuario, prueba, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. INDICES DE APOYO
CREATE INDEX ix_torneo_estado   ON torneo (estado, fecha_inicio);
CREATE INDEX ix_enfr_fecha      ON enfrentamiento (fecha_hora);
CREATE INDEX ix_audit_fecha     ON auditoria (fecha_hora);

-- 11. DATOS INICIALES DE LOS CATALOGOS

INSERT INTO rol (nombre, descripcion) VALUES
  ('administrador', 'Administra la plataforma completa'),
  ('organizador',   'Crea y gestiona sus propios torneos'),
  ('jugador',       'Se inscribe y compite'),
  ('arbitro',       'Carga resultados de los enfrentamientos');

INSERT INTO disciplina (nombre) VALUES
  ('Esports'), ('Ajedrez'), ('Tenis de mesa'), ('Fútbol'), ('Cartas'), ('Fútbol 5');

INSERT INTO tipo_torneo (nombre, compite_equipo, descripcion) VALUES
  ('Individual', 0, 'Compiten personas'),
  ('Por equipos', 1, 'Compiten equipos');

INSERT INTO modulo_competencia (nombre, descripcion) VALUES
  ('Liga',                'Todos contra todos, calendario completo desde el inicio'),
  ('Eliminación directa', 'Llaves, el que pierde queda fuera'),
  ('Suizo',               'Emparejamiento por puntaje, sin repetir rivales');

-- [solo servidor propio] desde aca
-- 12. DCL - USUARIOS DE BASE DE DATOS Y PERMISOS

CREATE USER IF NOT EXISTS 'sgdm_app'@'localhost'      IDENTIFIED BY 'CAMBIAR_CLAVE_APP';
CREATE USER IF NOT EXISTS 'sgdm_consulta'@'localhost' IDENTIFIED BY 'CAMBIAR_CLAVE_CONSULTA';
CREATE USER IF NOT EXISTS 'sgdm_admin'@'localhost'    IDENTIFIED BY 'CAMBIAR_CLAVE_ADMIN';

-- Aplicacion: lectura en todo el esquema y escritura solo donde la necesita.
GRANT SELECT ON sgdm.* TO 'sgdm_app'@'localhost';

GRANT INSERT, UPDATE         ON sgdm.usuario              TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.usuario_rol          TO 'sgdm_app'@'localhost';
-- Pedidos de rol: sin DELETE.
GRANT INSERT, UPDATE         ON sgdm.pedido_rol           TO 'sgdm_app'@'localhost';
-- Pedidos de inscripcion: igual, sin DELETE.
GRANT INSERT, UPDATE         ON sgdm.pedido_inscripcion   TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE         ON sgdm.equipo               TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.integrante_equipo    TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.torneo               TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.configuracion_torneo TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.participante         TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.ronda                TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.enfrentamiento       TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.resultado            TO 'sgdm_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON sgdm.tabla_posiciones     TO 'sgdm_app'@'localhost';
-- Mediciones: se guardan y se borran (una cargada por error), no se modifican.
GRANT INSERT, DELETE         ON sgdm.medicion             TO 'sgdm_app'@'localhost';
-- Habilitar o deshabilitar un modulo (RF-24): solo esa columna.
GRANT UPDATE (habilitado)    ON sgdm.modulo_competencia   TO 'sgdm_app'@'localhost';

-- La auditoria solo admite altas: ni la aplicacion puede modificar o borrar el historial.
GRANT INSERT ON sgdm.auditoria TO 'sgdm_app'@'localhost';

-- Consulta: solo lectura.
GRANT SELECT ON sgdm.* TO 'sgdm_consulta'@'localhost';

-- Administrador del esquema: todo sobre esta base, y nada fuera de ella.
GRANT ALL PRIVILEGES ON sgdm.* TO 'sgdm_admin'@'localhost';

-- [solo servidor propio] hasta aca
-- GRANT y REVOKE toman efecto de inmediato.

-- 13. CONSULTA DE EJEMPLO: TABLA DE POSICIONES
