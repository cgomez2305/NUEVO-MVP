-- Blindaje de cuentas.
--
-- 1. El bloqueo por intentos ya no es por cuenta (cualquiera que supiera el
--    número de un dueño podía dejarlo por fuera 15 minutos equivocándose 5
--    veces): ahora va por cuenta + IP (o + celular de confianza) en
--    limites_tasa. Las columnas viejas sobran.
ALTER TABLE usuarios DROP COLUMN intentos_fallidos, DROP COLUMN bloqueado_hasta;
ALTER TABLE admins DROP COLUMN intentos_fallidos, DROP COLUMN bloqueado_hasta;

-- 2. Celulares de confianza: donde el usuario ya entró con su contraseña.
--    Si alguien ataca la cuenta desde muchas IPs, desde un celular conocido
--    se sigue entrando. Solo se guarda el hash del token de la cookie.
CREATE TABLE IF NOT EXISTS dispositivos_confianza (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  descripcion VARCHAR(80)  NOT NULL DEFAULT '',
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_dispositivo_token (token_hash),
  INDEX idx_dispositivo_usuario (usuario_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Bitácora de seguridad: quién entró, desde dónde, qué cambió en la
--    cuenta (contraseña, colaboradores, exportes). El dueño la ve en Mi
--    cuenta. La IP se guarda recortada (sin el último bloque).
CREATE TABLE IF NOT EXISTS eventos_seguridad (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED DEFAULT NULL,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  admin_id    INT UNSIGNED DEFAULT NULL,
  tipo        VARCHAR(40)  NOT NULL,
  detalle     VARCHAR(255) NOT NULL DEFAULT '',
  ip          VARCHAR(45)  NOT NULL DEFAULT '',
  descripcion VARCHAR(80)  NOT NULL DEFAULT '',
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_eventos_negocio (negocio_id, creado_en),
  INDEX idx_eventos_admin (admin_id, creado_en)
) ENGINE=InnoDB;

-- 4. Segundo factor (TOTP, apps tipo Google Authenticator) para el panel
--    interno: un admin confirma pagos y suspende negocios. Se activa con
--    bin/admin_2fa.php. totp_ultimo_paso impide reusar un código.
ALTER TABLE admins
  ADD COLUMN totp_secreto VARCHAR(64) DEFAULT NULL,
  ADD COLUMN totp_ultimo_paso BIGINT UNSIGNED DEFAULT NULL;
