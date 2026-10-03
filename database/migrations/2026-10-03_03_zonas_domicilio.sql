-- Zonas de domicilio por sede (cada sede reparte en su barrio) con su
-- costo y, opcional, un pedido mínimo propio; y un pedido mínimo general
-- de la sede. El pedido guarda lo que se cobró de domicilio y en qué
-- zona: si el dueño cambia la tarifa mañana, el pedido de hoy no cambia.

CREATE TABLE IF NOT EXISTS zonas_domicilio (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id        INT UNSIGNED     NOT NULL,
  nombre         VARCHAR(80)      NOT NULL,
  costo          INT UNSIGNED     NOT NULL DEFAULT 0,
  minimo_pedido  INT UNSIGNED     NOT NULL DEFAULT 0,
  activa         TINYINT(1)       NOT NULL DEFAULT 1,
  creado_en      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_zonas_sede (sede_id, activa)
) ENGINE=InnoDB;

ALTER TABLE sedes ADD COLUMN pedido_minimo INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE pedidos
  ADD COLUMN costo_domicilio INT UNSIGNED NOT NULL DEFAULT 0 AFTER cupon_codigo,
  ADD COLUMN zona_domicilio  VARCHAR(80)  DEFAULT NULL AFTER costo_domicilio;
