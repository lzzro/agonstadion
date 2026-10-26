-- Migracion 002: foto de perfil y portada en usuario.
-- Para bases creadas antes de estas columnas; schema.sql ya las trae.
-- Correrla dos veces avisa "Duplicate column name" y no cambia nada.

ALTER TABLE usuario
  ADD COLUMN foto_perfil  VARCHAR(40) NULL AFTER fecha_alta,
  ADD COLUMN foto_portada VARCHAR(40) NULL AFTER foto_perfil,
  ADD CONSTRAINT ck_usuario_foto    CHECK (foto_perfil IS NULL
                                        OR BINARY foto_perfil REGEXP '^[0-9a-f]{32}[.](jpg|png|webp)$'),
  ADD CONSTRAINT ck_usuario_portada CHECK (foto_portada IS NULL
                                        OR BINARY foto_portada REGEXP '^[0-9a-f]{32}[.](jpg|png|webp)$');

SHOW CREATE TABLE usuario;
