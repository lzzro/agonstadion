-- Migracion 008: los modulos de competencia se pueden habilitar y deshabilitar (RF-24).
-- Necesita la 007; frena sin cambiar nada si falta. Se puede correr dos veces.
-- Al final tiene que mostrar columna_habilitado 1 y 38 restricciones CHECK.

SET NAMES utf8mb4;

-- 1. Lo que tiene que estar antes.
SELECT COUNT(*) AS mediciones FROM medicion;

-- 2. La columna y su restriccion.
ALTER TABLE modulo_competencia
  ADD COLUMN IF NOT EXISTS habilitado TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion;
ALTER TABLE modulo_competencia
  ADD CONSTRAINT IF NOT EXISTS ck_modulo_habilitado CHECK (habilitado IN (0, 1));

-- 3. Comprobacion.
SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modulo_competencia'
        AND COLUMN_NAME = 'habilitado')                                    AS columna_habilitado,
    (SELECT COUNT(*) FROM modulo_competencia WHERE habilitado = 1)         AS modulos_habilitados,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK') AS restricciones_check;

-- [solo servidor propio] desde aca
GRANT UPDATE (habilitado) ON sgdm.modulo_competencia TO 'sgdm_app'@'localhost';
-- [solo servidor propio] hasta aca
