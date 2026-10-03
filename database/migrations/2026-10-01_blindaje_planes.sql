-- Migración para una base de datos EXISTENTE (instalaciones nuevas ya
-- traen esto en database/schema.sql).
--
-- Blindaje contra aprovechamiento de planes + correcciones de la auditoría
-- de seguridad:
--   1. admins.intentos_fallidos/bloqueado_hasta — el login de /admin no
--      tenía protección de fuerza bruta, y un admin comprometido puede
--      confirmar pagos falsos (activar Barrio/Pro gratis) y suspender o
--      reactivar cualquier cuenta.
--   2. limites_tasa — limitador de tasa genérico (registro multicuenta,
--      pedidos/citas falsos que agotarían el cupo Gratis de un negocio,
--      login contra muchas cuentas, bombardeo de correos de recuperación).
--   3. usuarios.reset_token pasa a guardar el SHA-256 del token, no el
--      token: los enlaces de recuperación que estuvieran vigentes al
--      aplicar esto dejan de funcionar (vencían en 1 hora de todas formas).
--
-- El resto de los fixes (límite de sedes por plan, plan vigente en tiempo
-- real, monto re-verificado al confirmar un pago, monto en el webhook) son
-- solo lógica PHP, sin cambios de esquema.
--
-- Aplicar UNA sola vez, con un backup reciente a mano:
--   mysql -u <usuario> -p <base_de_datos> < database/migrations/2026-10-01_blindaje_planes.sql

USE veci;

ALTER TABLE admins
  ADD COLUMN intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash,
  ADD COLUMN bloqueado_hasta DATETIME DEFAULT NULL AFTER intentos_fallidos;

CREATE TABLE IF NOT EXISTS limites_tasa (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  accion      VARCHAR(30)  NOT NULL,
  clave       VARCHAR(80)  NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_limites_tasa (accion, clave, creado_en),
  INDEX idx_limites_tasa_fecha (creado_en)
) ENGINE=InnoDB;

UPDATE usuarios SET reset_token = NULL, reset_token_expira = NULL WHERE reset_token IS NOT NULL;
