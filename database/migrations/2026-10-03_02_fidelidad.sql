-- Tarjeta de sellos: cada compra (pedido o cita no cancelados, desde un
-- mínimo) suma un sello; al llegar a la meta el cliente gana el premio.
-- Los sellos no se guardan: se cuentan de pedidos y citas desde que el
-- negocio activó la tarjeta (`desde`). Al entregar un premio se anota
-- cuántos sellos gastó.

CREATE TABLE IF NOT EXISTS fidelidad (
  negocio_id     INT UNSIGNED PRIMARY KEY,
  activa         TINYINT(1)       NOT NULL DEFAULT 1,
  meta           TINYINT UNSIGNED NOT NULL DEFAULT 8,
  premio         VARCHAR(120)     NOT NULL,
  minimo_compra  INT UNSIGNED     NOT NULL DEFAULT 0,
  desde          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fidelidad_premios (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id   INT UNSIGNED     NOT NULL,
  cliente_id   INT UNSIGNED     NOT NULL,
  sellos       TINYINT UNSIGNED NOT NULL,
  premio       VARCHAR(120)     NOT NULL,
  usuario_id   INT UNSIGNED     DEFAULT NULL,
  entregado_en DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  INDEX idx_fidelidad_premios_cliente (negocio_id, cliente_id)
) ENGINE=InnoDB;
