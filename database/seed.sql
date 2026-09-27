-- Veci — datos de demostración
-- Crea el negocio "Doña María" (el mismo de la maqueta) con su catálogo,
-- clientes y un historial de pedidos pensado para que el copiloto de
-- recompra tenga algo real que mostrar apenas entras.
--
-- Pensado para correr una sola vez sobre un esquema recién creado
-- (asume que los AUTO_INCREMENT empiezan en 1).
--
-- Importar con:
--   mysql -u root -p veci < database/seed.sql
--
-- Acceso de prueba al panel: WhatsApp 3001234567 · contraseña veci123

USE veci;
SET NAMES utf8mb4;

INSERT INTO negocios
  (id, slug, nombre, descripcion, whatsapp, password_hash, inicial, color_marca,
   llave_breb_tipo, llave_breb_valor, publicada)
VALUES
  (1, 'donamaria', 'Doña María', 'Arepas y jugos naturales · Bucaramanga',
   '3001234567', '$2y$12$Rp/Uc3/Mg/f0A7wwOH/7w.OrB97It.nmx2n1sExSPVsAdf6Q/cQFW',
   'M', '#F2B632', 'celular', '3001234567', 1);

INSERT INTO productos (negocio_id, nombre, precio, categoria, color, orden) VALUES
  (1, 'Bandeja paisa',    28000, 'Comidas', '#E8452C', 1),
  (1, 'Arepa con queso',   6000, 'Comidas', '#F2B632', 2),
  (1, 'Jugo natural',      5000, 'Bebidas', '#5B7F3A', 3),
  (1, 'Café tinto',        2500, 'Bebidas', '#1B1A17', 4);

-- Clientes: dos con patrón de recompra roto (para que el copiloto los marque)
-- y tres con hábito normal (para que el copiloto los deje en paz).
INSERT INTO clientes (id, negocio_id, nombre, telefono, autorizo_datos, autorizado_en) VALUES
  (1, 1, 'Doña Marta',       '3001112233', 1, NOW()),
  (2, 1, 'Carlos Ramírez',   '3009998877', 1, NOW()),
  (3, 1, 'Laura Gómez',      '3012223344', 1, NOW()),
  (4, 1, 'Pedro Ruiz',       '3023334455', 1, NOW()),
  (5, 1, 'Ana Torres',       '3034445566', 1, NOW());

-- Doña Marta: pedía cada ~7 días y dejó de pedir hace 18 (se debe reactivar).
INSERT INTO pedidos (negocio_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 46 DAY),
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 39 DAY),
  (1, 1, 39000, 'breb', 'entregado', CURDATE() - INTERVAL 32 DAY),
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 25 DAY),
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 18 DAY);

-- Carlos Ramírez: pide cada 5-6 días y pidió anteayer (no necesita reactivación).
INSERT INTO pedidos (negocio_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 2, 28000, 'nequi', 'entregado', CURDATE() - INTERVAL 19 DAY),
  (1, 2, 28000, 'nequi', 'entregado', CURDATE() - INTERVAL 13 DAY),
  (1, 2, 33000, 'nequi', 'entregado', CURDATE() - INTERVAL 8 DAY),
  (1, 2, 28000, 'nequi', 'entregado', CURDATE() - INTERVAL 2 DAY);

-- Laura Gómez: apenas un pedido (aún no hay suficiente historia).
INSERT INTO pedidos (negocio_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 3, 8500, 'efectivo', 'entregado', CURDATE() - INTERVAL 3 DAY);

-- Pedro Ruiz: pedía cada ~10 días y dejó de pedir hace 35 (se debe reactivar).
INSERT INTO pedidos (negocio_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 4, 28000, 'breb', 'entregado', CURDATE() - INTERVAL 65 DAY),
  (1, 4, 28000, 'breb', 'entregado', CURDATE() - INTERVAL 55 DAY),
  (1, 4, 30500, 'breb', 'entregado', CURDATE() - INTERVAL 45 DAY),
  (1, 4, 28000, 'breb', 'entregado', CURDATE() - INTERVAL 35 DAY);

-- Ana Torres: pide toda semana, el último pedido fue ayer (no necesita reactivación).
INSERT INTO pedidos (negocio_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 5, 11000, 'breb', 'entregado', CURDATE() - INTERVAL 22 DAY),
  (1, 5, 11000, 'breb', 'entregado', CURDATE() - INTERVAL 15 DAY),
  (1, 5, 13500, 'breb', 'entregado', CURDATE() - INTERVAL 8 DAY),
  (1, 5, 11000, 'breb', 'entregado', CURDATE() - INTERVAL 1 DAY);

-- Un renglón de items por pedido (suficiente para que el panel de pedidos
-- muestre algo real; el copiloto solo necesita cliente_id + creado_en).
INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, precio_unitario, cantidad)
SELECT id, 1, 'Bandeja paisa', 28000, 1 FROM pedidos;
