-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- Migracion 007: rendimiento fisico (RF-29, el modulo de Fisica)
-- ---------------------------------------------------------------------
-- La tabla medicion: las pruebas fisicas de cada cuenta, con sus datos
-- crudos y su fecha. Los indicadores (tiempo de reaccion, aceleracion de
-- salida, potencia de salto) no se guardan: los calcula la clase
-- Medicion con las formulas de Fisica, la misma decision que los puntos
-- de la tabla de posiciones. Una base creada con el schema.sql de ahora
-- ya la trae, y en ella esta migracion no cambia nada.
--
-- ORDEN, en una base que viene de antes: las que falten hasta la 006, y
-- despues esta. Frena sin cambiar nada si falta la 006 (ver "Que
-- esperar").
--
-- ANTES DE CORRERLA: un respaldo. En phpMyAdmin, con la base elegida,
-- Export -> Quick -> SQL -> Go.
--
-- Como se corre, a mano, una vez en cada base (igual que las otras):
--   1. En phpMyAdmin, elegir la base (sgdm en XAMPP). Sin USE, a proposito.
--   2. Pestana SQL (o Import), pegar o subir este archivo, y Go. El
--      bloque del final (el permiso de sgdm_app) pide la cuenta de
--      administracion de la base.
--   3. Con la cuenta con la que se administra la base (root en XAMPP
--      o en la VM).
--
-- Que esperar:
--   - Si falta la 006, la primera consulta da "Unknown column
--     'mejor_de'" y no se cambia nada: correr la 006 y despues esta.
--   - Se puede correr dos veces: la tabla ya esta y no se repite.
--   - La ultima consulta tiene que mostrar:
--         tabla_medicion       1
--         restricciones_check  37
--
-- Necesita MariaDB 10.2 o superior. Probada en MariaDB 10.11 (la de la
-- VM), sobre una base con la 006 y sobre una base nueva, y dos veces.
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Lo que tiene que estar antes. Nada de esto cambia la base.
-- ---------------------------------------------------------------------
-- La 006: si falta mejor_de, esto da error y frena.
SELECT COUNT(mejor_de) AS series FROM configuracion_torneo;


-- ---------------------------------------------------------------------
-- 2. La tabla. El porque de cada restriccion esta en schema.sql.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS medicion (
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


-- ---------------------------------------------------------------------
-- 3. Comprobacion. Solo mira; se puede correr sola.
-- ---------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medicion')        AS tabla_medicion,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK') AS restricciones_check;


-- [solo servidor propio] desde aca
-- ---------------------------------------------------------------------
-- 4. El permiso de sgdm_app: guardar y borrar mediciones (el SELECT ya
--    lo tiene sobre toda la base).
-- ---------------------------------------------------------------------
GRANT INSERT, DELETE ON sgdm.medicion TO 'sgdm_app'@'localhost';
-- [solo servidor propio] hasta aca
