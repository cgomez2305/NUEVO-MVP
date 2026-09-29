-- Veci — esquema MySQL
-- Vende por WhatsApp sin comisión. Y haz que vuelvan.
--
-- Importar con:
--   mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS veci
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE veci;
SET NAMES utf8mb4;

-- Un negocio = un comerciante con su tienda propia.
-- tipo_negocio decide qué flujo usa: catálogo con carrito ('pedidos', comida,
-- tiendas) o servicios con cita previa ('reservas', peluquerías, talleres,
-- consultorios, spas...). horario_atencion guarda la disponibilidad semanal
-- como JSON: {"1":["09:00","18:00"], ...} con 1=lunes .. 7=domingo, un día
-- ausente del objeto significa que ese día el negocio está cerrado.
CREATE TABLE IF NOT EXISTS negocios (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug                VARCHAR(60)  NOT NULL UNIQUE,
  nombre              VARCHAR(120) NOT NULL,
  descripcion         VARCHAR(180) DEFAULT NULL,
  whatsapp            VARCHAR(20)  NOT NULL UNIQUE,
  password_hash       VARCHAR(255) NOT NULL,
  inicial             CHAR(2)      DEFAULT NULL,
  color_marca         CHAR(7)      DEFAULT '#E8452C',
  menu_foto           VARCHAR(255) DEFAULT NULL,
  llave_breb_tipo     ENUM('celular','cedula','correo') DEFAULT 'celular',
  llave_breb_valor    VARCHAR(120) DEFAULT NULL,
  tipo_negocio        ENUM('pedidos','reservas') NOT NULL DEFAULT 'pedidos',
  horario_atencion    JSON         DEFAULT NULL,
  intervalo_citas_min SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  publicada           TINYINT(1)   NOT NULL DEFAULT 0,
  intentos_fallidos   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta     DATETIME     DEFAULT NULL,
  creado_en           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Catálogo del negocio, extraído por la IA o cargado a mano.
CREATE TABLE IF NOT EXISTS productos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  precio      INT UNSIGNED NOT NULL,
  categoria   VARCHAR(60)  NOT NULL DEFAULT 'General',
  color       CHAR(7)      NOT NULL DEFAULT '#5B7F3A',
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  agotado     TINYINT(1)   NOT NULL DEFAULT 0,
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  INDEX idx_productos_negocio (negocio_id, activo)
) ENGINE=InnoDB;

-- La base de clientes: el activo que el negocio no tenía antes.
-- autorizo_datos guarda el check explícito del checkout (Ley 1581 de 2012).
CREATE TABLE IF NOT EXISTS clientes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(120) NOT NULL,
  telefono        VARCHAR(20)  NOT NULL,
  autorizo_datos  TINYINT(1)   NOT NULL DEFAULT 0,
  autorizado_en   DATETIME     DEFAULT NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_cliente_por_negocio (negocio_id, telefono)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id    INT UNSIGNED NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  total         INT UNSIGNED NOT NULL,
  metodo_pago   ENUM('breb','nequi','efectivo') NOT NULL DEFAULT 'breb',
  estado        ENUM('pendiente','pagado','en_cocina','en_camino','entregado','cancelado')
                NOT NULL DEFAULT 'pendiente',
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  INDEX idx_pedidos_negocio_fecha (negocio_id, creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedido_items (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id         INT UNSIGNED NOT NULL,
  producto_id       INT UNSIGNED DEFAULT NULL,
  nombre_producto   VARCHAR(120) NOT NULL,
  precio_unitario   INT UNSIGNED NOT NULL,
  cantidad          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Historial de mensajes que el copiloto sugirió y el dueño mandó,
-- para no repetir el mismo cliente todos los días.
CREATE TABLE IF NOT EXISTS mensajes_copiloto (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  mensaje     TEXT NOT NULL,
  enviado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Catálogo de servicios para negocios tipo 'reservas' (equivalente a
-- productos, pero con duración: cada servicio ocupa un bloque de agenda).
CREATE TABLE IF NOT EXISTS servicios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id    INT UNSIGNED NOT NULL,
  nombre        VARCHAR(120) NOT NULL,
  precio        INT UNSIGNED NOT NULL,
  duracion_min  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  color         CHAR(7)      NOT NULL DEFAULT '#5B7F3A',
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  agotado       TINYINT(1)   NOT NULL DEFAULT 0,
  orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  INDEX idx_servicios_negocio (negocio_id, activo)
) ENGINE=InnoDB;

-- Empleados/recursos de un negocio de reservas. Si un negocio no tiene
-- ninguno, las citas se agendan igual que antes (recurso único implícito).
-- Si tiene al menos uno, el cliente elige con quién agenda y la
-- disponibilidad se calcula por empleado, no por negocio.
CREATE TABLE IF NOT EXISTS empleados (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  INDEX idx_empleados_negocio (negocio_id, activo)
) ENGINE=InnoDB;

-- Días en que el negocio no atiende aunque su horario semanal lo permita
-- (vacaciones, festivos). La disponibilidad de citas los excluye.
CREATE TABLE IF NOT EXISTS fechas_bloqueadas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  fecha       DATE         NOT NULL,
  motivo      VARCHAR(120) DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_fecha_bloqueada (negocio_id, fecha)
) ENGINE=InnoDB;

-- Una cita = una reserva de un cliente para un servicio, en una fecha y hora.
-- duracion_min queda copiado del servicio al momento de reservar, así si el
-- dueño cambia la duración después no descuadra las citas ya agendadas.
CREATE TABLE IF NOT EXISTS citas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id    INT UNSIGNED NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  servicio_id   INT UNSIGNED DEFAULT NULL,
  empleado_id   INT UNSIGNED DEFAULT NULL,
  nombre_servicio VARCHAR(120) NOT NULL,
  precio        INT UNSIGNED NOT NULL,
  fecha_hora    DATETIME     NOT NULL,
  duracion_min  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  estado        ENUM('pendiente','confirmada','completada','cancelada')
                NOT NULL DEFAULT 'pendiente',
  notas         VARCHAR(255) DEFAULT NULL,
  token_gestion CHAR(32)     DEFAULT NULL,
  recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
  INDEX idx_citas_negocio_fecha (negocio_id, fecha_hora),
  UNIQUE KEY uniq_citas_token (token_gestion)
) ENGINE=InnoDB;
