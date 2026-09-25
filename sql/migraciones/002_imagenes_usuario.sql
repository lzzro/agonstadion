-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- Migracion 002: foto de perfil y portada en usuario
-- ---------------------------------------------------------------------
-- Para las bases creadas con el schema.sql anterior a estas columnas
-- (la del XAMPP local y la del hosting). Una base nueva no la necesita:
-- sql/schema.sql ya las trae dentro del CREATE TABLE usuario.
--
-- Que agrega:
--   - foto_perfil y foto_portada: el nombre del archivo que genera el
--     sistema al subir una imagen (32 caracteres al azar y la
--     extension). NULL es "sin imagen", que es como quedan todas las
--     cuentas que ya existen.
--   - ck_usuario_foto y ck_usuario_portada: la base solo acepta
--     exactamente esa forma de nombre. Aunque el codigo fallara, en
--     estas columnas no entra una ruta ni un "../". BINARY hace
--     que distinga mayusculas, porque la tabla no las distingue y el
--     nombre generado va siempre en minusculas.
--
-- Como se corre, a mano, una vez en cada base (igual que la 001):
--   1. En phpMyAdmin, elegir la base en la lista de la izquierda
--      (sgdm en XAMPP, lucasmar_sgdm en el hosting). Este archivo no
--      lleva USE a proposito.
--   2. Pestana SQL (o Import), pegar o subir este archivo, y Go.
--   3. Con la cuenta con la que se administra la base (root en XAMPP,
--      la cuenta de cPanel en el hosting): sgdm_app no puede cambiar la
--      estructura.
--
-- No hace falta tocar permisos: sgdm_app tiene UPDATE sobre la tabla
-- usuario entera, y eso incluye las columnas nuevas.
--
-- Que esperar:
--   - El ALTER corre de una sola vez: las dos columnas y los dos CHECK
--     entran juntos, o no entra nada.
--   - Si se corre dos veces, avisa "Duplicate column name
--     'foto_perfil'" y no cambia nada: quiere decir que ya estaba.
--   - La ultima consulta muestra la tabla: las dos columnas tienen que
--     aparecer despues de fecha_alta, y los dos CHECK al final.
--
-- Necesita MariaDB 10.2 o superior, igual que schema.sql.
-- =====================================================================

ALTER TABLE usuario
  ADD COLUMN foto_perfil  VARCHAR(40) NULL AFTER fecha_alta,
  ADD COLUMN foto_portada VARCHAR(40) NULL AFTER foto_perfil,
  ADD CONSTRAINT ck_usuario_foto    CHECK (foto_perfil IS NULL
                                        OR BINARY foto_perfil REGEXP '^[0-9a-f]{32}[.](jpg|png|webp)$'),
  ADD CONSTRAINT ck_usuario_portada CHECK (foto_portada IS NULL
                                        OR BINARY foto_portada REGEXP '^[0-9a-f]{32}[.](jpg|png|webp)$');

SHOW CREATE TABLE usuario;
