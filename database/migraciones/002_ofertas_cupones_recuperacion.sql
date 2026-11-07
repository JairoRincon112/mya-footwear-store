-- =====================================================================
-- Migración 002: ofertas por producto, cupones de descuento y
-- recuperación de contraseña.
-- Para bases de datos creadas con la versión anterior de schema.sql:
-- agrega lo nuevo SIN borrar los datos existentes. Ejecutar una sola vez.
--   C:\xampp\mysql\bin\mysql.exe -u root < database\migraciones\002_ofertas_cupones_recuperacion.sql
-- =====================================================================
USE mya_footwear;
SET NAMES utf8mb4;

ALTER TABLE productos
  ADD COLUMN precio_oferta DECIMAL(12,2) NULL COMMENT 'Precio en oferta; NULL = sin oferta' AFTER precio,
  ADD COLUMN oferta_inicio DATETIME NULL COMMENT 'NULL = la oferta rige desde ya' AFTER precio_oferta,
  ADD COLUMN oferta_fin DATETIME NULL COMMENT 'NULL = la oferta no vence' AFTER oferta_inicio,
  ADD KEY idx_productos_oferta (precio_oferta, oferta_fin),
  ADD CONSTRAINT chk_productos_oferta CHECK (precio_oferta IS NULL OR (precio_oferta > 0 AND precio_oferta < precio)),
  ADD CONSTRAINT chk_productos_fechas_oferta CHECK (oferta_inicio IS NULL OR oferta_fin IS NULL OR oferta_inicio < oferta_fin);

CREATE TABLE cupones (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  codigo             VARCHAR(30)   NOT NULL COMMENT 'Se guarda en MAYÚSCULAS',
  descripcion        VARCHAR(255)  NULL,
  tipo               ENUM('porcentaje', 'valor') NOT NULL,
  valor              DECIMAL(12,2) NOT NULL COMMENT 'Porcentaje (1-90) o pesos',
  minimo_compra      DECIMAL(12,2) NOT NULL DEFAULT 0,
  usos_maximos       INT UNSIGNED  NULL COMMENT 'NULL = sin límite',
  usos               INT UNSIGNED  NOT NULL DEFAULT 0,
  uno_por_cliente    TINYINT(1)    NOT NULL DEFAULT 0,
  inicio             DATETIME      NULL,
  fin                DATETIME      NULL,
  activo             TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cupones_codigo (codigo),
  KEY idx_cupones_vigencia (activo, fin),
  CONSTRAINT chk_cupones_valor CHECK (valor > 0 AND (tipo = 'valor' OR valor <= 90)),
  CONSTRAINT chk_cupones_fechas CHECK (inicio IS NULL OR fin IS NULL OR inicio < fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los pedidos existentes no tenían descuento: subtotal = total.
ALTER TABLE pedidos
  ADD COLUMN cupon_id INT UNSIGNED NULL AFTER vendedor_id,
  ADD COLUMN cupon_codigo VARCHAR(30) NULL COMMENT 'Código usado, por si el cupón se edita' AFTER cupon_id,
  ADD COLUMN subtotal DECIMAL(12,2) NULL AFTER estado,
  ADD COLUMN descuento DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal;
UPDATE pedidos SET subtotal = total WHERE subtotal IS NULL;
ALTER TABLE pedidos
  MODIFY subtotal DECIMAL(12,2) NOT NULL,
  ADD KEY idx_pedidos_cupon (cupon_id, cliente_id),
  ADD CONSTRAINT fk_pedidos_cupon FOREIGN KEY (cupon_id) REFERENCES cupones (id) ON UPDATE CASCADE ON DELETE SET NULL,
  DROP CONSTRAINT chk_pedidos_total,
  ADD CONSTRAINT chk_pedidos_total CHECK (total >= 0 AND descuento >= 0 AND total = subtotal - descuento);

CREATE TABLE recuperaciones_password (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL COMMENT 'SHA-256 del token',
  expira_en   DATETIME     NOT NULL,
  usado_en    DATETIME     NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recuperaciones_token (token_hash),
  KEY idx_recuperaciones_usuario (usuario_id, creado_en),
  CONSTRAINT fk_recuperaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
