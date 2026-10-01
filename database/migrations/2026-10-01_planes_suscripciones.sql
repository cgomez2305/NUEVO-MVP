-- Migración para una base de datos EXISTENTE (instalaciones nuevas ya
-- traen todo esto en database/schema.sql).
--
-- Agrega el sistema de planes y suscripciones completo:
--   planes          — los 3 planes (gratis/barrio/pro), precios y límites.
--   negocios        — plan_id, plan_estado, plan_vence_en, plan_ciclo.
--   pagos_plan      — registro del cobro manual verificado (Bre-B + admin).
--   usos_ia         — cuenta los análisis reales con Claude, por negocio.
--
-- Todo negocio existente queda en plan_id=1 (gratis) con plan_estado
-- 'activo': no se bloquea ni se degrada nada por aplicar esta migración,
-- simplemente empiezan a contar los límites del plan Gratis desde ahora.
--
-- Aplicar UNA sola vez, con un backup reciente a mano:
--   mysql -u <usuario> -p <base_de_datos> < database/migrations/2026-10-01_planes_suscripciones.sql

USE veci;

CREATE TABLE IF NOT EXISTS planes (
  id                             TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                         VARCHAR(20)  NOT NULL UNIQUE,
  precio_mensual                 INT UNSIGNED NOT NULL,
  precio_anual                   INT UNSIGNED NOT NULL,
  limite_pedidos_mes             SMALLINT UNSIGNED DEFAULT NULL,
  limite_ia_mes                  TINYINT UNSIGNED DEFAULT NULL,
  incluye_copiloto               TINYINT(1)   NOT NULL DEFAULT 0,
  incluye_estadisticas_completas TINYINT(1)   NOT NULL DEFAULT 0,
  incluye_multisede              TINYINT(1)   NOT NULL DEFAULT 0,
  sedes_incluidas                TINYINT UNSIGNED DEFAULT NULL,
  precio_sede_extra              INT UNSIGNED DEFAULT NULL,
  creado_en                      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO planes
  (id, nombre, precio_mensual, precio_anual, limite_pedidos_mes, limite_ia_mes,
   incluye_copiloto, incluye_estadisticas_completas, incluye_multisede, sedes_incluidas, precio_sede_extra)
VALUES
  (1, 'gratis', 0,      0,       50,   3,    0, 0, 0, 1, NULL),
  (2, 'barrio', 59000,  708000,  NULL, NULL, 1, 0, 0, 1, NULL),
  (3, 'pro',    129000, 1548000, NULL, NULL, 1, 1, 1, 3, 30000);

ALTER TABLE negocios
  ADD COLUMN plan_id       TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER tipo_negocio,
  ADD COLUMN plan_estado   ENUM('activo','vencido','degradado_a_gratis') NOT NULL DEFAULT 'activo' AFTER plan_id,
  ADD COLUMN plan_vence_en DATE DEFAULT NULL AFTER plan_estado,
  ADD COLUMN plan_ciclo    ENUM('mensual','anual') NOT NULL DEFAULT 'mensual' AFTER plan_vence_en,
  ADD FOREIGN KEY (plan_id) REFERENCES planes(id);

CREATE TABLE IF NOT EXISTS pagos_plan (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id     INT UNSIGNED NOT NULL,
  plan_id        TINYINT UNSIGNED NOT NULL,
  monto          INT UNSIGNED NOT NULL,
  metodo_pago    VARCHAR(30)  NOT NULL DEFAULT 'breb_manual',
  ciclo          ENUM('mensual','anual') NOT NULL DEFAULT 'mensual',
  periodo_inicio DATE         NOT NULL,
  periodo_fin    DATE         NOT NULL,
  confirmado_por INT UNSIGNED DEFAULT NULL,
  confirmado_en  DATETIME     DEFAULT NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES planes(id),
  FOREIGN KEY (confirmado_por) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_pagos_plan_pendientes (confirmado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usos_ia (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  sede_id     INT UNSIGNED NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_usos_ia_negocio_fecha (negocio_id, creado_en)
) ENGINE=InnoDB;
