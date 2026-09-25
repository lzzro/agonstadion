-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- Migracion 003: pedidos de rol
-- ---------------------------------------------------------------------
-- Para las bases creadas con el schema.sql anterior a los pedidos de rol
-- (la del XAMPP local y la del hosting). Una base nueva no la necesita:
-- sql/schema.sql ya trae todo esto.
--
-- Que agrega:
--   - La tabla pedido_rol: una cuenta pide un rol (hoy, organizador) y
--     un administrador lo aprueba o lo rechaza. Un solo pedido pendiente
--     por cuenta y por rol, y nadie resuelve su propio pedido: las dos
--     cosas las garantiza la base, no solo el codigo. El porque de cada
--     restriccion esta comentado en schema.sql, sobre la tabla.
--   - Tres acciones nuevas en el registro de auditoria: pedido_rol,
--     aprobacion y rechazo. Para eso se reemplaza ck_audit_accion por
--     otro con la lista completa.
--   - El permiso de sgdm_app sobre la tabla nueva (INSERT y UPDATE, sin
--     DELETE), SOLO en XAMPP: ver el ultimo bloque.
--
-- Como se corre, a mano, una vez en cada base (igual que la 001 y la 002):
--   1. En phpMyAdmin, elegir la base en la lista de la izquierda (sgdm en
--      XAMPP, lucasmar_sgdm en el hosting). Sin USE, a proposito.
--   2. Pestana SQL (o Import), pegar o subir este archivo, y Go.
--   3. Con la cuenta con la que se administra la base (root en XAMPP, la
--      cuenta de cPanel en el hosting).
--
-- Que esperar:
--   - En XAMPP corre entero.
--   - En el hosting, el ultimo bloque (GRANT) da un error de permisos, y
--     es lo esperable: ahi los permisos de sgdm_app los da cPanel sobre
--     la base entera (paso 1 de docs/deploy-hosting-compartido.md), y ya
--     alcanzan a la tabla nueva. Todo lo anterior al GRANT ya quedo hecho.
--   - Si se corre dos veces, el CREATE TABLE avisa "Table 'pedido_rol'
--     already exists" y no cambia nada.
--
-- Necesita MariaDB 10.2 o superior, igual que schema.sql (los CHECK y las
-- columnas calculadas).
-- =====================================================================

CREATE TABLE pedido_rol (
  id_pedido_rol       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario          INT UNSIGNED NOT NULL,
  id_rol              INT UNSIGNED NOT NULL,
  estado              VARCHAR(10)  NOT NULL DEFAULT 'pendiente',
  fecha_pedido        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion    DATETIME     NULL,
  id_usuario_resuelve INT UNSIGNED NULL,
  pendiente_de        INT UNSIGNED GENERATED ALWAYS AS
                        (IF(estado = 'pendiente', id_usuario, NULL)) STORED,
  CONSTRAINT pk_pedido_rol       PRIMARY KEY (id_pedido_rol),
  CONSTRAINT fk_pedido_usuario   FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pedido_rol       FOREIGN KEY (id_rol) REFERENCES rol (id_rol)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pedido_resuelve  FOREIGN KEY (id_usuario_resuelve) REFERENCES usuario (id_usuario)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT uq_pedido_pendiente UNIQUE (pendiente_de, id_rol),
  CONSTRAINT ck_pedido_estado    CHECK (estado IN ('pendiente', 'aprobado', 'rechazado')),
  CONSTRAINT ck_pedido_resuelto  CHECK ((estado = 'pendiente' AND fecha_resolucion IS NULL
                                                             AND id_usuario_resuelve IS NULL)
                                     OR (estado <> 'pendiente' AND fecha_resolucion IS NOT NULL
                                                               AND id_usuario_resuelve IS NOT NULL)),
  CONSTRAINT ck_pedido_fechas    CHECK (fecha_resolucion IS NULL OR fecha_resolucion >= fecha_pedido),
  CONSTRAINT ck_pedido_propio    CHECK (id_usuario_resuelve IS NULL OR id_usuario_resuelve <> id_usuario)
) ENGINE=InnoDB;

ALTER TABLE auditoria DROP CONSTRAINT ck_audit_accion;
ALTER TABLE auditoria ADD CONSTRAINT ck_audit_accion
  CHECK (accion IN ('alta', 'baja', 'modificacion', 'login_ok', 'login_error', 'logout',
                    'pedido_rol', 'aprobacion', 'rechazo'));

SHOW CREATE TABLE pedido_rol;

-- ---------------------------------------------------------------------
-- Solo en XAMPP (en el hosting da error y no hace falta, ver arriba).
-- sgdm_app no tiene DELETE sobre esta tabla: un pedido no se borra, se
-- resuelve.
-- ---------------------------------------------------------------------
GRANT INSERT, UPDATE ON pedido_rol TO 'sgdm_app'@'localhost';
