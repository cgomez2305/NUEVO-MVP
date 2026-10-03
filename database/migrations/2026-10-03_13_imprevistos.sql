-- Imprevistos en los servicios: nada es tan exacto como una agenda promete.
-- servicios.precio_tipo: 'fijo' ($20.000), 'desde' (desde $20.000) o 'rango'
-- ($20.000 a precio_max). La cita copia ambos (como copia el nombre y el
-- precio) y guarda en precio_final lo que de verdad se cobró al terminar.
ALTER TABLE servicios
  ADD COLUMN precio_tipo ENUM('fijo','desde','rango') NOT NULL DEFAULT 'fijo' AFTER precio,
  ADD COLUMN precio_max INT UNSIGNED DEFAULT NULL AFTER precio_tipo;

-- Reglas de la agenda: minutos de colchón entre citas, cuánto se espera a un
-- cliente que llega tarde y qué pasa con su anticipo si no llega.
ALTER TABLE sedes
  ADD COLUMN colchon_min TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER intervalo_citas_min,
  ADD COLUMN tolerancia_min TINYINT UNSIGNED NOT NULL DEFAULT 15 AFTER colchon_min,
  ADD COLUMN anticipo_no_asiste ENUM('se_pierde','se_abona') NOT NULL DEFAULT 'se_pierde' AFTER tolerancia_min;

-- Citas: estados en_curso y no_asistio; duración real (iniciada/terminada);
-- retraso avisado por el negocio o por el cliente; cita que el negocio pidió
-- mover por un imprevisto (imprevisto_motivo, se limpia al reprogramar);
-- aviso de imprevisto pendiente de mandar ('retraso' o 'reprogramar');
-- ajuste de precio que el cliente aprueba o no.
ALTER TABLE citas
  MODIFY COLUMN estado ENUM('pendiente','confirmada','en_curso','completada','no_asistio','cancelada') NOT NULL DEFAULT 'pendiente',
  ADD COLUMN precio_tipo ENUM('fijo','desde','rango') NOT NULL DEFAULT 'fijo' AFTER precio,
  ADD COLUMN precio_max INT UNSIGNED DEFAULT NULL AFTER precio_tipo,
  ADD COLUMN precio_final INT UNSIGNED DEFAULT NULL AFTER precio_max,
  ADD COLUMN iniciada_en DATETIME DEFAULT NULL AFTER duracion_min,
  ADD COLUMN terminada_en DATETIME DEFAULT NULL AFTER iniciada_en,
  ADD COLUMN retraso_negocio_min SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER anticipo_estado,
  ADD COLUMN retraso_cliente_min SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER retraso_negocio_min,
  ADD COLUMN cliente_espera TINYINT(1) NOT NULL DEFAULT 0 AFTER retraso_cliente_min,
  ADD COLUMN imprevisto_motivo VARCHAR(120) DEFAULT NULL AFTER cliente_espera,
  ADD COLUMN aviso_imprevisto VARCHAR(20) DEFAULT NULL AFTER imprevisto_motivo,
  ADD COLUMN ajuste_precio INT UNSIGNED DEFAULT NULL AFTER aviso_imprevisto,
  ADD COLUMN ajuste_motivo VARCHAR(200) DEFAULT NULL AFTER ajuste_precio,
  ADD COLUMN ajuste_estado ENUM('pendiente','aprobado','rechazado') DEFAULT NULL AFTER ajuste_motivo;

-- Garantía o retoque: un bono de una sesión a $0 ligado a la cita original.
ALTER TABLE bonos
  ADD COLUMN garantia_de INT UNSIGNED DEFAULT NULL AFTER paquete_id,
  ADD CONSTRAINT fk_bonos_garantia FOREIGN KEY (garantia_de) REFERENCES citas(id) ON DELETE SET NULL;
