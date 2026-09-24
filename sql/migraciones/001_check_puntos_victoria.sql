-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- Migracion 001: rango de los puntos por victoria (1 a 10)
-- ---------------------------------------------------------------------
-- Para las bases creadas con el schema.sql anterior a esta restriccion
-- (la del XAMPP local y la del hosting). Una base nueva no la necesita:
-- sql/schema.sql ya trae ck_config_victoria dentro del CREATE TABLE.
--
-- Por que: minimo 1, el mismo min="1" del formulario de crear.html y de
-- ConfiguracionTorneo::validar(). Una victoria que no suma puntos no
-- distingue al que gana. El tope de 10 es el mismo de validar().
--
-- Como se corre, a mano, una vez en cada base:
--   1. En phpMyAdmin, elegir la base en la lista de la izquierda
--      (sgdm en XAMPP, lucasmar_sgdm en el hosting). Este archivo no
--      lleva USE a proposito: el nombre de la base no es el mismo en
--      los dos lugares.
--   2. Pestaña SQL (o Import), pegar o subir este archivo, y Go.
--   3. Con la cuenta con la que se administra la base (root en XAMPP,
--      la cuenta de cPanel en el hosting). sgdm_app no alcanza: solo
--      tiene permisos sobre los datos, no sobre la estructura.
--
-- Que esperar:
--   - La primera consulta lista las filas que el CHECK rechazaria. Lo
--     normal es que no devuelva ninguna. Si devuelve alguna, el ALTER
--     falla con "CONSTRAINT ck_config_victoria failed" y no cambia
--     nada: corregir esas filas primero (UPDATE con un valor de 1 a 10)
--     y volver a correr el archivo.
--   - Si se corre dos veces en la misma base, el segundo ALTER falla
--     con "Duplicate CHECK constraint name". No rompe nada: quiere
--     decir que la restriccion ya estaba.
--   - La ultima consulta muestra la tabla: ck_config_victoria tiene que
--     aparecer al final, debajo de ck_config_pts.
--
-- Necesita MariaDB 10.2 o superior, igual que schema.sql (antes de esa
-- version los CHECK se aceptan pero no se verifican).
-- =====================================================================

SELECT id_torneo, puntos_victoria
  FROM configuracion_torneo
 WHERE puntos_victoria NOT BETWEEN 1 AND 10;

ALTER TABLE configuracion_torneo
  ADD CONSTRAINT ck_config_victoria CHECK (puntos_victoria BETWEEN 1 AND 10);

SHOW CREATE TABLE configuracion_torneo;
