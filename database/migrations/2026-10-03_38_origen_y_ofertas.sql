-- 1. De dónde llegó cada negocio: lo que el sitio web manda a /registro
--    (utm_*, plan, ciclo, modo y código de oferta). Texto plano recortado;
--    solo se guardan valores de una lista blanca (plan, ciclo, modo).
CREATE TABLE IF NOT EXISTS negocio_origen (
  negocio_id     INT UNSIGNED NOT NULL PRIMARY KEY,
  utm_source     VARCHAR(80) DEFAULT NULL,
  utm_medium     VARCHAR(80) DEFAULT NULL,
  utm_campaign   VARCHAR(80) DEFAULT NULL,
  utm_term       VARCHAR(80) DEFAULT NULL,
  utm_content    VARCHAR(80) DEFAULT NULL,
  plan_interes   ENUM('gratis','barrio','pro') DEFAULT NULL,
  ciclo_interes  ENUM('mensual','anual') DEFAULT NULL,
  modo_interes   ENUM('pedidos','reservas') DEFAULT NULL,
  oferta_codigo  VARCHAR(20) DEFAULT NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Qué WhatsApp y qué documento (cédula o NIT) ya tuvieron un negocio en
--    Veci, guardados solo como HMAC (con la clave del servidor): sirven
--    para que una oferta "solo negocios nuevos" no se repita abriendo otra
--    cuenta. Sin llave foránea a propósito: el rastro queda aunque el
--    negocio se borre.
CREATE TABLE IF NOT EXISTS identidades_negocio (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo        ENUM('whatsapp','documento') NOT NULL,
  hash        CHAR(64)     NOT NULL,
  negocio_id  INT UNSIGNED NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_identidad (tipo, hash, negocio_id),
  INDEX idx_identidad_negocio (negocio_id)
) ENGINE=InnoDB;

-- 3. Códigos de oferta para los planes de Veci (p. ej. VECICHAT30: 30% del
--    primer mes de Barrio o Pro mensual). Fecha de fin y cupo se cambian
--    desde /admin/ofertas.
CREATE TABLE IF NOT EXISTS ofertas_plan (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo       VARCHAR(20)  NOT NULL UNIQUE,
  descripcion  VARCHAR(160) NOT NULL DEFAULT '',
  porcentaje   TINYINT UNSIGNED NOT NULL,
  vence_en     DATE         DEFAULT NULL,
  cupo_total   INT UNSIGNED DEFAULT NULL,
  activa       TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Cada canje: 'apartado' al pedir el plan (cuenta para el cupo), 'confirmado'
-- al pagarse, 'liberado' si la solicitud se cancela. Sin llave foránea al
-- negocio: el registro de canjes no se pierde.
CREATE TABLE IF NOT EXISTS ofertas_canjes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oferta_id       INT UNSIGNED NOT NULL,
  negocio_id      INT UNSIGNED NOT NULL,
  pago_plan_id    INT UNSIGNED DEFAULT NULL,
  plan_id         TINYINT UNSIGNED NOT NULL,
  whatsapp_hash   CHAR(64)     NOT NULL,
  documento_hash  CHAR(64)     NOT NULL,
  monto_lista     INT UNSIGNED NOT NULL,
  descuento       INT UNSIGNED NOT NULL,
  estado          ENUM('apartado','confirmado','liberado') NOT NULL DEFAULT 'apartado',
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmado_en   DATETIME     DEFAULT NULL,
  INDEX idx_canjes_oferta (oferta_id, estado),
  INDEX idx_canjes_negocio (negocio_id),
  INDEX idx_canjes_whatsapp (whatsapp_hash),
  INDEX idx_canjes_documento (documento_hash),
  INDEX idx_canjes_pago (pago_plan_id),
  FOREIGN KEY (oferta_id) REFERENCES ofertas_plan(id)
) ENGINE=InnoDB;

-- El pago guarda el precio de lista y el descuento: el admin ve el monto
-- esperado ya con la oferta (monto = lo que debe llegar).
ALTER TABLE pagos_plan
  ADD COLUMN monto_lista   INT UNSIGNED DEFAULT NULL AFTER monto,
  ADD COLUMN descuento     INT UNSIGNED NOT NULL DEFAULT 0 AFTER monto_lista,
  ADD COLUMN oferta_codigo VARCHAR(20)  DEFAULT NULL AFTER descuento;

-- La oferta del asistente del sitio: 30%, cupo de 300. Sin fecha de fin
-- (se pone desde el admin).
INSERT IGNORE INTO ofertas_plan (codigo, descripcion, porcentaje, cupo_total)
VALUES ('VECICHAT30', '30% del primer mes de Barrio o Pro (pago mensual), solo negocios nuevos', 30, 300);
