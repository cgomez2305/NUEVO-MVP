-- Paquetes de sesiones (p. ej. "5 cortes por $85.000") y los bonos que el
-- negocio le vende a un cliente. El bono copia lo que compró (servicio,
-- sesiones, precio): si mañana cambian o borran el paquete, el bono vale
-- igual. Cada cita reservada con el bono gasta una sesión; cancelarla la
-- devuelve.

CREATE TABLE IF NOT EXISTS paquetes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id        INT UNSIGNED      NOT NULL,
  servicio_id    INT UNSIGNED      NOT NULL,
  sesiones       TINYINT UNSIGNED  NOT NULL,
  precio         INT UNSIGNED      NOT NULL,
  vigencia_dias  SMALLINT UNSIGNED DEFAULT NULL,
  activo         TINYINT(1)        NOT NULL DEFAULT 1,
  creado_en      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bonos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id         INT UNSIGNED     NOT NULL,
  paquete_id      INT UNSIGNED     DEFAULT NULL,
  cliente_id      INT UNSIGNED     NOT NULL,
  servicio_id     INT UNSIGNED     DEFAULT NULL,
  nombre_servicio VARCHAR(120)     NOT NULL,
  sesiones_total  TINYINT UNSIGNED NOT NULL,
  precio_pagado   INT UNSIGNED     NOT NULL,
  vence_en        DATE             DEFAULT NULL,
  token           CHAR(32)         NOT NULL,
  usuario_id      INT UNSIGNED     DEFAULT NULL,
  creado_en       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_bono_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (paquete_id) REFERENCES paquetes(id) ON DELETE SET NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  INDEX idx_bonos_cliente (sede_id, cliente_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bono_usos (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bono_id   INT UNSIGNED NOT NULL,
  cita_id   INT UNSIGNED NOT NULL,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_bono_uso_cita (cita_id),
  FOREIGN KEY (bono_id) REFERENCES bonos(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
