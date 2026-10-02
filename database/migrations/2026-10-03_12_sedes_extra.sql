-- Cobro de la sede extra del plan Pro (planes.precio_sede_extra, $30.000/mes).
-- negocios.sedes_extra: cuántas sedes por encima de las incluidas tiene
-- pagadas en el período actual (el cupo es sedes_incluidas + sedes_extra).
-- pagos_plan.concepto: 'plan' (pago o renovación del plan, que ya incluye
-- las sedes extra que el negocio tenga) o 'sede_extra' (agregar una sede a
-- mitad de período, prorrateado hasta que vence el plan). En ambos casos
-- pagos_plan.sedes_extra dice cuántas sedes extra cubre ese pago.
ALTER TABLE negocios ADD COLUMN sedes_extra TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER plan_ciclo;
ALTER TABLE pagos_plan
  ADD COLUMN concepto ENUM('plan','sede_extra') NOT NULL DEFAULT 'plan' AFTER plan_id,
  ADD COLUMN sedes_extra TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER concepto;
