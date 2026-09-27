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
CREATE TABLE IF NOT EXISTS negocios (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(60)  NOT NULL UNIQUE,
  nombre            VARCHAR(120) NOT NULL,
  descripcion       VARCHAR(180) DEFAULT NULL,
  whatsapp          VARCHAR(20)  NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NOT NULL,
  inicial           CHAR(2)      DEFAULT NULL,
  color_marca       CHAR(7)      DEFAULT '#E8452C',
  menu_foto         VARCHAR(255) DEFAULT NULL,
  llave_breb_tipo   ENUM('celular','cedula','correo') DEFAULT 'celular',
  llave_breb_valor  VARCHAR(120) DEFAULT NULL,
  publicada         TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
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
