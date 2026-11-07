-- =========================================================================
-- Migración 003: pagos en línea con Wompi
-- Para bases creadas antes de este módulo. Las instalaciones nuevas ya la
-- tienen incluida en schema.sql.
--   C:\xampp\mysql\bin\mysql.exe -u root < database\migraciones\003_pagos_wompi.sql
-- =========================================================================
USE mya_footwear;
SET NAMES utf8mb4;

-- Cada intento de pago de un pedido. Un pedido puede tener varios intentos
-- (por ejemplo, una tarjeta rechazada y luego otra aprobada).
CREATE TABLE IF NOT EXISTS pagos (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  pedido_id       INT UNSIGNED    NOT NULL,
  pasarela        VARCHAR(20)     NOT NULL DEFAULT 'wompi',
  referencia      VARCHAR(60)     NOT NULL COMMENT 'Referencia única enviada a Wompi',
  monto_centavos  BIGINT UNSIGNED NOT NULL COMMENT 'Total del pedido en centavos al iniciar el pago',
  moneda          CHAR(3)         NOT NULL DEFAULT 'COP',
  estado          ENUM('pendiente', 'aprobado', 'rechazado', 'anulado', 'error') NOT NULL DEFAULT 'pendiente',
  transaccion_id  VARCHAR(60)     NULL COMMENT 'Id de la transacción en Wompi',
  metodo          VARCHAR(30)     NULL COMMENT 'CARD, NEQUI, PSE, BANCOLOMBIA_TRANSFER...',
  respuesta       TEXT            NULL COMMENT 'Última respuesta de Wompi (JSON), para auditoría',
  creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pagos_referencia (referencia),
  UNIQUE KEY uq_pagos_transaccion (transaccion_id),
  KEY idx_pagos_pedido (pedido_id, creado_en),
  CONSTRAINT fk_pagos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
