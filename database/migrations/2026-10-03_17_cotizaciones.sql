-- Cotización por ítems (mano de obra, materiales) que el cliente aprueba
-- desde su enlace. Al aprobarla, su total queda como el valor de la cita
-- (igual que un ajuste aprobado). Puede pedir anticipo para materiales y
-- dice cuántos días de garantía tiene el trabajo.
CREATE TABLE IF NOT EXISTS cotizaciones (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id         INT UNSIGNED NOT NULL,
  cita_id         INT UNSIGNED NOT NULL,
  token           CHAR(32)     NOT NULL,
  estado          ENUM('enviada','aprobada','rechazada','reemplazada') NOT NULL DEFAULT 'enviada',
  total           INT UNSIGNED NOT NULL DEFAULT 0,
  anticipo        INT UNSIGNED NOT NULL DEFAULT 0,
  anticipo_pagado TINYINT(1)   NOT NULL DEFAULT 0,
  garantia_dias   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  validez_dias    TINYINT UNSIGNED NOT NULL DEFAULT 8,
  nota            VARCHAR(500) DEFAULT NULL,
  respondida_en   DATETIME     DEFAULT NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cotizacion_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_cotizaciones_cita (cita_id, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cotizacion_items (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cotizacion_id  INT UNSIGNED NOT NULL,
  tipo           ENUM('mano_obra','material','otro','descuento') NOT NULL,
  descripcion    VARCHAR(160) NOT NULL,
  cantidad       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  valor_unitario INT UNSIGNED NOT NULL,
  FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;
