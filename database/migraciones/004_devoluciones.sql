-- =========================================================================
-- Migración 004: devoluciones de pedidos entregados
-- Para bases creadas antes de este módulo. Las instalaciones nuevas ya la
-- tienen incluida en schema.sql.
--   C:\xampp\mysql\bin\mysql.exe -u root < database\migraciones\004_devoluciones.sql
-- =========================================================================
USE mya_footwear;
SET NAMES utf8mb4;

-- Una solicitud de devolución por producto (línea) de un pedido entregado.
-- Flujo: solicitada -> aprobada -> recibida (el stock vuelve al inventario)
--        solicitada -> rechazada
CREATE TABLE IF NOT EXISTS devoluciones (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  pedido_id       INT UNSIGNED      NOT NULL,
  detalle_id      INT UNSIGNED      NOT NULL COMMENT 'Línea del pedido que se devuelve',
  cliente_id      INT UNSIGNED      NOT NULL,
  tipo            ENUM('garantia', 'talla_incorrecta', 'defectuoso', 'producto_equivocado', 'no_satisface') NOT NULL,
  cantidad        SMALLINT UNSIGNED NOT NULL,
  descripcion     VARCHAR(500)      NOT NULL COMMENT 'Lo que cuenta el cliente',
  estado          ENUM('solicitada', 'aprobada', 'rechazada', 'recibida') NOT NULL DEFAULT 'solicitada',
  respuesta       VARCHAR(500)      NULL COMMENT 'Respuesta de la tienda (obligatoria al rechazar)',
  gestionado_por  INT UNSIGNED      NULL COMMENT 'Vendedor o administrador que la atendió',
  creado_en       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_devoluciones_estado (estado, creado_en),
  KEY idx_devoluciones_pedido (pedido_id),
  KEY idx_devoluciones_detalle (detalle_id),
  KEY idx_devoluciones_cliente (cliente_id),
  CONSTRAINT fk_devoluciones_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_devoluciones_detalle FOREIGN KEY (detalle_id) REFERENCES pedido_detalle (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_devoluciones_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_devoluciones_gestor FOREIGN KEY (gestionado_por) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_devoluciones_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
