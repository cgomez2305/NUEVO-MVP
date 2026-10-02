-- Combos: un producto con precio propio armado con otros productos de la
-- misma sede (p. ej. "Almuerzo completo" = bandeja + jugo). Un producto es
-- combo si tiene filas aquí. El combo está agotado si le falta cualquiera
-- de sus partes, y vender un combo descuenta el inventario de sus partes.

CREATE TABLE IF NOT EXISTS combo_items (
  combo_id    INT UNSIGNED     NOT NULL,
  producto_id INT UNSIGNED     NOT NULL,
  cantidad    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (combo_id, producto_id),
  FOREIGN KEY (combo_id) REFERENCES productos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
