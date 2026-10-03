-- Catálogo compartido de códigos de barras por votos: cada negocio cuenta
-- una vez por código y se sugiere el nombre que usan más negocios. Antes
-- ganaba el último que guardaba: una sola cuenta podía cambiarle el nombre
-- a un producto común (la gaseosa de siempre) para todas las tiendas.
-- codigos_barras queda como respaldo de lo que ya se había aprendido.
CREATE TABLE IF NOT EXISTS codigos_barras_votos (
  codigo          VARCHAR(32)  NOT NULL,
  negocio_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(120) NOT NULL,
  actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (codigo, negocio_id),
  INDEX idx_codigos_votos_nombre (codigo, nombre),
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
