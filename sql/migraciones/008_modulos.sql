-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- Migracion 008: modulos habilitados (RF-24, la administracion)
-- ---------------------------------------------------------------------
-- La columna modulo_competencia.habilitado: cada modulo de competencia
-- (Liga, Eliminacion directa, Suizo) se habilita o se deshabilita desde
-- la administracion. Uno deshabilitado no se ofrece en "Nuevo torneo";
-- los torneos que ya lo usan siguen igual. Arranca en 1: los tres
-- habilitados, como hasta ahora. Una base creada con el schema.sql de
-- ahora ya la trae, y en ella esta migracion no cambia nada.
--
-- ORDEN, en una base que viene de antes: las que falten hasta la 007, y
-- despues esta. Frena sin cambiar nada si falta la 007 (ver "Que
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
--   - Si falta la 007, la primera consulta da "Table ... medicion
--     doesn't exist" y no se cambia nada: correr la 007 y despues esta.
--   - Se puede correr dos veces: lo que agrega ya esta y no se repite.
--   - La ultima consulta tiene que mostrar:
--         columna_habilitado   1
--         modulos_habilitados  3 (o los que esten habilitados, si ya
--                              se corrio y se cambio alguno)
--         restricciones_check  38
--
-- Necesita MariaDB 10.2 o superior. Probada en MariaDB 10.11 (la de la
-- VM), sobre una base con la 007 y sobre una base nueva, y dos veces.
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Lo que tiene que estar antes. Nada de esto cambia la base.
-- ---------------------------------------------------------------------
-- La 007: si falta la tabla medicion, esto da error y frena.
SELECT COUNT(*) AS mediciones FROM medicion;


-- ---------------------------------------------------------------------
-- 2. La columna y su restriccion. Cada sentencia se puede repetir. El
--    porque esta en schema.sql.
-- ---------------------------------------------------------------------
ALTER TABLE modulo_competencia
  ADD COLUMN IF NOT EXISTS habilitado TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion;
ALTER TABLE modulo_competencia
  ADD CONSTRAINT IF NOT EXISTS ck_modulo_habilitado CHECK (habilitado IN (0, 1));


-- ---------------------------------------------------------------------
-- 3. Comprobacion. Solo mira; se puede correr sola.
-- ---------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modulo_competencia'
        AND COLUMN_NAME = 'habilitado')                                    AS columna_habilitado,
    (SELECT COUNT(*) FROM modulo_competencia WHERE habilitado = 1)         AS modulos_habilitados,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK') AS restricciones_check;


-- [solo servidor propio] desde aca
-- ---------------------------------------------------------------------
-- 4. El permiso de sgdm_app: cambiar solo esa columna (habilitar o
--    deshabilitar), no el nombre ni la descripcion de un modulo.
-- ---------------------------------------------------------------------
GRANT UPDATE (habilitado) ON sgdm.modulo_competencia TO 'sgdm_app'@'localhost';
-- [solo servidor propio] hasta aca
