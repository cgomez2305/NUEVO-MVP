-- 1. Códigos de un solo uso enviados por WhatsApp (OTP). Primer uso: el
--    titular confirma su WhatsApp antes de aplicar una oferta de plan. Solo
--    se guarda el HMAC del código; vence a los 10 minutos y aguanta 5 intentos.
CREATE TABLE IF NOT EXISTS verificaciones_whatsapp (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED NOT NULL,
  proposito     VARCHAR(30)  NOT NULL,
  codigo_hash   CHAR(64)     NOT NULL,
  expira_en     DATETIME     NOT NULL,
  intentos      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verificado_en DATETIME     DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_verificaciones_usuario (usuario_id, proposito, creado_en),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. "Sube tu foto sin cuenta" (el sitio lee un menú con IA sin registro):
--    una fila por lectura para el tope diario de uso de la API. No guarda la
--    foto ni la IP (solo su HMAC), ni lo que se leyó.
CREATE TABLE IF NOT EXISTS demo_ia_usos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash         CHAR(64)     NOT NULL,
  estado          VARCHAR(12)  NOT NULL,
  tokens_entrada  INT UNSIGNED NOT NULL DEFAULT 0,
  tokens_salida   INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_demo_ia_fecha (creado_en)
) ENGINE=InnoDB;
