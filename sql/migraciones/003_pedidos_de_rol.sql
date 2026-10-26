-- Migracion 003: pedidos de rol (tabla pedido_rol y tres acciones nuevas en la auditoria).
-- Para bases creadas antes de los pedidos de rol; schema.sql ya los trae.
-- El GRANT del final se corre con la cuenta de administracion de la base.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auditoria DROP CONSTRAINT ck_audit_accion;
ALTER TABLE auditoria ADD CONSTRAINT ck_audit_accion
  CHECK (accion IN ('alta', 'baja', 'modificacion', 'login_ok', 'login_error', 'logout',
                    'pedido_rol', 'aprobacion', 'rechazo'));

SHOW CREATE TABLE pedido_rol;

GRANT INSERT, UPDATE ON pedido_rol TO 'sgdm_app'@'localhost';
