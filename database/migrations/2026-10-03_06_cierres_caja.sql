-- Cierre de caja diario por sede: lo vendido ese día (por forma de pago),
-- la base con que se abrió, el efectivo contado y la diferencia. Un día se
-- cierra una vez; volver a cerrarlo reemplaza el cierre (queda la hora).

CREATE TABLE IF NOT EXISTS cierres_caja (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id           INT UNSIGNED NOT NULL,
  fecha             DATE         NOT NULL,
  ventas_total      INT          NOT NULL DEFAULT 0,
  base              INT          NOT NULL DEFAULT 0,
  efectivo_esperado INT          NOT NULL DEFAULT 0,
  efectivo_contado  INT          NOT NULL DEFAULT 0,
  diferencia        INT          NOT NULL DEFAULT 0,
  resumen           TEXT         NOT NULL,
  notas             VARCHAR(255) DEFAULT NULL,
  usuario_id        INT UNSIGNED DEFAULT NULL,
  creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_cierre_sede_fecha (sede_id, fecha)
) ENGINE=InnoDB;
