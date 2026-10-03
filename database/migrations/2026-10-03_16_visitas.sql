-- Visitas a domicilio (plomero, técnico de aires, electricista): un negocio
-- de reservas que va a la casa del cliente en vez de recibirlo en el local.
-- Todo lo de las citas (agenda, equipo, imprevistos, anticipos, garantía)
-- sirve igual; aquí solo lo que cambia cuando el servicio es en la casa.
ALTER TABLE negocios
  ADD COLUMN modalidad ENUM('local','domicilio') NOT NULL DEFAULT 'local' AFTER tipo_negocio;

-- Servicios que se repiten (mantenimiento de aires cada 6 meses, control
-- cada 3): NULL = no se repite. Alimenta la lista de "Mantenimientos".
ALTER TABLE servicios
  ADD COLUMN repetir_cada_meses TINYINT UNSIGNED DEFAULT NULL;

-- La visita: dónde, qué pasa y la franja de llegada que se le promete al
-- cliente ("llegamos entre 8 y 12"). fecha_hora sigue siendo el turno que
-- se reservó dentro de esa franja (lo que ocupa la agenda del técnico).
ALTER TABLE citas
  ADD COLUMN direccion VARCHAR(200) DEFAULT NULL,
  ADD COLUMN direccion_referencia VARCHAR(160) DEFAULT NULL,
  ADD COLUMN zona_nombre VARCHAR(80) DEFAULT NULL,
  ADD COLUMN recargo_zona INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN problema TEXT DEFAULT NULL,
  ADD COLUMN franja_inicio TIME DEFAULT NULL,
  ADD COLUMN franja_fin TIME DEFAULT NULL,
  ADD COLUMN en_camino_en DATETIME DEFAULT NULL,
  ADD COLUMN llegada_estimada DATETIME DEFAULT NULL,
  -- El cliente pidió que le recuerden el próximo mantenimiento (permiso
  -- específico para ese mensaje, Ley 1581) y cuándo se le recordó.
  ADD COLUMN recordar_repetir TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN repetir_avisado_en DATETIME DEFAULT NULL;

-- Fotos de la visita: las que manda el cliente al pedirla (cómo está el
-- daño) y la evidencia del técnico (antes y después). Se guardan FUERA de
-- public/ (storage/visitas/): son fotos de adentro de una casa y solo las
-- ven el negocio y el cliente con su enlace.
CREATE TABLE IF NOT EXISTS cita_fotos (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cita_id   INT UNSIGNED NOT NULL,
  archivo   VARCHAR(80)  NOT NULL,
  momento   ENUM('cliente','antes','despues') NOT NULL,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_cita_fotos (cita_id, momento)
) ENGINE=InnoDB;
