-- Reseñas: el negocio le pide la reseña al cliente por WhatsApp con un
-- enlace de un solo uso (/r/{token}) atado a un pedido o una cita real.
-- Solo opina quien compró. El dueño puede ocultar el COMENTARIO (insultos,
-- datos personales) pero las estrellas siempre cuentan en el promedio.

CREATE TABLE IF NOT EXISTS resenas (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id         INT UNSIGNED     NOT NULL,
  sede_id            INT UNSIGNED     NOT NULL,
  cliente_id         INT UNSIGNED     NOT NULL,
  pedido_id          INT UNSIGNED     DEFAULT NULL,
  cita_id            INT UNSIGNED     DEFAULT NULL,
  token              CHAR(32)         NOT NULL,
  estrellas          TINYINT UNSIGNED DEFAULT NULL,
  comentario         VARCHAR(400)     DEFAULT NULL,
  comentario_oculto  TINYINT(1)       NOT NULL DEFAULT 0,
  pedida_en          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  respondida_en      DATETIME         DEFAULT NULL,
  UNIQUE KEY uniq_resena_token (token),
  UNIQUE KEY uniq_resena_pedido (pedido_id),
  UNIQUE KEY uniq_resena_cita (cita_id),
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_resenas_negocio (negocio_id, respondida_en)
) ENGINE=InnoDB;
