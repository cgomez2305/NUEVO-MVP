-- Fiado (la cuenta del cliente en la tienda). Es del NEGOCIO, como el
-- cliente: lo que se fió en una sede se puede abonar en otra.
-- Saldo = cargos − abonos (sin contar los anulados). Un cargo nace de una
-- venta de mostrador fiada (venta_id) o a mano, con nota. sede_id dice en
-- qué local entró el abono: un abono en efectivo suma a la caja de ESA sede.
-- fiado_limite: tope opcional de lo que se le fía a un cliente (lo pone el
-- dueño; la venta de mostrador no deja pasarlo).

ALTER TABLE clientes
  ADD COLUMN fiado_limite INT UNSIGNED DEFAULT NULL AFTER acepta_marketing;

CREATE TABLE IF NOT EXISTS fiado_movimientos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  sede_id     INT UNSIGNED DEFAULT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  tipo        ENUM('cargo','abono') NOT NULL,
  monto       INT UNSIGNED NOT NULL,
  venta_id    INT UNSIGNED DEFAULT NULL,
  metodo      ENUM('efectivo','nequi','breb') DEFAULT NULL,
  nota        VARCHAR(160) DEFAULT NULL,
  anulado     TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE SET NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_fiado_cliente (negocio_id, cliente_id, creado_en),
  INDEX idx_fiado_sede_fecha (sede_id, creado_en)
) ENGINE=InnoDB;

-- Cada recordatorio de pago que se abrió por WhatsApp. Ley 2300 de 2023:
-- como mucho uno por cliente a la semana (se revisa aquí) y solo en el
-- horario permitido (ver App\Services\HorarioCobro).
CREATE TABLE IF NOT EXISTS fiado_recordatorios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  saldo       INT UNSIGNED NOT NULL,
  enviado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_recordatorio_cliente (cliente_id, enviado_en)
) ENGINE=InnoDB;
