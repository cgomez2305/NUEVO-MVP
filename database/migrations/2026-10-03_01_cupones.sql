-- Cupones de descuento del negocio.
--
-- Dos orígenes:
--   'panel'    el dueño crea un código para todos (VECI10, DIADELAMADRE…)
--              y lo comparte en sus estados o redes.
--   'copiloto' cupón personal que nace cuando el dueño le manda a un
--              cliente un mensaje con descuento desde el copiloto: solo
--              sirve para ese cliente (cliente_id) y una vez.
--
-- valor: porcentaje (1–90) si tipo = 'porcentaje'; pesos si tipo = 'monto'.
-- usos_maximos NULL = sin tope. vence_en NULL = no vence.
CREATE TABLE IF NOT EXISTS cupones (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id           INT UNSIGNED NOT NULL,
  codigo               VARCHAR(20)  NOT NULL,
  tipo                 ENUM('porcentaje','monto') NOT NULL DEFAULT 'porcentaje',
  valor                INT UNSIGNED NOT NULL,
  minimo_compra        INT UNSIGNED NOT NULL DEFAULT 0,
  vence_en             DATE         DEFAULT NULL,
  usos_maximos         INT UNSIGNED DEFAULT NULL,
  una_vez_por_cliente  TINYINT(1)   NOT NULL DEFAULT 1,
  cliente_id           INT UNSIGNED DEFAULT NULL,
  origen               ENUM('panel','copiloto') NOT NULL DEFAULT 'panel',
  activo               TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_cupon_codigo (negocio_id, codigo)
) ENGINE=InnoDB;

-- Cada vez que un cupón se usó, en un pedido o en una cita.
CREATE TABLE IF NOT EXISTS cupon_usos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cupon_id    INT UNSIGNED NOT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  pedido_id   INT UNSIGNED DEFAULT NULL,
  cita_id     INT UNSIGNED DEFAULT NULL,
  descuento   INT UNSIGNED NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cupon_id) REFERENCES cupones(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_cupon_usos_cupon (cupon_id, cliente_id)
) ENGINE=InnoDB;

-- pedidos.total sigue siendo lo que el cliente paga; descuento es cuánto
-- se le rebajó (por cupón o premio de fidelidad) para mostrarlo aparte.
ALTER TABLE pedidos
  ADD COLUMN descuento INT UNSIGNED NOT NULL DEFAULT 0 AFTER total,
  ADD COLUMN cupon_codigo VARCHAR(20) DEFAULT NULL AFTER descuento;

-- citas.precio sigue siendo el del servicio; lo que se paga es precio − descuento.
ALTER TABLE citas
  ADD COLUMN descuento INT UNSIGNED NOT NULL DEFAULT 0 AFTER precio,
  ADD COLUMN cupon_codigo VARCHAR(20) DEFAULT NULL AFTER descuento;
