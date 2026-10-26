-- Migracion 005: lo que necesitan las ligas y las tres ligas de muestra.
-- Necesita la 003 y la 004. Frena sin cambiar nada si falta alguna o si ya se aplico.
-- Al final tiene que mostrar 3 ligas de muestra, 31 equipos, 111 partidos y 57 jugados.

SET NAMES utf8mb4;

-- La hora de Montevideo, solo para esta conexion.
SET time_zone = '-03:00';

-- 1. Lo que tiene que estar antes.
SELECT COUNT(*) AS pedidos_de_rol FROM pedido_rol;

-- 1b. La 004: las tablas en utf8mb4.
CREATE TEMPORARY TABLE control_005_004 (
  tablas_en_otra_codificacion INT NOT NULL,
  CONSTRAINT ck_005_falta_la_004 CHECK (tablas_en_otra_codificacion = 0)
);
INSERT INTO control_005_004 (tablas_en_otra_codificacion)
SELECT COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
   AND TABLE_COLLATION <> 'utf8mb4_unicode_ci';
DROP TEMPORARY TABLE control_005_004;

-- 2. Que la 005 no este aplicada, y que los nombres esten libres.
CREATE TEMPORARY TABLE control_005_aplicada (
  ya_estan INT NOT NULL,
  CONSTRAINT ck_005_ya_esta_aplicada CHECK (ya_estan = 0)
);
INSERT INTO control_005_aplicada (ya_estan)
SELECT COUNT(*) FROM torneo
 WHERE nombre IN ('Liga Valorant · Otoño', 'Liga Barrial del Cerro', 'Liga Interna Club Sur');
DROP TEMPORARY TABLE control_005_aplicada;

-- 2b. Los 31 equipos de muestra.
CREATE TEMPORARY TABLE carga_005_equipo (
  nombre VARCHAR(40) NOT NULL,
  ciudad VARCHAR(40) NULL,
  alta   DATETIME    NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO carga_005_equipo (nombre, ciudad, alta) VALUES
  ('Vortex', 'Montevideo', '2026-08-01 10:00:00'),
  ('Sur Gaming', NULL, '2026-08-01 10:00:00'),
  ('Halcones', 'Pando', '2026-08-01 10:00:00'),
  ('Delta Gaming', 'Las Piedras', '2026-08-01 10:00:00'),
  ('Titanes CS', 'Montevideo', '2026-08-01 10:00:00'),
  ('Nova Esports', 'Ciudad de la Costa', '2026-08-01 10:00:00'),
  ('Aurora FC', 'Canelones', '2026-08-01 10:00:00'),
  ('Liceo 3', 'Montevideo', '2026-08-01 10:00:00'),
  ('Ping Masters', 'Montevideo', '2026-08-01 10:00:00'),
  ('Rambla Esports', NULL, '2026-08-01 10:00:00'),
  ('Faro Gaming', NULL, '2026-08-01 10:00:00'),
  ('Atlántida GG', NULL, '2026-08-01 10:00:00'),
  ('Cerro FC', 'Montevideo', '2026-08-10 10:00:00'),
  ('Casabó', 'Montevideo', '2026-08-10 10:00:00'),
  ('Villa del Cerro', 'Montevideo', '2026-08-10 10:00:00'),
  ('Cerro Norte', 'Montevideo', '2026-08-10 10:00:00'),
  ('Paso de la Arena', 'Montevideo', '2026-08-10 10:00:00'),
  ('Santa Catalina', 'Montevideo', '2026-08-10 10:00:00'),
  ('La Teja', 'Montevideo', '2026-08-10 10:00:00'),
  ('Tres Ombúes', 'Montevideo', '2026-08-10 10:00:00'),
  ('Nuevo París', 'Montevideo', '2026-08-10 10:00:00'),
  ('Belvedere', 'Montevideo', '2026-08-10 10:00:00'),
  ('Club Sur A', 'Montevideo', '2026-09-10 10:00:00'),
  ('Club Sur B', 'Montevideo', '2026-09-10 10:00:00'),
  ('Club Sur C', 'Montevideo', '2026-09-10 10:00:00'),
  ('Club Sur D', 'Montevideo', '2026-09-10 10:00:00'),
  ('Veteranos del Sur', 'Montevideo', '2026-09-10 10:00:00'),
  ('Sub 20 del Sur', 'Montevideo', '2026-09-10 10:00:00'),
  ('Amigos del Sur', 'Montevideo', '2026-09-10 10:00:00'),
  ('Sur Femenino', 'Montevideo', '2026-09-10 10:00:00'),
  ('Sur Mixto', 'Montevideo', '2026-09-10 10:00:00');

-- 2c. Los nombres de equipo y los correos de muestra que ya esten ocupados.
SELECT 'equipo' AS ocupado, e.nombre AS valor
  FROM equipo e JOIN carga_005_equipo c ON c.nombre = e.nombre
UNION ALL
SELECT 'cuenta', correo
  FROM usuario
 WHERE correo IN ('vortice@ejemplo.invalid', 'clubsur@ejemplo.invalid', 'cerro@ejemplo.invalid')
UNION ALL
SELECT 'torneo vigente repetido', MIN(nombre)
  FROM torneo
 WHERE estado IN ('borrador', 'inscripcion', 'en_curso')
 GROUP BY nombre HAVING COUNT(*) > 1;

CREATE TEMPORARY TABLE control_005_nombres (
  ocupados INT NOT NULL,
  CONSTRAINT ck_005_nombres_libres CHECK (ocupados = 0)
);
INSERT INTO control_005_nombres (ocupados)
SELECT (SELECT COUNT(*) FROM equipo e JOIN carga_005_equipo c ON c.nombre = e.nombre)
     + (SELECT COUNT(*) FROM usuario
         WHERE correo IN ('vortice@ejemplo.invalid', 'clubsur@ejemplo.invalid', 'cerro@ejemplo.invalid'))
     + (SELECT COUNT(*) FROM (SELECT 1 FROM torneo
                               WHERE estado IN ('borrador', 'inscripcion', 'en_curso')
                               GROUP BY nombre HAVING COUNT(*) > 1) repetidos);
DROP TEMPORARY TABLE control_005_nombres;

-- 3. Estructura.
ALTER TABLE usuario
  ADD COLUMN IF NOT EXISTS de_muestra TINYINT(1) NOT NULL DEFAULT 0 AFTER foto_portada;
ALTER TABLE usuario
  ADD CONSTRAINT IF NOT EXISTS ck_usuario_muestra CHECK (de_muestra IN (0, 1));

ALTER TABLE torneo MODIFY fecha_inicio DATE NULL;
ALTER TABLE torneo
  ADD COLUMN IF NOT EXISTS nombre_vigente VARCHAR(80) GENERATED ALWAYS AS
    (IF(estado IN ('borrador', 'inscripcion', 'en_curso'), nombre, NULL)) STORED AFTER fecha_creacion;
ALTER TABLE torneo
  ADD UNIQUE KEY IF NOT EXISTS uq_torneo_vigente (nombre_vigente);

ALTER TABLE configuracion_torneo
  ADD COLUMN IF NOT EXISTS criterio_desempate VARCHAR(10) NOT NULL DEFAULT 'diferencia' AFTER ida_y_vuelta;
ALTER TABLE configuracion_torneo
  ADD CONSTRAINT IF NOT EXISTS ck_config_desempate CHECK (criterio_desempate IN ('diferencia', 'favor'));

CREATE TABLE IF NOT EXISTS pedido_inscripcion (
  id_pedido_inscripcion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_torneo             INT UNSIGNED NOT NULL,
  id_equipo             INT UNSIGNED NOT NULL,
  id_usuario            INT UNSIGNED NOT NULL,
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

-- La lista de acciones de la auditoria, entera.
ALTER TABLE auditoria DROP CONSTRAINT IF EXISTS ck_audit_accion;
ALTER TABLE auditoria
  ADD CONSTRAINT ck_audit_accion CHECK (accion IN ('alta', 'baja', 'modificacion',
                                                   'login_ok', 'login_error', 'logout',
                                                   'pedido_rol', 'aprobacion', 'rechazo',
                                                   'inscripcion', 'solicitud', 'cierre', 'fixture'));

UPDATE disciplina         SET nombre = 'Fútbol'              WHERE nombre = 'Futbol';
UPDATE modulo_competencia SET nombre = 'Eliminación directa' WHERE nombre = 'Eliminacion directa';
INSERT INTO disciplina (nombre)
SELECT 'Fútbol 5' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM disciplina WHERE nombre = 'Fútbol 5');

-- [solo servidor propio] desde aca
GRANT INSERT, UPDATE ON pedido_inscripcion TO 'sgdm_app'@'localhost';
-- [solo servidor propio] hasta aca

-- 5. Los datos de muestra, todo o nada.
START TRANSACTION;

-- Las tres cuentas organizadoras.
INSERT INTO usuario (correo, hash_password, nombre, apellido, alias, presentacion,
                     activo, fecha_alta, de_muestra) VALUES
  ('vortice@ejemplo.invalid', '!cuenta-de-muestra:sin-clave', 'Comunidad',      'Vórtice', NULL,
   'Cuenta de muestra.', 1, '2026-08-01 09:00:00', 1),
  ('clubsur@ejemplo.invalid', '!cuenta-de-muestra:sin-clave', 'Club',           'Sur',     NULL,
   'Cuenta de muestra.', 1, '2026-08-01 09:00:00', 1),
  ('cerro@ejemplo.invalid',   '!cuenta-de-muestra:sin-clave', 'Centro Juvenil', 'Cerro',   NULL,
   'Cuenta de muestra.', 1, '2026-08-01 09:00:00', 1);

INSERT INTO usuario_rol (id_usuario, id_rol, fecha_asignacion)
SELECT u.id_usuario, r.id_rol, u.fecha_alta
  FROM usuario u
  JOIN rol r ON r.nombre = 'organizador'
 WHERE u.de_muestra = 1
   AND u.correo IN ('vortice@ejemplo.invalid', 'clubsur@ejemplo.invalid', 'cerro@ejemplo.invalid');

-- Las tres ligas.
INSERT INTO torneo (nombre, id_disciplina, id_tipo_torneo, id_modulo, id_usuario_organizador,
                    fecha_inicio, fecha_fin, max_participantes, sede, estado, fecha_creacion)
SELECT 'Liga Valorant · Otoño', d.id_disciplina, tt.id_tipo_torneo, m.id_modulo, u.id_usuario,
       '2026-08-22', '2026-10-25', 12, 'Montevideo (online)', 'en_curso', '2026-08-01 09:30:00'
  FROM disciplina d, tipo_torneo tt, modulo_competencia m, usuario u
 WHERE d.nombre = 'Esports' AND tt.nombre = 'Por equipos' AND m.nombre = 'Liga'
   AND u.correo = 'vortice@ejemplo.invalid';

INSERT INTO torneo (nombre, id_disciplina, id_tipo_torneo, id_modulo, id_usuario_organizador,
                    fecha_inicio, fecha_fin, max_participantes, sede, estado, fecha_creacion)
SELECT 'Liga Barrial del Cerro', d.id_disciplina, tt.id_tipo_torneo, m.id_modulo, u.id_usuario,
       '2026-08-29', '2026-10-25', 10, NULL, 'en_curso', '2026-08-05 09:30:00'
  FROM disciplina d, tipo_torneo tt, modulo_competencia m, usuario u
 WHERE d.nombre = 'Fútbol 5' AND tt.nombre = 'Por equipos' AND m.nombre = 'Liga'
   AND u.correo = 'cerro@ejemplo.invalid';

INSERT INTO torneo (nombre, id_disciplina, id_tipo_torneo, id_modulo, id_usuario_organizador,
                    fecha_inicio, fecha_fin, max_participantes, sede, estado, fecha_creacion)
SELECT 'Liga Interna Club Sur', d.id_disciplina, tt.id_tipo_torneo, m.id_modulo, u.id_usuario,
       '2026-10-04', NULL, 12, NULL, 'inscripcion', '2026-09-10 09:30:00'
  FROM disciplina d, tipo_torneo tt, modulo_competencia m, usuario u
 WHERE d.nombre = 'Fútbol' AND tt.nombre = 'Por equipos' AND m.nombre = 'Liga'
   AND u.correo = 'clubsur@ejemplo.invalid';

-- Su configuracion: 3/1/0 en las tres, una vuelta, desempate por diferencia y despues a favor.
INSERT INTO configuracion_torneo (id_torneo, puntos_victoria, puntos_empate, puntos_derrota,
                                  admite_empate, clasifican_playoffs, ida_y_vuelta,
                                  criterio_desempate, rondas_previstas, reglas)
SELECT id_torneo, 3, 1, 0, 0, 4, 0, 'diferencia', 11,
       'Series al mejor de 3 mapas: gana la serie quien se queda con dos, así que no hay empates. Un equipo que no se presenta a la hora pactada pierde el enfrentamiento por walkover, sin necesidad de jugarlo.'
  FROM torneo WHERE nombre = 'Liga Valorant · Otoño';

INSERT INTO configuracion_torneo (id_torneo, puntos_victoria, puntos_empate, puntos_derrota,
                                  admite_empate, clasifican_playoffs, ida_y_vuelta,
                                  criterio_desempate, rondas_previstas, reglas)
SELECT id_torneo, 3, 1, 0, 1, 0, 0, 'diferencia', 9, NULL
  FROM torneo WHERE nombre = 'Liga Barrial del Cerro';

INSERT INTO configuracion_torneo (id_torneo, puntos_victoria, puntos_empate, puntos_derrota,
                                  admite_empate, clasifican_playoffs, ida_y_vuelta,
                                  criterio_desempate, rondas_previstas, reglas)
SELECT id_torneo, 3, 1, 0, 1, 0, 0, 'diferencia', NULL, NULL
  FROM torneo WHERE nombre = 'Liga Interna Club Sur';

-- Los equipos: 12 de la Liga Valorant, 10 de la Liga Barrial y 9 de la Liga Interna.
INSERT INTO equipo (nombre, ciudad, fecha_alta)
SELECT nombre, ciudad, alta FROM carga_005_equipo;

CREATE TEMPORARY TABLE carga_005_inscripcion (orden SMALLINT, torneo VARCHAR(80), equipo VARCHAR(40), fecha DATETIME)
  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO carga_005_inscripcion VALUES
  (1, 'Liga Valorant · Otoño', 'Vortex', '2026-08-15 12:00:00'),
  (2, 'Liga Valorant · Otoño', 'Sur Gaming', '2026-08-15 12:00:00'),
  (3, 'Liga Valorant · Otoño', 'Halcones', '2026-08-15 12:00:00'),
  (4, 'Liga Valorant · Otoño', 'Delta Gaming', '2026-08-15 12:00:00'),
  (5, 'Liga Valorant · Otoño', 'Titanes CS', '2026-08-15 12:00:00'),
  (6, 'Liga Valorant · Otoño', 'Nova Esports', '2026-08-15 12:00:00'),
  (7, 'Liga Valorant · Otoño', 'Aurora FC', '2026-08-15 12:00:00'),
  (8, 'Liga Valorant · Otoño', 'Liceo 3', '2026-08-15 12:00:00'),
  (9, 'Liga Valorant · Otoño', 'Ping Masters', '2026-08-15 12:00:00'),
  (10, 'Liga Valorant · Otoño', 'Rambla Esports', '2026-08-15 12:00:00'),
  (11, 'Liga Valorant · Otoño', 'Faro Gaming', '2026-08-15 12:00:00'),
  (12, 'Liga Valorant · Otoño', 'Atlántida GG', '2026-08-15 12:00:00'),
  (13, 'Liga Barrial del Cerro', 'Cerro FC', '2026-08-20 12:00:00'),
  (14, 'Liga Barrial del Cerro', 'Casabó', '2026-08-20 12:00:00'),
  (15, 'Liga Barrial del Cerro', 'Villa del Cerro', '2026-08-20 12:00:00'),
  (16, 'Liga Barrial del Cerro', 'Cerro Norte', '2026-08-20 12:00:00'),
  (17, 'Liga Barrial del Cerro', 'Paso de la Arena', '2026-08-20 12:00:00'),
  (18, 'Liga Barrial del Cerro', 'Santa Catalina', '2026-08-20 12:00:00'),
  (19, 'Liga Barrial del Cerro', 'La Teja', '2026-08-20 12:00:00'),
  (20, 'Liga Barrial del Cerro', 'Tres Ombúes', '2026-08-20 12:00:00'),
  (21, 'Liga Barrial del Cerro', 'Nuevo París', '2026-08-20 12:00:00'),
  (22, 'Liga Barrial del Cerro', 'Belvedere', '2026-08-20 12:00:00'),
  (23, 'Liga Interna Club Sur', 'Club Sur A', '2026-09-15 12:00:00'),
  (24, 'Liga Interna Club Sur', 'Club Sur B', '2026-09-15 12:00:00'),
  (25, 'Liga Interna Club Sur', 'Club Sur C', '2026-09-15 12:00:00'),
  (26, 'Liga Interna Club Sur', 'Club Sur D', '2026-09-15 12:00:00'),
  (27, 'Liga Interna Club Sur', 'Veteranos del Sur', '2026-09-15 12:00:00'),
  (28, 'Liga Interna Club Sur', 'Sub 20 del Sur', '2026-09-15 12:00:00'),
  (29, 'Liga Interna Club Sur', 'Amigos del Sur', '2026-09-15 12:00:00'),
  (30, 'Liga Interna Club Sur', 'Sur Femenino', '2026-09-15 12:00:00'),
  (31, 'Liga Interna Club Sur', 'Sur Mixto', '2026-09-15 12:00:00');
INSERT INTO participante (id_torneo, id_equipo, estado, fecha_inscripcion)
SELECT t.id_torneo, e.id_equipo, 'inscripto', c.fecha
  FROM carga_005_inscripcion c
  JOIN torneo t ON t.nombre = c.torneo
  JOIN equipo e ON e.nombre = c.equipo
 ORDER BY c.orden;

-- Rondas (fechas) de las dos ligas que ya empezaron.
CREATE TEMPORARY TABLE carga_005_ronda (torneo VARCHAR(80), numero TINYINT UNSIGNED, inicio DATE, fin DATE, estado VARCHAR(12))
  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO carga_005_ronda VALUES
  ('Liga Valorant · Otoño', 1, '2026-08-22', '2026-08-24', 'cerrada'),
  ('Liga Valorant · Otoño', 2, '2026-08-26', '2026-08-28', 'cerrada'),
  ('Liga Valorant · Otoño', 3, '2026-08-30', '2026-09-01', 'cerrada'),
  ('Liga Valorant · Otoño', 4, '2026-09-03', '2026-09-05', 'cerrada'),
  ('Liga Valorant · Otoño', 5, '2026-09-07', '2026-09-09', 'cerrada'),
  ('Liga Valorant · Otoño', 6, '2026-09-11', '2026-09-13', 'cerrada'),
  ('Liga Valorant · Otoño', 7, '2026-09-15', '2026-09-17', 'cerrada'),
  ('Liga Valorant · Otoño', 8, '2026-09-18', '2026-09-20', 'en_curso'),
  ('Liga Valorant · Otoño', 9, '2026-09-25', '2026-09-27', 'pendiente'),
  ('Liga Valorant · Otoño', 10, '2026-10-09', '2026-10-11', 'pendiente'),
  ('Liga Valorant · Otoño', 11, '2026-10-23', '2026-10-25', 'pendiente'),
  ('Liga Barrial del Cerro', 1, '2026-08-29', '2026-08-30', 'cerrada'),
  ('Liga Barrial del Cerro', 2, '2026-09-05', '2026-09-06', 'cerrada'),
  ('Liga Barrial del Cerro', 3, '2026-09-12', '2026-09-13', 'cerrada'),
  ('Liga Barrial del Cerro', 4, '2026-09-19', '2026-09-20', 'en_curso'),
  ('Liga Barrial del Cerro', 5, '2026-09-26', '2026-09-27', 'pendiente'),
  ('Liga Barrial del Cerro', 6, '2026-10-03', '2026-10-04', 'pendiente'),
  ('Liga Barrial del Cerro', 7, '2026-10-10', '2026-10-11', 'pendiente'),
  ('Liga Barrial del Cerro', 8, '2026-10-17', '2026-10-18', 'pendiente'),
  ('Liga Barrial del Cerro', 9, '2026-10-24', '2026-10-25', 'pendiente');
INSERT INTO ronda (id_torneo, numero, nombre, fecha_inicio, fecha_fin, estado)
SELECT t.id_torneo, c.numero, CONCAT('Fecha ', c.numero), c.inicio, c.fin, c.estado
  FROM carga_005_ronda c
  JOIN torneo t ON t.nombre = c.torneo
 ORDER BY t.id_torneo, c.numero;

CREATE TEMPORARY TABLE carga_005_partido (torneo VARCHAR(80), ronda TINYINT UNSIGNED, numero TINYINT UNSIGNED,
  local VARCHAR(40), visitante VARCHAR(40), fecha_hora DATETIME, estado VARCHAR(12),
  puntaje_local SMALLINT, puntaje_visitante SMALLINT)
  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO carga_005_partido VALUES
  ('Liga Valorant · Otoño', 1, 1, 'Vortex', 'Atlántida GG', '2026-08-22 19:00:00', 'jugado', 2, 0),
  ('Liga Valorant · Otoño', 1, 2, 'Sur Gaming', 'Faro Gaming', '2026-08-22 20:30:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 1, 3, 'Halcones', 'Rambla Esports', '2026-08-22 22:00:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 1, 4, 'Delta Gaming', 'Ping Masters', '2026-08-23 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 1, 5, 'Titanes CS', 'Liceo 3', '2026-08-23 20:30:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 1, 6, 'Nova Esports', 'Aurora FC', '2026-08-24 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 2, 1, 'Faro Gaming', 'Vortex', '2026-08-26 19:00:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 2, 2, 'Atlántida GG', 'Rambla Esports', '2026-08-26 20:30:00', 'jugado', 2, 0),
  ('Liga Valorant · Otoño', 2, 3, 'Sur Gaming', 'Ping Masters', '2026-08-26 22:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 2, 4, 'Halcones', 'Liceo 3', '2026-08-27 19:00:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 2, 5, 'Delta Gaming', 'Aurora FC', '2026-08-27 20:30:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 2, 6, 'Titanes CS', 'Nova Esports', '2026-08-28 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 3, 1, 'Vortex', 'Rambla Esports', '2026-08-30 19:00:00', 'jugado', 2, 0),
  ('Liga Valorant · Otoño', 3, 2, 'Faro Gaming', 'Ping Masters', '2026-08-30 20:30:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 3, 3, 'Atlántida GG', 'Liceo 3', '2026-08-30 22:00:00', 'jugado', 2, 0),
  ('Liga Valorant · Otoño', 3, 4, 'Sur Gaming', 'Aurora FC', '2026-08-31 19:00:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 3, 5, 'Halcones', 'Nova Esports', '2026-08-31 20:30:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 3, 6, 'Delta Gaming', 'Titanes CS', '2026-09-01 19:00:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 4, 1, 'Ping Masters', 'Vortex', '2026-09-03 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 4, 2, 'Rambla Esports', 'Liceo 3', '2026-09-03 20:30:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 4, 3, 'Faro Gaming', 'Aurora FC', '2026-09-03 22:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 4, 4, 'Atlántida GG', 'Nova Esports', '2026-09-04 19:00:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 4, 5, 'Sur Gaming', 'Titanes CS', '2026-09-04 20:30:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 4, 6, 'Halcones', 'Delta Gaming', '2026-09-05 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 5, 1, 'Vortex', 'Liceo 3', '2026-09-07 19:00:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 5, 2, 'Ping Masters', 'Aurora FC', '2026-09-07 20:30:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 5, 3, 'Rambla Esports', 'Nova Esports', '2026-09-07 22:00:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 5, 4, 'Faro Gaming', 'Titanes CS', '2026-09-08 19:00:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 5, 5, 'Atlántida GG', 'Delta Gaming', '2026-09-08 20:30:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 5, 6, 'Sur Gaming', 'Halcones', '2026-09-09 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 6, 1, 'Aurora FC', 'Vortex', '2026-09-11 19:00:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 6, 2, 'Liceo 3', 'Nova Esports', '2026-09-11 20:30:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 6, 3, 'Ping Masters', 'Titanes CS', '2026-09-11 22:00:00', 'jugado', 0, 2),
  ('Liga Valorant · Otoño', 6, 4, 'Rambla Esports', 'Delta Gaming', '2026-09-12 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 6, 5, 'Faro Gaming', 'Halcones', '2026-09-12 20:30:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 6, 6, 'Atlántida GG', 'Sur Gaming', '2026-09-13 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 7, 1, 'Vortex', 'Nova Esports', '2026-09-15 19:00:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 7, 2, 'Aurora FC', 'Titanes CS', '2026-09-15 20:30:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 7, 3, 'Liceo 3', 'Delta Gaming', '2026-09-15 22:00:00', 'jugado', 1, 2),
  ('Liga Valorant · Otoño', 7, 4, 'Ping Masters', 'Halcones', '2026-09-16 19:00:00', 'jugado', 2, 1),
  ('Liga Valorant · Otoño', 7, 5, 'Rambla Esports', 'Sur Gaming', '2026-09-16 20:30:00', 'jugado', 2, 0),
  ('Liga Valorant · Otoño', 7, 6, 'Faro Gaming', 'Atlántida GG', '2026-09-17 19:00:00', 'jugado', 2, 0),
  ('Liga Valorant · Otoño', 8, 1, 'Titanes CS', 'Vortex', '2026-09-18 19:00:00', 'en_vivo', NULL, NULL),
  ('Liga Valorant · Otoño', 8, 2, 'Nova Esports', 'Delta Gaming', '2026-09-18 20:30:00', 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 8, 3, 'Aurora FC', 'Halcones', '2026-09-20 17:00:00', 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 8, 4, 'Liceo 3', 'Sur Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 8, 5, 'Ping Masters', 'Atlántida GG', '2026-09-19 20:00:00', 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 8, 6, 'Rambla Esports', 'Faro Gaming', '2026-09-20 19:00:00', 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 9, 1, 'Vortex', 'Delta Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 9, 2, 'Titanes CS', 'Halcones', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 9, 3, 'Nova Esports', 'Sur Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 9, 4, 'Aurora FC', 'Atlántida GG', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 9, 5, 'Liceo 3', 'Faro Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 9, 6, 'Ping Masters', 'Rambla Esports', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 10, 1, 'Halcones', 'Vortex', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 10, 2, 'Delta Gaming', 'Sur Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 10, 3, 'Titanes CS', 'Atlántida GG', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 10, 4, 'Nova Esports', 'Faro Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 10, 5, 'Aurora FC', 'Rambla Esports', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 10, 6, 'Liceo 3', 'Ping Masters', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 11, 1, 'Vortex', 'Sur Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 11, 2, 'Halcones', 'Atlántida GG', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 11, 3, 'Delta Gaming', 'Faro Gaming', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 11, 4, 'Titanes CS', 'Rambla Esports', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 11, 5, 'Nova Esports', 'Ping Masters', NULL, 'programado', NULL, NULL),
  ('Liga Valorant · Otoño', 11, 6, 'Aurora FC', 'Liceo 3', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 1, 1, 'Cerro FC', 'Belvedere', '2026-08-29 19:30:00', 'jugado', 1, 3),
  ('Liga Barrial del Cerro', 1, 2, 'Casabó', 'Nuevo París', '2026-08-29 18:00:00', 'jugado', 5, 5),
  ('Liga Barrial del Cerro', 1, 3, 'Villa del Cerro', 'Tres Ombúes', '2026-08-29 21:00:00', 'jugado', 1, 2),
  ('Liga Barrial del Cerro', 1, 4, 'Cerro Norte', 'La Teja', '2026-08-30 17:00:00', 'jugado', 6, 5),
  ('Liga Barrial del Cerro', 1, 5, 'Paso de la Arena', 'Santa Catalina', '2026-08-30 18:30:00', 'jugado', 3, 5),
  ('Liga Barrial del Cerro', 2, 1, 'Nuevo París', 'Cerro FC', '2026-09-05 19:30:00', 'jugado', 4, 4),
  ('Liga Barrial del Cerro', 2, 2, 'Belvedere', 'Tres Ombúes', '2026-09-05 18:00:00', 'jugado', 2, 0),
  ('Liga Barrial del Cerro', 2, 3, 'Casabó', 'La Teja', '2026-09-05 21:00:00', 'jugado', 6, 1),
  ('Liga Barrial del Cerro', 2, 4, 'Villa del Cerro', 'Santa Catalina', '2026-09-06 17:00:00', 'jugado', 1, 2),
  ('Liga Barrial del Cerro', 2, 5, 'Cerro Norte', 'Paso de la Arena', '2026-09-06 18:30:00', 'jugado', 1, 4),
  ('Liga Barrial del Cerro', 3, 1, 'Cerro FC', 'Tres Ombúes', '2026-09-12 19:30:00', 'jugado', 0, 4),
  ('Liga Barrial del Cerro', 3, 2, 'Nuevo París', 'La Teja', '2026-09-12 18:00:00', 'jugado', 3, 2),
  ('Liga Barrial del Cerro', 3, 3, 'Belvedere', 'Santa Catalina', '2026-09-12 21:00:00', 'jugado', 3, 2),
  ('Liga Barrial del Cerro', 3, 4, 'Casabó', 'Paso de la Arena', '2026-09-13 17:00:00', 'jugado', 3, 3),
  ('Liga Barrial del Cerro', 3, 5, 'Villa del Cerro', 'Cerro Norte', '2026-09-13 18:30:00', 'jugado', 3, 5),
  ('Liga Barrial del Cerro', 4, 1, 'La Teja', 'Cerro FC', '2026-09-19 19:30:00', 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 4, 2, 'Tres Ombúes', 'Santa Catalina', '2026-09-19 18:00:00', 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 4, 3, 'Nuevo París', 'Paso de la Arena', '2026-09-19 21:00:00', 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 4, 4, 'Belvedere', 'Cerro Norte', '2026-09-20 17:00:00', 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 4, 5, 'Casabó', 'Villa del Cerro', '2026-09-20 18:30:00', 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 5, 1, 'Cerro FC', 'Santa Catalina', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 5, 2, 'La Teja', 'Paso de la Arena', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 5, 3, 'Tres Ombúes', 'Cerro Norte', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 5, 4, 'Nuevo París', 'Villa del Cerro', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 5, 5, 'Belvedere', 'Casabó', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 6, 1, 'Paso de la Arena', 'Cerro FC', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 6, 2, 'Santa Catalina', 'Cerro Norte', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 6, 3, 'La Teja', 'Villa del Cerro', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 6, 4, 'Tres Ombúes', 'Casabó', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 6, 5, 'Nuevo París', 'Belvedere', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 7, 1, 'Cerro FC', 'Cerro Norte', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 7, 2, 'Paso de la Arena', 'Villa del Cerro', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 7, 3, 'Santa Catalina', 'Casabó', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 7, 4, 'La Teja', 'Belvedere', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 7, 5, 'Tres Ombúes', 'Nuevo París', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 8, 1, 'Villa del Cerro', 'Cerro FC', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 8, 2, 'Cerro Norte', 'Casabó', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 8, 3, 'Paso de la Arena', 'Belvedere', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 8, 4, 'Santa Catalina', 'Nuevo París', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 8, 5, 'La Teja', 'Tres Ombúes', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 9, 1, 'Cerro FC', 'Casabó', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 9, 2, 'Villa del Cerro', 'Belvedere', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 9, 3, 'Cerro Norte', 'Nuevo París', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 9, 4, 'Paso de la Arena', 'Tres Ombúes', NULL, 'programado', NULL, NULL),
  ('Liga Barrial del Cerro', 9, 5, 'Santa Catalina', 'La Teja', NULL, 'programado', NULL, NULL);
INSERT INTO enfrentamiento (id_ronda, numero, id_participante_local, id_participante_visitante,
                            fecha_hora, estado)
SELECT r.id_ronda, c.numero, pl.id_participante, pv.id_participante, c.fecha_hora, c.estado
  FROM carga_005_partido c
  JOIN torneo t        ON t.nombre = c.torneo
  JOIN ronda r         ON r.id_torneo = t.id_torneo AND r.numero = c.ronda
  JOIN equipo el       ON el.nombre = c.local
  JOIN participante pl ON pl.id_torneo = t.id_torneo AND pl.id_equipo = el.id_equipo
  JOIN equipo ev       ON ev.nombre = c.visitante
  JOIN participante pv ON pv.id_torneo = t.id_torneo AND pv.id_equipo = ev.id_equipo
 ORDER BY r.id_ronda, c.numero;

-- Resultados de los jugados.
INSERT INTO resultado (id_enfrentamiento, puntaje_local, puntaje_visitante,
                       id_participante_ganador, walkover, id_usuario_carga, fecha_carga)
SELECT en.id_enfrentamiento, c.puntaje_local, c.puntaje_visitante,
       CASE WHEN c.puntaje_local > c.puntaje_visitante THEN en.id_participante_local
            WHEN c.puntaje_local < c.puntaje_visitante THEN en.id_participante_visitante
       END,
       0, t.id_usuario_organizador, c.fecha_hora + INTERVAL 3 HOUR
  FROM carga_005_partido c
  JOIN torneo t          ON t.nombre = c.torneo
  JOIN ronda r           ON r.id_torneo = t.id_torneo AND r.numero = c.ronda
  JOIN enfrentamiento en ON en.id_ronda = r.id_ronda AND en.numero = c.numero
 WHERE c.puntaje_local IS NOT NULL;

INSERT INTO tabla_posiciones (id_participante, ganados, empatados, perdidos,
                              favor, contra, fecha_actualizacion)
SELECT pa.id_participante,
       SUM(CASE WHEN x.propio > x.ajeno THEN 1 ELSE 0 END),
       SUM(CASE WHEN x.propio = x.ajeno THEN 1 ELSE 0 END),
       SUM(CASE WHEN x.propio < x.ajeno THEN 1 ELSE 0 END),
       SUM(x.propio), SUM(x.ajeno), MAX(x.fecha_carga)
  FROM participante pa
  JOIN torneo t ON t.id_torneo = pa.id_torneo
  JOIN (SELECT en.id_participante_local AS id_participante,
               r.puntaje_local AS propio, r.puntaje_visitante AS ajeno, r.fecha_carga
          FROM resultado r
          JOIN enfrentamiento en ON en.id_enfrentamiento = r.id_enfrentamiento
        UNION ALL
        SELECT en.id_participante_visitante,
               r.puntaje_visitante, r.puntaje_local, r.fecha_carga
          FROM resultado r
          JOIN enfrentamiento en ON en.id_enfrentamiento = r.id_enfrentamiento) x
    ON x.id_participante = pa.id_participante
 WHERE t.nombre IN ('Liga Valorant · Otoño', 'Liga Barrial del Cerro')
 GROUP BY pa.id_participante;

-- Una fila en la auditoria, sin cuenta: la carga no la hace nadie desde la aplicacion.
INSERT INTO auditoria (id_usuario, tabla_afectada, id_registro, accion, detalle)
VALUES (NULL, 'torneo', NULL, 'alta',
        '3 cuentas, 3 ligas, 31 equipos');

COMMIT;

DROP TEMPORARY TABLE carga_005_equipo;
DROP TEMPORARY TABLE carga_005_inscripcion;
DROP TEMPORARY TABLE carga_005_ronda;
DROP TEMPORARY TABLE carga_005_partido;

-- 6. El resultado.
SELECT
    (SELECT COUNT(*) FROM usuario WHERE de_muestra = 1)                  AS cuentas_de_muestra,
    (SELECT COUNT(*) FROM usuario
      WHERE de_muestra = 1 AND hash_password LIKE '$%')                  AS claves_usables,
    (SELECT COUNT(*) FROM torneo t
       JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
      WHERE u.de_muestra = 1)                                            AS ligas_de_muestra,
    (SELECT GROUP_CONCAT(t.estado ORDER BY t.nombre SEPARATOR ' / ')
       FROM torneo t
       JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
      WHERE u.de_muestra = 1)                                            AS estados,
    (SELECT COUNT(DISTINCT pa.id_equipo) FROM participante pa
       JOIN torneo t  ON t.id_torneo = pa.id_torneo
       JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
      WHERE u.de_muestra = 1)                                            AS equipos,
    (SELECT COUNT(*) FROM enfrentamiento en
       JOIN ronda r   ON r.id_ronda = en.id_ronda
       JOIN torneo t  ON t.id_torneo = r.id_torneo
       JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
      WHERE u.de_muestra = 1)                                            AS partidos,
    (SELECT COUNT(*) FROM enfrentamiento en
       JOIN resultado re ON re.id_enfrentamiento = en.id_enfrentamiento
       JOIN ronda r   ON r.id_ronda = en.id_ronda
       JOIN torneo t  ON t.id_torneo = r.id_torneo
       JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
      WHERE u.de_muestra = 1 AND en.estado = 'jugado')                   AS jugados,
    (SELECT COUNT(*) FROM enfrentamiento en
       JOIN ronda r   ON r.id_ronda = en.id_ronda
       JOIN torneo t  ON t.id_torneo = r.id_torneo
       JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
      WHERE u.de_muestra = 1 AND en.estado = 'en_vivo')                  AS en_vivo,
    (SELECT COUNT(*) FROM tabla_posiciones p
       JOIN participante pa ON pa.id_participante = p.id_participante
       JOIN torneo t  ON t.id_torneo = pa.id_torneo
       JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
      WHERE u.de_muestra = 1)                                            AS filas_tabla,
    (SELECT CONCAT(e.nombre, ' · ', p.ganados * 3 + p.empatados, ' pts · ',
                   IF(p.favor >= p.contra, '+', ''), p.favor - p.contra)
       FROM tabla_posiciones p
       JOIN participante pa ON pa.id_participante = p.id_participante
       JOIN equipo e  ON e.id_equipo = pa.id_equipo
       JOIN torneo t  ON t.id_torneo = pa.id_torneo
      WHERE t.nombre = 'Liga Valorant · Otoño'
      ORDER BY p.ganados * 3 + p.empatados DESC, p.favor - p.contra DESC, p.favor DESC, e.nombre
      LIMIT 1)                                                           AS puntero,
    (SELECT CONCAT(e.nombre, ' · ', p.ganados * 3 + p.empatados, ' pts · ',
                   IF(p.favor >= p.contra, '+', ''), p.favor - p.contra)
       FROM tabla_posiciones p
       JOIN participante pa ON pa.id_participante = p.id_participante
       JOIN equipo e  ON e.id_equipo = pa.id_equipo
       JOIN torneo t  ON t.id_torneo = pa.id_torneo
      WHERE t.nombre = 'Liga Valorant · Otoño'
      ORDER BY p.ganados * 3 + p.empatados DESC, p.favor - p.contra DESC, p.favor DESC, e.nombre
      LIMIT 1 OFFSET 7)                                                  AS octavo,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE')     AS tablas,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK')        AS restricciones_check,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY')  AS claves_foraneas,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'UNIQUE')       AS indices_unicos;
