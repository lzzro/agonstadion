-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- El primer administrador
-- ---------------------------------------------------------------------
-- El rol administrador no se pide ni se aprueba desde el sitio: alguien
-- tiene que aprobar los pedidos, y ese alguien necesita ser
-- administrador primero. Este archivo le da el rol a UNA cuenta que ya
-- existe (creada desde registro.php), una sola vez en cada base.
--
-- En el repositorio no hay ningun correo real: el de abajo es un
-- marcador y se reemplaza al correrlo, no en el archivo.
--
-- Cuando: despues de la migracion 003, que agrega a la auditoria la
-- accion "aprobacion" con que queda registrado. Anda igual antes o
-- despues de la 004 (con las tablas todavia en latin1 o ya en utf8mb4).
-- En una base que viene de antes, el orden es 003, 004 y este.
--
-- Como se corre:
--   1. Crear la cuenta desde el sitio, como cualquier otra.
--   2. En phpMyAdmin, elegir la base (sgdm en XAMPP) -> pestana SQL. En
--      la VM o en Docker, en la consola de la base, con la base elegida.
--   3. Pegar TODO este archivo y reemplazar CORREO_DE_LA_CUENTA por el
--      correo con que se creo la cuenta, entre las comillas.
--   4. Go.
--
-- Que esperar:
--   - La ultima consulta muestra la cuenta con sus roles: tiene que
--     aparecer "administrador" (ademas de "jugador").
--   - Si no aparece ninguna fila, el correo no coincide con ninguna
--     cuenta activa: revisar como se escribio.
--   - Correrlo dos veces no hace dano: si la cuenta ya es
--     administradora, no agrega nada.
--   - La asignacion queda en el registro de auditoria, como cualquier
--     cambio de rol.
--   - Si da "CONSTRAINT ck_audit_accion failed", falta la migracion 003:
--     correrla y repetir este. No queda nada a medias: el rol y su fila
--     de auditoria van juntos (ver la transaccion, abajo).
--
-- Se corre con la cuenta de administracion de la base: root en XAMPP,
-- en la VM o en Docker. No desde el sitio:
-- ninguna pagina da el rol administrador.
-- =====================================================================

-- La hora de Montevideo (UTC-3), la misma que usa la aplicacion (ver
-- apps/config/database.php): la fila de la auditoria lleva la hora de
-- aca y no la del servidor, que puede estar en UTC. Vale solo para
-- esta conexion.
SET time_zone = '-03:00';

-- El correo, en utf8mb4 y con un cotejo fijo, venga como venga la
-- conexion (XAMPP, la VM y Docker pueden venir distintos).
SET @correo = CONVERT('CORREO_DE_LA_CUENTA' USING utf8mb4) COLLATE utf8mb4_unicode_ci;

-- La columna correo se compara convertida al mismo cotejo que la
-- variable, y no tal cual. Asi la comparacion anda sea cual sea la
-- codificacion de la tabla: latin1 (una base anterior a la 004),
-- utf8mb4_unicode_ci (despues), o cualquier otra. Comparada tal cual,
-- una tabla en otro cotejo utf8mb4 (utf8mb4_general_ci, por ejemplo)
-- daba "Illegal mix of collations". Es una tabla chica y se corre una
-- vez: que no use el indice no importa.

-- El rol y su fila de auditoria van juntos: si algo falla en el medio
-- (por ejemplo, sin la 003 la auditoria no acepta "aprobacion"), no
-- queda el rol dado sin su registro. Al cortarse el archivo por el
-- error, la conexion se cierra sin el COMMIT y la base deshace lo que
-- habia empezado.
--
-- Las dos filas salen de la MISMA condicion: la cuenta existe, esta
-- activa y todavia no es administradora. Primero la auditoria, despues
-- el rol: asi, cuando se evalua la auditoria, el rol todavia falta. Si
-- la cuenta ya era administradora, la condicion no se cumple y no se
-- agrega ninguna de las dos.
-- No se usa ROW_COUNT() ("cuantas filas agrego el INSERT anterior"):
-- despues de cada consulta, phpMyAdmin corre las suyas (SHOW WARNINGS,
-- SELECT LAST_INSERT_ID()...), y ROW_COUNT() termina contando esas. Asi
-- estaba antes, y desde phpMyAdmin el rol quedaba dado pero la fila de
-- auditoria no se escribia nunca (por la consola, en cambio, andaba).
START TRANSACTION;

-- La fila de auditoria (sin id de quien lo hace: no lo hace ninguna
-- cuenta del sitio, sino quien administra la base).
INSERT INTO auditoria (id_usuario, tabla_afectada, id_registro, accion, detalle)
SELECT NULL, 'usuario_rol', u.id_usuario, 'aprobacion', 'Primer administrador'
  FROM usuario u
  JOIN rol r ON r.nombre = 'administrador'
 WHERE CONVERT(u.correo USING utf8mb4) COLLATE utf8mb4_unicode_ci = @correo
   AND u.activo = 1
   AND NOT EXISTS (SELECT 1 FROM usuario_rol ur
                    WHERE ur.id_usuario = u.id_usuario AND ur.id_rol = r.id_rol);

-- El rol.
INSERT INTO usuario_rol (id_usuario, id_rol)
SELECT u.id_usuario, r.id_rol
  FROM usuario u
  JOIN rol r ON r.nombre = 'administrador'
 WHERE CONVERT(u.correo USING utf8mb4) COLLATE utf8mb4_unicode_ci = @correo
   AND u.activo = 1
   AND NOT EXISTS (SELECT 1 FROM usuario_rol ur
                    WHERE ur.id_usuario = u.id_usuario AND ur.id_rol = r.id_rol);

COMMIT;

SELECT u.id_usuario, u.correo, GROUP_CONCAT(r.nombre ORDER BY r.nombre) AS roles
  FROM usuario u
  JOIN usuario_rol ur ON ur.id_usuario = u.id_usuario
  JOIN rol r ON r.id_rol = ur.id_rol
 WHERE CONVERT(u.correo USING utf8mb4) COLLATE utf8mb4_unicode_ci = @correo
 GROUP BY u.id_usuario, u.correo;
