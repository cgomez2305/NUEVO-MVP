-- Catálogo compartido de códigos de barras entre todas las tiendas Veci:
-- solo el código y cómo se llama el producto (nunca precios, costos ni
-- nada de la tienda que lo registró). Cuando una tienda escanea un código
-- que no tiene, se le sugiere el nombre ("Otras tiendas lo llaman: …").
-- Solo entran códigos de producto reales (GTIN con dígito de control
-- válido), no los internos de la balanza ni los inventados por la tienda.

CREATE TABLE IF NOT EXISTS codigos_barras (
  codigo          VARCHAR(32)  NOT NULL PRIMARY KEY,
  nombre          VARCHAR(120) NOT NULL,
  veces_usado     INT UNSIGNED NOT NULL DEFAULT 1,
  actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
