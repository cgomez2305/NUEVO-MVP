-- Salud (decisiones del dueño, 2026-10-03):
-- 1. Aprobar el plan en el consultorio: muchos pacientes (sobre todo los
--    mayores) no abren enlaces. Queda por dónde se aprobó y quién lo marcó.
-- 2. Rehacer un plan vencido, rechazado o cancelado: el nuevo nace de las
--    fases del anterior (rehecho_de) y el viejo no se edita, para que el
--    historial diga la verdad.
ALTER TABLE planes_tratamiento
  ADD COLUMN aprobado_canal ENUM('enlace','consultorio') DEFAULT NULL AFTER respondido_en,
  ADD COLUMN aprobado_por   INT UNSIGNED DEFAULT NULL AFTER aprobado_canal,
  ADD COLUMN rehecho_de     INT UNSIGNED DEFAULT NULL AFTER aprobado_por,
  ADD CONSTRAINT fk_planes_aprobado_por FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_planes_rehecho_de FOREIGN KEY (rehecho_de) REFERENCES planes_tratamiento(id) ON DELETE SET NULL;

UPDATE planes_tratamiento SET aprobado_canal = 'enlace' WHERE estado IN ('aprobado', 'terminado') AND respondido_en IS NOT NULL;
