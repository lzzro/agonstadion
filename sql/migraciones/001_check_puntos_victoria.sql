-- Migracion 001: rango de los puntos por victoria (1 a 10).
-- Para bases creadas antes de esta restriccion; schema.sql ya la trae.
-- Frena sin cambiar nada si alguna fila viola el rango. Correrla dos veces avisa que la restriccion ya existe.

SELECT id_torneo, puntos_victoria
  FROM configuracion_torneo
 WHERE puntos_victoria NOT BETWEEN 1 AND 10;

ALTER TABLE configuracion_torneo
  ADD CONSTRAINT ck_config_victoria CHECK (puntos_victoria BETWEEN 1 AND 10);

SHOW CREATE TABLE configuracion_torneo;
