-- Da el rol administrador a una cuenta ya creada, una vez por base (despues de la 003).
-- Reemplazar CORREO_DE_LA_CUENTA por el correo de la cuenta al pegarlo, no en el archivo.
-- Al final tiene que aparecer "administrador" entre sus roles; correrlo dos veces no agrega nada.

-- La hora de Montevideo, solo para esta conexion.
SET time_zone = '-03:00';

SET @correo = CONVERT('CORREO_DE_LA_CUENTA' USING utf8mb4) COLLATE utf8mb4_unicode_ci;

-- La columna correo se compara convertida al mismo cotejo que la variable, y no tal cual.

START TRANSACTION;

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
