-- Migracion 006: resultados (partidos al mejor de 3, 5 o 7 y cinco acciones nuevas en la auditoria).
-- Necesita la 005; frena sin cambiar nada si falta. Se puede correr dos veces.
-- Al final tiene que mostrar empates_en_series 0 y 35 restricciones CHECK.

SET NAMES utf8mb4;

-- 1. Lo que tiene que estar antes.
SELECT COUNT(*) AS pedidos_de_inscripcion FROM pedido_inscripcion;

-- 2. Estructura.
ALTER TABLE configuracion_torneo
  ADD COLUMN IF NOT EXISTS mejor_de TINYINT UNSIGNED NULL AFTER admite_empate;
ALTER TABLE configuracion_torneo
  ADD CONSTRAINT IF NOT EXISTS ck_config_mejor_de CHECK (mejor_de IS NULL OR mejor_de IN (3, 5, 7));
ALTER TABLE configuracion_torneo
  ADD CONSTRAINT IF NOT EXISTS ck_config_serie_empate CHECK (mejor_de IS NULL OR admite_empate = 0);

-- La lista de acciones de la auditoria, entera.
ALTER TABLE auditoria DROP CONSTRAINT IF EXISTS ck_audit_accion;
ALTER TABLE auditoria
  ADD CONSTRAINT ck_audit_accion CHECK (accion IN ('alta', 'baja', 'modificacion',
                                                   'login_ok', 'login_error', 'logout',
                                                   'pedido_rol', 'aprobacion', 'rechazo',
                                                   'inscripcion', 'solicitud', 'cierre', 'fixture',
                                                   'resultado', 'programacion', 'final',
                                                   'cancelacion', 'medicion'));

-- 3. La liga de muestra: series al mejor de 3 mapas.
UPDATE configuracion_torneo c
  JOIN torneo t  ON t.id_torneo = c.id_torneo
  JOIN usuario u ON u.id_usuario = t.id_usuario_organizador
   SET c.mejor_de = 3
 WHERE t.nombre = 'Liga Valorant · Otoño' AND u.de_muestra = 1 AND c.admite_empate = 0;

-- 4. Comprobacion.
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
