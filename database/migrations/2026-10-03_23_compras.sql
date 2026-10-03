-- Compras a proveedor: la llegada del pedido del distribuidor. Suma al
-- inventario y deja como costo del producto el último costo pagado.
-- cantidad: unidades, o KILOS si el producto se vende por peso.
-- stock_antes: lo que había antes de sumar (NULL = el producto no llevaba
-- inventario y empezó a llevarlo con esta compra).

CREATE TABLE IF NOT EXISTS compras (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id     INT UNSIGNED NOT NULL,
  proveedor   VARCHAR(120) NOT NULL,
  total       INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  token       CHAR(32)     DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_compras_token (token),
  INDEX idx_compras_sede_fecha (sede_id, creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compra_items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compra_id       INT UNSIGNED  NOT NULL,
  producto_id     INT UNSIGNED  DEFAULT NULL,
  nombre          VARCHAR(120)  NOT NULL,
  por_peso        TINYINT(1)    NOT NULL DEFAULT 0,
  cantidad        DECIMAL(10,3) NOT NULL,
  costo_unitario  INT UNSIGNED  NOT NULL,
  subtotal        INT UNSIGNED  NOT NULL,
  stock_antes     INT           DEFAULT NULL,
  FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;
