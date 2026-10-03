-- Salud (odontología, fisioterapia, psicología, nutrición): un negocio de
-- reservas con rubro 'salud'. Veci NO es una historia clínica (eso lo
-- regula la Resolución 1995 de 1999 y exige otro tipo de software): aquí
-- solo va lo comercial y de agenda del tratamiento.
ALTER TABLE negocios
  ADD COLUMN rubro ENUM('general','salud') NOT NULL DEFAULT 'general' AFTER modalidad;

-- Motivo de consulta: dato de salud = dato sensible (Ley 1581, art. 5-6).
-- Es opcional y solo se guarda con autorización expresa y separada.
ALTER TABLE citas
  ADD COLUMN motivo_consulta VARCHAR(500) DEFAULT NULL,
  ADD COLUMN autorizo_sensibles_en DATETIME DEFAULT NULL,
  ADD COLUMN plan_id INT UNSIGNED DEFAULT NULL,
  ADD COLUMN plan_fase_id INT UNSIGNED DEFAULT NULL,
  ADD INDEX idx_citas_plan (plan_id);

-- Plan de tratamiento: un presupuesto por fases (p. ej. ortodoncia:
-- valoración, brackets, 12 controles) que el paciente aprueba desde su
-- enlace y va pagando con abonos. Las citas del plan se vinculan a su fase
-- y valen $0 por sí solas: la plata entra por los abonos.
CREATE TABLE IF NOT EXISTS planes_tratamiento (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id       INT UNSIGNED NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  token         CHAR(32)     NOT NULL,
  titulo        VARCHAR(120) NOT NULL,
  estado        ENUM('propuesto','aprobado','rechazado','terminado','cancelado') NOT NULL DEFAULT 'propuesto',
  total         INT UNSIGNED NOT NULL DEFAULT 0,
  validez_dias  TINYINT UNSIGNED NOT NULL DEFAULT 30,
  nota          VARCHAR(500) DEFAULT NULL,
  respondido_en DATETIME     DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_plan_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  INDEX idx_planes_sede (sede_id, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plan_fases (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id     INT UNSIGNED NOT NULL,
  orden       TINYINT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  sesiones    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  valor       INT UNSIGNED NOT NULL,
  FOREIGN KEY (plan_id) REFERENCES planes_tratamiento(id) ON DELETE CASCADE,
  INDEX idx_plan_fases (plan_id, orden)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plan_abonos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id     INT UNSIGNED NOT NULL,
  sede_id     INT UNSIGNED NOT NULL,
  monto       INT UNSIGNED NOT NULL,
  metodo      ENUM('efectivo','nequi','breb','tarjeta') NOT NULL DEFAULT 'efectivo',
  nota        VARCHAR(160) DEFAULT NULL,
  anulado     TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES planes_tratamiento(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_plan_abonos_sede (sede_id, creado_en)
) ENGINE=InnoDB;

-- Recordatorios de saldo (Ley 2300 de 2023: en horario permitido y máximo
-- uno por semana, igual que el fiado).
ALTER TABLE planes_tratamiento
  ADD COLUMN saldo_recordado_en DATETIME DEFAULT NULL;
