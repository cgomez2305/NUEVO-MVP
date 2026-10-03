-- "Se me complicó el día" de UNA persona del equipo: ese día no recibe
-- reservas ni reprogramaciones (antes solo se cerraba el día de todo el
-- negocio, y a la persona enferma la seguían agendando).
CREATE TABLE IF NOT EXISTS empleado_dias_libres (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empleado_id  INT UNSIGNED NOT NULL,
  fecha        DATE         NOT NULL,
  motivo       VARCHAR(120) DEFAULT NULL,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_dia_libre (empleado_id, fecha)
) ENGINE=InnoDB;
