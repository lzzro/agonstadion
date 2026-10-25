-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- Migracion 006: resultados de las ligas (fase 3 del motor de torneos)
-- ---------------------------------------------------------------------
-- Lo que necesita la aplicacion para cargar resultados, cerrar fechas y
-- terminar una liga con su vencedor. Una base creada con el schema.sql
-- de ahora ya lo trae, y en ella esta migracion solo marca la serie de
-- la liga de muestra (paso 3).
--
--   - configuracion_torneo.mejor_de: los partidos al mejor de 3, 5 o 7
--     (mapas, sets, partidas). NULL es un partido unico. Con dos
--     restricciones: solo 3, 5 o 7 (ck_config_mejor_de), y una serie no
--     admite empate (ck_config_serie_empate), porque al mejor de un
--     numero impar siempre hay ganador.
--   - Cinco acciones nuevas en la auditoria: resultado, programacion,
--     final, cancelacion y medicion. Se reemplaza ck_audit_accion por la
--     lista entera, igual que en la 005.
--   - La Liga Valorant · Otoño de muestra (si esta) pasa a decir lo que
--     ya era: series al mejor de 3 mapas. Sus 57 partidos jugados son
--     todos 2-0 o 2-1, asi que nada cambia en la tabla.
--
-- ORDEN, en una base que viene de antes: las que falten hasta la 005, y
-- despues esta. Frena sin cambiar nada si falta la 005 (ver "Que
-- esperar").
--
-- ANTES DE CORRERLA: un respaldo. En phpMyAdmin, con la base elegida,
-- Export -> Quick -> SQL -> Go.
--
-- Como se corre, a mano, una vez en cada base (igual que las otras):
--   1. En phpMyAdmin, elegir la base (sgdm en XAMPP). Sin USE, a proposito.
--   2. Pestana SQL (o Import), pegar o subir este archivo, y Go. No tiene
--      bloques "[solo servidor propio]".
--   3. Con la cuenta con la que se administra la base (root en XAMPP
--      o en la VM). sgdm_app no alcanza: no puede
--      cambiar la estructura.
--
-- Que esperar:
--   - Si falta la 005, la primera consulta da "Table ...
--     pedido_inscripcion doesn't exist" y no se cambia nada: correr la
--     005 y despues esta.
--   - Se puede correr dos veces: lo que agrega ya esta y no se repite.
--   - La ultima consulta tiene que mostrar, en una base con la 005:
--         serie_valorant       3 (o vacio si la base no tiene la
--                              liga de muestra, o si esa liga volvio
--                              a admitir empates: entonces no es una
--                              serie, y no se le pone)
--         empates_en_series    0
--         restricciones_check  35
--     Si "empates_en_series" no da 0, NO seguir: avisar.
--
-- Necesita MariaDB 10.2 o superior. Probada en MariaDB 10.11 (la de la
-- VM), sobre una base con la 005 y sobre una base nueva, y dos veces.
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Lo que tiene que estar antes. Nada de esto cambia la base.
-- ---------------------------------------------------------------------
-- La 005: si falta pedido_inscripcion, esto da error y frena.
SELECT COUNT(*) AS pedidos_de_inscripcion FROM pedido_inscripcion;



-- ---------------------------------------------------------------------
-- 2. Estructura. Cada sentencia se puede repetir: si lo que agrega ya
--    esta, no hace nada. El porque de cada cosa esta en schema.sql.
-- ---------------------------------------------------------------------
ALTER TABLE configuracion_torneo
  ADD COLUMN IF NOT EXISTS mejor_de TINYINT UNSIGNED NULL AFTER admite_empate;
ALTER TABLE configuracion_torneo
  ADD CONSTRAINT IF NOT EXISTS ck_config_mejor_de CHECK (mejor_de IS NULL OR mejor_de IN (3, 5, 7));
ALTER TABLE configuracion_torneo
  ADD CONSTRAINT IF NOT EXISTS ck_config_serie_empate CHECK (mejor_de IS NULL OR admite_empate = 0);

-- La lista de acciones de la auditoria, entera. Entre las dos sentencias
-- la tabla queda un instante sin la restriccion; nada escribe en ella
-- mientras corre la migracion.
ALTER TABLE auditoria DROP CONSTRAINT IF EXISTS ck_audit_accion;
ALTER TABLE auditoria
  ADD CONSTRAINT ck_audit_accion CHECK (accion IN ('alta', 'baja', 'modificacion',
                                                   'login_ok', 'login_error', 'logout',
                                                   'pedido_rol', 'aprobacion', 'rechazo',
                                                   'inscripcion', 'solicitud', 'cierre', 'fixture',
                                                   'resultado', 'programacion', 'final',
                                                   'cancelacion', 'medicion'));


-- ---------------------------------------------------------------------
-- 3. La liga de muestra: series al mejor de 3 mapas. Ya no admitia
--    empates (005); ahora lo dice la columna nueva. Si la base no tiene
--    la liga de muestra, no cambia nada.
-- ---------------------------------------------------------------------
UPDATE configuracion_torneo c
  JOIN torneo t  ON t.id_torneo = c.id_torneo
  JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
   SET c.mejor_de = 3
 WHERE t.nombre = 'Liga Valorant · Otoño' AND u.de_muestra = 1 AND c.admite_empate = 0;


-- ---------------------------------------------------------------------
-- 4. Comprobacion. Solo mira; se puede correr sola.
-- ---------------------------------------------------------------------
SELECT
    (SELECT c.mejor_de FROM configuracion_torneo c
       JOIN torneo t ON t.id_torneo = c.id_torneo
      WHERE t.nombre = 'Liga Valorant · Otoño' LIMIT 1)                  AS serie_valorant,
    (SELECT COUNT(*) FROM resultado re
       JOIN enfrentamiento en ON en.id_enfrentamiento = re.id_enfrentamiento
       JOIN ronda r ON r.id_ronda = en.id_ronda
       JOIN configuracion_torneo c ON c.id_torneo = r.id_torneo
      WHERE c.mejor_de IS NOT NULL
        AND re.puntaje_local = re.puntaje_visitante)                     AS empates_en_series,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK') AS restricciones_check;
