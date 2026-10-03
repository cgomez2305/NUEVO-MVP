-- Belleza (y cualquier negocio con equipo): el cliente elige a SU barbero.
-- empleados: foto, especialidad y una frase; % de comisión (NULL = no va por
-- comisión); horario propio (mismo JSON que sedes.horario_atencion; NULL =
-- el de la sede).
ALTER TABLE empleados
  ADD COLUMN foto VARCHAR(255) DEFAULT NULL AFTER nombre,
  ADD COLUMN especialidad VARCHAR(80) DEFAULT NULL AFTER foto,
  ADD COLUMN bio VARCHAR(240) DEFAULT NULL AFTER especialidad,
  ADD COLUMN comision_pct TINYINT UNSIGNED DEFAULT NULL AFTER bio,
  ADD COLUMN horario_atencion TEXT DEFAULT NULL AFTER comision_pct;

-- Portafolio: fotos de trabajos de cada profesional (máximo 9).
CREATE TABLE IF NOT EXISTS empleado_fotos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empleado_id INT UNSIGNED NOT NULL,
  ruta        VARCHAR(255) NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
  INDEX idx_empleado_fotos (empleado_id)
) ENGINE=InnoDB;

-- Qué servicios hace cada profesional, con precio y duración propios si
-- difieren (NULL = los del servicio). Un profesional SIN filas aquí hace
-- todos los servicios al precio normal.
CREATE TABLE IF NOT EXISTS empleado_servicios (
  empleado_id  INT UNSIGNED NOT NULL,
  servicio_id  INT UNSIGNED NOT NULL,
  precio       INT UNSIGNED DEFAULT NULL,
  duracion_min SMALLINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (empleado_id, servicio_id),
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Adicionales que se suman al reservar (barba, cejas, mascarilla): precio y
-- minutos extra. servicio_id NULL = se ofrece con todos los servicios.
CREATE TABLE IF NOT EXISTS adicionales (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id      INT UNSIGNED NOT NULL,
  servicio_id  INT UNSIGNED DEFAULT NULL,
  nombre       VARCHAR(80)  NOT NULL,
  precio       INT UNSIGNED NOT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
  INDEX idx_adicionales_sede (sede_id)
) ENGINE=InnoDB;

-- Lo que se sumó a cada cita (copiado: si el adicional cambia o se borra, la
-- cita guarda lo que se reservó). citas.precio y duracion_min ya los incluyen.
CREATE TABLE IF NOT EXISTS cita_adicionales (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cita_id      INT UNSIGNED NOT NULL,
  adicional_id INT UNSIGNED DEFAULT NULL,
  nombre       VARCHAR(80)  NOT NULL,
  precio       INT UNSIGNED NOT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  FOREIGN KEY (adicional_id) REFERENCES adicionales(id) ON DELETE SET NULL,
  INDEX idx_cita_adicionales (cita_id)
) ENGINE=InnoDB;

-- Fila virtual para clientes sin cita (la barbería del barrio vive de quien
-- llega sin avisar). sedes.fila_abierta: el dueño la abre y la cierra.
ALTER TABLE sedes ADD COLUMN fila_abierta TINYINT(1) NOT NULL DEFAULT 0 AFTER anticipo_no_asiste;

CREATE TABLE IF NOT EXISTS turnos_fila (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id      INT UNSIGNED NOT NULL,
  cliente_id   INT UNSIGNED NOT NULL,
  servicio_id  INT UNSIGNED DEFAULT NULL,
  empleado_id  INT UNSIGNED DEFAULT NULL,
  estado       ENUM('esperando','llamado','atendido','se_fue') NOT NULL DEFAULT 'esperando',
  token        CHAR(32)     NOT NULL,
  cita_id      INT UNSIGNED DEFAULT NULL,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  llamado_en   DATETIME     DEFAULT NULL,
  cerrado_en   DATETIME     DEFAULT NULL,
  UNIQUE KEY uniq_turno_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE SET NULL,
  INDEX idx_turnos_sede (sede_id, estado, creado_en)
) ENGINE=InnoDB;
