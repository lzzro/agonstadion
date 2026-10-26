-- Migracion 004: la base y sus 17 tablas a utf8mb4 (utf8mb4_unicode_ci), sin perder datos.
-- Necesita la 003. Frena sin cambiar nada si falta o si hay valores que chocarian en un indice unico.
-- Al final tiene que mostrar 28 CHECK, 25 claves foraneas y 14 indices unicos.

-- 1. Que la 003 ya este: si falta pedido_rol, esto da error y frena.
SELECT COUNT(*) AS pedidos_de_rol FROM pedido_rol;

-- 2a. Los valores que chocarian en un indice unico.
SELECT 'rol.nombre' AS indice, GROUP_CONCAT(nombre SEPARATOR '  |  ') AS valores
  FROM rol
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'usuario.correo', GROUP_CONCAT(correo SEPARATOR '  |  ')
  FROM usuario
 GROUP BY CONVERT(correo USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'usuario.alias', GROUP_CONCAT(alias SEPARATOR '  |  ')
  FROM usuario WHERE alias IS NOT NULL
 GROUP BY CONVERT(alias USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'equipo.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM equipo
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'disciplina.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM disciplina
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'tipo_torneo.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM tipo_torneo
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'modulo_competencia.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM modulo_competencia
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'torneo.nombre + fecha_inicio', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM torneo
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci, fecha_inicio HAVING COUNT(*) > 1;

-- 2b. Y si hay alguno, se frena aca, antes de cambiar nada.
CREATE TEMPORARY TABLE control_004 (
  valores_que_chocan INT NOT NULL,
  CONSTRAINT ck_004_sin_choques CHECK (valores_que_chocan = 0)
);

INSERT INTO control_004 (valores_que_chocan)
SELECT
    (SELECT COUNT(*) FROM (SELECT 1 FROM rol
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM usuario
        GROUP BY CONVERT(correo USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM usuario WHERE alias IS NOT NULL
        GROUP BY CONVERT(alias USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM equipo
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM disciplina
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM tipo_torneo
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM modulo_competencia
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM torneo
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci, fecha_inicio HAVING COUNT(*) > 1) c);

DROP TEMPORARY TABLE control_004;

-- 3. La codificacion por defecto de la base.
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 4. Las 17 tablas, en el orden de schema.sql.
ALTER TABLE rol                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuario              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuario_rol          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE pedido_rol           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE equipo               CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE integrante_equipo    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE disciplina           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tipo_torneo          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE modulo_competencia   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE torneo               CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE configuracion_torneo CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                                 MODIFY reglas TEXT NULL;
ALTER TABLE participante         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ronda                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE enfrentamiento       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resultado            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tabla_posiciones     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE auditoria            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5. El resultado.
SELECT
    (SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA
      WHERE SCHEMA_NAME = DATABASE())                                   AS base,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
        AND TABLE_COLLATION = 'utf8mb4_unicode_ci')                     AS tablas_utf8mb4,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
        AND TABLE_COLLATION <> 'utf8mb4_unicode_ci')                    AS tablas_en_otra,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND COLLATION_NAME IS NOT NULL
        AND COLLATION_NAME <> 'utf8mb4_unicode_ci')                     AS columnas_en_otra,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK')        AS restricciones_check,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY')  AS claves_foraneas,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'UNIQUE')       AS indices_unicos;
