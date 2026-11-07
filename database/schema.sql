-- =====================================================================
-- MyA FOOTWEAR STORE - Esquema de la base de datos (MySQL / MariaDB)
-- Ejecutar primero este archivo y luego seed.sql.
-- ¡Atención! Borra y vuelve a crear todas las tablas.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS mya_footwear
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mya_footwear;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS pagos, recuperaciones_password, mensajes_contacto, movimientos_inventario, pedido_detalle, pedidos,
                     cupones, variantes, producto_imagenes, productos, tallas, colores, marcas, usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Usuarios del sistema: administradores, vendedores y clientes.
-- La contraseña se guarda con password_hash() (bcrypt), nunca en texto plano.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(120)  NOT NULL,
  email          VARCHAR(150)  NOT NULL,
  password_hash  VARCHAR(255)  NOT NULL,
  rol            ENUM('admin', 'vendedor', 'cliente') NOT NULL DEFAULT 'cliente',
  telefono       VARCHAR(30)   NULL,
  activo         TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_rol (rol, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catálogo: marcas, colores y tallas.
-- ---------------------------------------------------------------------
CREATE TABLE marcas (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(60)  NOT NULL,
  slug        VARCHAR(60)  NOT NULL,
  descripcion TEXT         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_marcas_nombre (nombre),
  UNIQUE KEY uq_marcas_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE colores (
  id     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(40) NOT NULL,
  hex    CHAR(7)     NULL COMMENT 'Color para la muestra, ej. #000000. NULL = multicolor',
  PRIMARY KEY (id),
  UNIQUE KEY uq_colores_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tallas (
  id    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  valor DECIMAL(3,1)      NOT NULL COMMENT 'Talla europea, ej. 38 o 38.5',
  orden SMALLINT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tallas_valor (valor),
  KEY idx_tallas_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Productos. El precio es por producto; el stock está en cada variante.
-- Los productos no se borran (pueden estar en pedidos): se desactivan.
-- ---------------------------------------------------------------------
CREATE TABLE productos (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  marca_id       SMALLINT UNSIGNED NOT NULL,
  nombre         VARCHAR(150)      NOT NULL,
  slug           VARCHAR(170)      NOT NULL COMMENT 'Identificador para la URL',
  descripcion    TEXT              NULL,
  precio         DECIMAL(12,2)     NOT NULL COMMENT 'Precio regular',
  precio_oferta  DECIMAL(12,2)     NULL COMMENT 'Precio en oferta; NULL = sin oferta',
  oferta_inicio  DATETIME          NULL COMMENT 'NULL = la oferta rige desde ya',
  oferta_fin     DATETIME          NULL COMMENT 'NULL = la oferta no vence',
  activo         TINYINT(1)        NOT NULL DEFAULT 1,
  creado_en      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_productos_slug (slug),
  KEY idx_productos_marca_activo (marca_id, activo),
  KEY idx_productos_nombre (nombre),
  KEY idx_productos_precio (precio),
  KEY idx_productos_oferta (precio_oferta, oferta_fin),
  CONSTRAINT fk_productos_marca FOREIGN KEY (marca_id) REFERENCES marcas (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_productos_precio CHECK (precio > 0),
  CONSTRAINT chk_productos_oferta CHECK (precio_oferta IS NULL OR (precio_oferta > 0 AND precio_oferta < precio)),
  CONSTRAINT chk_productos_fechas_oferta CHECK (oferta_inicio IS NULL OR oferta_fin IS NULL OR oferta_inicio < oferta_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Imágenes de cada producto. color_id permite mostrar la foto del color elegido.
CREATE TABLE producto_imagenes (
  id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  producto_id INT UNSIGNED      NOT NULL,
  color_id    SMALLINT UNSIGNED NULL,
  ruta        VARCHAR(255)      NOT NULL COMMENT 'Ruta relativa dentro de backend/public',
  orden       SMALLINT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_imagenes_producto (producto_id, orden),
  KEY idx_imagenes_color (color_id),
  CONSTRAINT fk_imagenes_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_imagenes_color FOREIGN KEY (color_id) REFERENCES colores (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Variantes: cada combinación talla + color de un producto, con su stock.
-- La llave única impide registrar dos veces la misma combinación.
-- ---------------------------------------------------------------------
CREATE TABLE variantes (
  id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  producto_id INT UNSIGNED      NOT NULL,
  talla_id    SMALLINT UNSIGNED NOT NULL,
  color_id    SMALLINT UNSIGNED NOT NULL,
  sku         VARCHAR(60)       NOT NULL COMMENT 'Código interno de la variante',
  stock       INT UNSIGNED      NOT NULL DEFAULT 0,
  activo      TINYINT(1)        NOT NULL DEFAULT 1,
  creado_en   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variantes_combinacion (producto_id, color_id, talla_id),
  UNIQUE KEY uq_variantes_sku (sku),
  KEY idx_variantes_talla (talla_id),
  KEY idx_variantes_color (color_id),
  KEY idx_variantes_stock (stock),
  CONSTRAINT fk_variantes_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_variantes_talla FOREIGN KEY (talla_id) REFERENCES tallas (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_variantes_color FOREIGN KEY (color_id) REFERENCES colores (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cupones de descuento: porcentaje o valor fijo, con vigencia, compra
-- mínima, límite de usos y opción de un solo uso por cliente.
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Pedidos y su detalle.
-- pedido_detalle guarda el precio del momento de la compra (no cambia si
-- después cambia el precio del producto). total = subtotal - descuento.
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  cliente_id      INT UNSIGNED  NOT NULL,
  vendedor_id     INT UNSIGNED  NULL COMMENT 'Vendedor que gestionó el pedido',
  cupon_id        INT UNSIGNED  NULL,
  cupon_codigo    VARCHAR(30)   NULL COMMENT 'Código usado, por si el cupón se edita',
  estado          ENUM('pendiente', 'pagado', 'enviado', 'entregado', 'cancelado') NOT NULL DEFAULT 'pendiente',
  subtotal        DECIMAL(12,2) NOT NULL,
  descuento       DECIMAL(12,2) NOT NULL DEFAULT 0,
  total           DECIMAL(12,2) NOT NULL,
  nombre_contacto VARCHAR(120)  NOT NULL,
  telefono        VARCHAR(30)   NOT NULL,
  direccion       VARCHAR(255)  NOT NULL,
  ciudad          VARCHAR(80)   NOT NULL,
  notas           VARCHAR(500)  NULL,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pedidos_cliente (cliente_id, creado_en),
  KEY idx_pedidos_vendedor (vendedor_id),
  KEY idx_pedidos_estado (estado, creado_en),
  KEY idx_pedidos_fecha (creado_en),
  KEY idx_pedidos_cupon (cupon_id, cliente_id),
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pedidos_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pedidos_cupon FOREIGN KEY (cupon_id) REFERENCES cupones (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_pedidos_total CHECK (total >= 0 AND descuento >= 0 AND total = subtotal - descuento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido_detalle (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  pedido_id       INT UNSIGNED      NOT NULL,
  variante_id     INT UNSIGNED      NOT NULL,
  descripcion     VARCHAR(255)      NOT NULL COMMENT 'Producto, color y talla al momento de la compra',
  cantidad        SMALLINT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2)     NOT NULL,
  subtotal        DECIMAL(12,2)     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_detalle_pedido_variante (pedido_id, variante_id),
  KEY idx_detalle_variante (variante_id),
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_variante FOREIGN KEY (variante_id) REFERENCES variantes (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pagos en línea (Wompi): cada intento de pago de un pedido.
-- Un pedido puede tener varios intentos (una tarjeta rechazada y luego otra aprobada).
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
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

-- ---------------------------------------------------------------------
-- Historial de inventario: cada cambio de stock queda registrado.
-- cantidad es positiva (entra) o negativa (sale).
-- ---------------------------------------------------------------------
CREATE TABLE movimientos_inventario (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  variante_id      INT UNSIGNED NOT NULL,
  usuario_id       INT UNSIGNED NULL COMMENT 'Quién hizo el movimiento',
  pedido_id        INT UNSIGNED NULL COMMENT 'Pedido relacionado (ventas y devoluciones)',
  tipo             ENUM('entrada', 'venta', 'ajuste', 'devolucion') NOT NULL,
  cantidad         INT          NOT NULL,
  stock_resultante INT UNSIGNED NOT NULL,
  motivo           VARCHAR(255) NULL,
  creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movimientos_variante (variante_id, creado_en),
  KEY idx_movimientos_tipo (tipo, creado_en),
  KEY idx_movimientos_pedido (pedido_id),
  CONSTRAINT fk_movimientos_variante FOREIGN KEY (variante_id) REFERENCES variantes (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_movimientos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_movimientos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_movimientos_cantidad CHECK (cantidad <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Mensajes del formulario de contacto.
-- ---------------------------------------------------------------------
CREATE TABLE mensajes_contacto (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(120) NOT NULL,
  email     VARCHAR(150) NOT NULL,
  telefono  VARCHAR(30)  NULL,
  mensaje   TEXT         NOT NULL,
  leido     TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mensajes_leido (leido, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Recuperación de contraseña: se guarda solo el HASH del token que va en
-- el enlace del correo; caduca y sirve una sola vez.
-- ---------------------------------------------------------------------
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
