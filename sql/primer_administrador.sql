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
-- Como se corre:
--   1. Crear la cuenta desde el sitio, como cualquier otra.
--   2. En phpMyAdmin, elegir la base (sgdm en XAMPP, lucasmar_sgdm en el
--      hosting) -> pestana SQL.
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
--
-- Se corre con la cuenta de administracion de la base: root en XAMPP, y
-- en el hosting la que usa el phpMyAdmin de cPanel. No desde el sitio:
-- ninguna pagina da el rol administrador.
-- =====================================================================

-- El COLLATE hace que la variable compare igual que la columna correo,
-- sea cual sea la configuracion de la conexion (XAMPP y phpMyAdmin del
-- hosting pueden venir distintas).
SET @correo = CONVERT('CORREO_DE_LA_CUENTA' USING utf8mb4) COLLATE utf8mb4_unicode_ci;

INSERT INTO usuario_rol (id_usuario, id_rol)
SELECT u.id_usuario, r.id_rol
  FROM usuario u
  JOIN rol r ON r.nombre = 'administrador'
 WHERE u.correo = @correo
   AND u.activo = 1
   AND NOT EXISTS (SELECT 1 FROM usuario_rol ur
                    WHERE ur.id_usuario = u.id_usuario AND ur.id_rol = r.id_rol);

SET @agregado = ROW_COUNT();

-- Si se agrego el rol, queda en la auditoria (sin id de quien lo hace:
-- no lo hace ninguna cuenta del sitio, sino quien administra la base).
INSERT INTO auditoria (id_usuario, tabla_afectada, id_registro, accion, detalle)
SELECT NULL, 'usuario_rol', u.id_usuario, 'aprobacion',
       'Primer administrador'
  FROM usuario u
 WHERE u.correo = @correo AND u.activo = 1 AND @agregado > 0;

SELECT u.id_usuario, u.correo, GROUP_CONCAT(r.nombre ORDER BY r.nombre) AS roles
  FROM usuario u
  JOIN usuario_rol ur ON ur.id_usuario = u.id_usuario
  JOIN rol r ON r.id_rol = ur.id_rol
 WHERE u.correo = @correo
 GROUP BY u.id_usuario, u.correo;
