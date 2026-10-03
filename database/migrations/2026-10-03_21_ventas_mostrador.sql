-- Venta de mostrador: lo que se vende en el local (con lector de códigos o
-- buscando por nombre), sin pasar por la tienda en línea. Descuenta el
-- mismo inventario que los pedidos y entra al cierre de caja por método.
--
-- recibido/cambio solo para efectivo ("¿con cuánto paga?"); cliente_id
-- solo si es fiado. token: el del formulario que la creó, único, para que
-- un doble toque en "Cobrar" no registre la venta dos veces.
-- Anular (solo el dueño, el mismo día) devuelve el inventario y, si fue
-- fiado, anula el cargo; la venta queda en el historial marcada.

CREATE TABLE IF NOT EXISTS ventas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id     INT UNSIGNED NOT NULL,
  total       INT UNSIGNED NOT NULL,
  metodo      ENUM('efectivo','nequi','breb','fiado') NOT NULL,
  recibido    INT UNSIGNED DEFAULT NULL,
  cambio      INT UNSIGNED DEFAULT NULL,
  cliente_id  INT UNSIGNED DEFAULT NULL,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  token       CHAR(32)     DEFAULT NULL,
  anulada     TINYINT(1)   NOT NULL DEFAULT 0,
  anulada_en  DATETIME     DEFAULT NULL,
  anulada_por INT UNSIGNED DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (anulada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_ventas_token (token),
  INDEX idx_ventas_sede_fecha (sede_id, creado_en)
) ENGINE=InnoDB;

-- cantidad: unidades, o KILOS si el producto se vende por peso (0,250 =
-- 250 g). precio_unitario es el de la unidad o el del kilo; costo_unitario
-- es el costo que tenía el producto al vender (para el margen real, aunque
-- el costo cambie después). nombre y por_peso se copian: borrar o editar el
-- producto no cambia el tiquete.
CREATE TABLE IF NOT EXISTS venta_items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_id        INT UNSIGNED  NOT NULL,
  producto_id     INT UNSIGNED  DEFAULT NULL,
  nombre          VARCHAR(120)  NOT NULL,
  por_peso        TINYINT(1)    NOT NULL DEFAULT 0,
  cantidad        DECIMAL(10,3) NOT NULL,
  precio_unitario INT UNSIGNED  NOT NULL,
  costo_unitario  INT UNSIGNED  DEFAULT NULL,
  subtotal        INT UNSIGNED  NOT NULL,
  FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;
