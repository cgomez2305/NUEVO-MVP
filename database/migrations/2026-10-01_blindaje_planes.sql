-- Migración para una base de datos EXISTENTE (instalaciones nuevas ya
-- traen esto en database/schema.sql).
--
-- Cierra 3 huecos encontrados al auditar cómo se podía abusar del sistema
-- de planes para no pagar nunca:
--   1. admins.intentos_fallidos/bloqueado_hasta — el login de /admin no
--      tenía ninguna protección de fuerza bruta, y un admin comprometido
--      puede confirmar pagos falsos (activar Barrio/Pro gratis) y
--      suspender/reactivar cualquier cuenta.
--   2. registros_ip — cuenta cuántas cuentas nuevas se crearon desde la
--      misma IP, para frenar la multiplicación de cupos de Gratis vía
--      multicuenta (ver AuthController::registrar).
--
-- El resto de los fixes (límite de sedes por plan, plan vigente en tiempo
-- real, monto re-verificado al confirmar un pago) son solo lógica PHP,
-- sin cambios de esquema.
--
-- Aplicar UNA sola vez, con un backup reciente a mano:
--   mysql -u <usuario> -p <base_de_datos> < database/migrations/2026-10-01_blindaje_planes.sql

USE veci;

ALTER TABLE admins
  ADD COLUMN intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash,
  ADD COLUMN bloqueado_hasta DATETIME DEFAULT NULL AFTER intentos_fallidos;

CREATE TABLE IF NOT EXISTS registros_ip (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip          VARCHAR(45)  NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_registros_ip_ip_fecha (ip, creado_en)
) ENGINE=InnoDB;
