-- Tiendas de barrio (fase 4): el producto puede llevar código de barras
-- (para venderlo con lector o con la cámara), lo que le cuesta al tendero
-- (para calcular el margen real) y si se vende por unidad o por peso.
--
-- Productos por peso: `precio` y `costo` son POR KILO y `stock` se lleva en
-- GRAMOS (2,5 kg de queso = 2500). Por unidad, todo sigue como antes.
-- El código es único por sede cuando existe (varios productos sin código
-- conviven: NULL no choca en un índice único).

ALTER TABLE productos
  ADD COLUMN codigo_barras VARCHAR(32) DEFAULT NULL AFTER descripcion,
  ADD COLUMN costo         INT UNSIGNED DEFAULT NULL AFTER precio,
  ADD COLUMN vende_por     ENUM('unidad','peso') NOT NULL DEFAULT 'unidad' AFTER costo,
  ADD UNIQUE KEY uniq_producto_codigo_sede (sede_id, codigo_barras),
  ADD INDEX idx_productos_codigo (codigo_barras);
