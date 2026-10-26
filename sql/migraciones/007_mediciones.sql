-- Migracion 007: la tabla medicion (rendimiento fisico, RF-29).
-- Necesita la 006; frena sin cambiar nada si falta. Se puede correr dos veces.
-- Al final tiene que mostrar tabla_medicion 1 y 37 restricciones CHECK.

SET NAMES utf8mb4;

-- 1. Lo que tiene que estar antes.
SELECT COUNT(mejor_de) AS series FROM configuracion_torneo;

-- 2. La tabla.
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

-- 3. Comprobacion.
SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medicion')        AS tabla_medicion,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK') AS restricciones_check;

-- [solo servidor propio] desde aca
-- 4. El permiso de sgdm_app: guardar y borrar mediciones (el SELECT ya lo tiene sobre toda la base).
GRANT INSERT, DELETE ON sgdm.medicion TO 'sgdm_app'@'localhost';
-- [solo servidor propio] hasta aca
