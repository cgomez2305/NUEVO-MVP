-- Veci — datos de demostración
-- Crea el negocio "Doña María" (el mismo de la maqueta) con su catálogo,
-- clientes y un historial de pedidos pensado para que el copiloto de
-- recompra tenga algo real que mostrar apenas entras. Incluye una segunda
-- sede para probar multi-sede, y un colaborador para probar roles.
--
-- Pensado para correr una sola vez sobre un esquema recién creado
-- (asume que los AUTO_INCREMENT empiezan en 1).
--
-- Importar con:
--   mysql -u root -p veci < database/seed.sql
--
-- Acceso de prueba al panel (dueño): WhatsApp 3001234567 · contraseña veci123
-- Acceso de prueba (colaborador de Salón Bonita): WhatsApp 3005550000 · contraseña veci123

USE veci;
SET NAMES utf8mb4;

INSERT INTO negocios (id, nombre, color_marca, tipo_negocio) VALUES
  (1, 'Doña María', '#F2B632', 'pedidos');

INSERT INTO usuarios (id, negocio_id, nombre, whatsapp, password_hash, rol) VALUES
  (1, 1, 'Doña María', '3001234567', '$2y$12$Rp/Uc3/Mg/f0A7wwOH/7w.OrB97It.nmx2n1sExSPVsAdf6Q/cQFW', 'dueno');

INSERT INTO sedes
  (id, negocio_id, slug, nombre, descripcion, whatsapp, inicial, llave_breb_tipo, llave_breb_valor, publicada)
VALUES
  (1, 1, 'donamaria', 'Doña María', 'Arepas y jugos naturales · Bucaramanga',
   '3001234567', 'M', 'celular', '3001234567', 1);

-- Segunda sede del mismo negocio, para probar el selector de sedes: su
-- propio catálogo y su propia tienda pública, cliente compartido con la
-- sede 1 (mismo negocio_id en clientes/copiloto).
INSERT INTO sedes
  (id, negocio_id, slug, nombre, descripcion, whatsapp, inicial, llave_breb_tipo, llave_breb_valor, publicada)
VALUES
  (3, 1, 'donamaria-centro', 'Doña María Centro', 'Nuestra segunda sede, en el centro',
   '3001239999', 'M', 'celular', '3001239999', 1);

INSERT INTO productos (sede_id, nombre, precio, categoria, color, orden) VALUES
  (1, 'Bandeja paisa',    28000, 'Comidas', '#E8452C', 1),
  (1, 'Arepa con queso',   6000, 'Comidas', '#F2B632', 2),
  (1, 'Jugo natural',      5000, 'Bebidas', '#5B7F3A', 3),
  (1, 'Café tinto',        2500, 'Bebidas', '#1B1A17', 4),
  (3, 'Bandeja paisa',    29000, 'Comidas', '#E8452C', 1),
  (3, 'Jugo natural',      5500, 'Bebidas', '#5B7F3A', 2);

-- Clientes: dos con patrón de recompra roto (para que el copiloto los marque)
-- y tres con hábito normal (para que el copiloto los deje en paz).
INSERT INTO clientes (id, negocio_id, nombre, telefono, autorizo_datos, autorizado_en) VALUES
  (1, 1, 'Doña Marta',       '3001112233', 1, NOW()),
  (2, 1, 'Carlos Ramírez',   '3009998877', 1, NOW()),
  (3, 1, 'Laura Gómez',      '3012223344', 1, NOW()),
  (4, 1, 'Pedro Ruiz',       '3023334455', 1, NOW()),
  (5, 1, 'Ana Torres',       '3034445566', 1, NOW());

-- Doña Marta: pedía cada ~7 días y dejó de pedir hace 18 (se debe reactivar).
INSERT INTO pedidos (sede_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 46 DAY),
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 39 DAY),
  (1, 1, 39000, 'breb', 'entregado', CURDATE() - INTERVAL 32 DAY),
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 25 DAY),
  (1, 1, 34000, 'breb', 'entregado', CURDATE() - INTERVAL 18 DAY);

-- Carlos Ramírez: pide cada 5-6 días y pidió anteayer (no necesita reactivación).
INSERT INTO pedidos (sede_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 2, 28000, 'nequi', 'entregado', CURDATE() - INTERVAL 19 DAY),
  (1, 2, 28000, 'nequi', 'entregado', CURDATE() - INTERVAL 13 DAY),
  (1, 2, 33000, 'nequi', 'entregado', CURDATE() - INTERVAL 8 DAY),
  (1, 2, 28000, 'nequi', 'entregado', CURDATE() - INTERVAL 2 DAY);

-- Laura Gómez: apenas un pedido (aún no hay suficiente historia).
INSERT INTO pedidos (sede_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 3, 8500, 'efectivo', 'entregado', CURDATE() - INTERVAL 3 DAY);

-- Pedro Ruiz: pedía cada ~10 días y dejó de pedir hace 35 (se debe reactivar).
INSERT INTO pedidos (sede_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 4, 28000, 'breb', 'entregado', CURDATE() - INTERVAL 65 DAY),
  (1, 4, 28000, 'breb', 'entregado', CURDATE() - INTERVAL 55 DAY),
  (1, 4, 30500, 'breb', 'entregado', CURDATE() - INTERVAL 45 DAY),
  (1, 4, 28000, 'breb', 'entregado', CURDATE() - INTERVAL 35 DAY);

-- Ana Torres: pide toda semana, el último pedido fue ayer (no necesita reactivación).
INSERT INTO pedidos (sede_id, cliente_id, total, metodo_pago, estado, creado_en) VALUES
  (1, 5, 11000, 'breb', 'entregado', CURDATE() - INTERVAL 22 DAY),
  (1, 5, 11000, 'breb', 'entregado', CURDATE() - INTERVAL 15 DAY),
  (1, 5, 13500, 'breb', 'entregado', CURDATE() - INTERVAL 8 DAY),
  (1, 5, 11000, 'breb', 'entregado', CURDATE() - INTERVAL 1 DAY);

-- Un renglón de items por pedido (suficiente para que el panel de pedidos
-- muestre algo real; el copiloto solo necesita cliente_id + creado_en).
INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, precio_unitario, cantidad)
SELECT id, 1, 'Bandeja paisa', 28000, 1 FROM pedidos;

-- Segundo negocio de ejemplo, tipo 'reservas': una peluquería, para mostrar
-- el flujo de servicios con cita previa (distinto de pedidos con carrito).
-- Acceso de prueba al panel: WhatsApp 3005556677 · contraseña veci123
INSERT INTO negocios (id, nombre, color_marca, tipo_negocio) VALUES
  (2, 'Salón Bonita', '#3B4CCA', 'reservas');

INSERT INTO usuarios (id, negocio_id, nombre, whatsapp, password_hash, rol) VALUES
  (2, 2, 'Salón Bonita', '3005556677', '$2y$12$Rp/Uc3/Mg/f0A7wwOH/7w.OrB97It.nmx2n1sExSPVsAdf6Q/cQFW', 'dueno');

-- Un colaborador de ejemplo, con acceso solo a la sede 2 (Salón Bonita), para
-- probar el panel con rol 'colaborador': entra a Agenda/Servicios/Pedidos
-- pero no ve Copiloto, Empleados, Horario ni Colaboradores.
INSERT INTO usuarios (id, negocio_id, nombre, whatsapp, password_hash, rol) VALUES
  (3, 2, 'Recepción Salón Bonita', '3005550000', '$2y$12$Rp/Uc3/Mg/f0A7wwOH/7w.OrB97It.nmx2n1sExSPVsAdf6Q/cQFW', 'colaborador');

INSERT INTO sedes
  (id, negocio_id, slug, nombre, descripcion, whatsapp, inicial, llave_breb_tipo, llave_breb_valor,
   horario_atencion, intervalo_citas_min, publicada)
VALUES
  (2, 2, 'salonbonita', 'Salón Bonita', 'Peluquería y manicure · Bucaramanga',
   '3005556677', 'S', 'celular', '3005556677',
   '{"1":["09:00","18:00"],"2":["09:00","18:00"],"3":["09:00","18:00"],"4":["09:00","18:00"],"5":["09:00","18:00"],"6":["09:00","14:00"]}',
   30, 1);

INSERT INTO usuario_sedes (usuario_id, sede_id) VALUES (3, 2);

INSERT INTO servicios (id, sede_id, nombre, precio, duracion_min, color, orden) VALUES
  (1, 2, 'Corte de cabello', 20000, 30, '#5B7F3A', 1),
  (2, 2, 'Manicure',         18000, 45, '#3B4CCA', 2),
  (3, 2, 'Peinado',          35000, 60, '#E8452C', 3),
  (4, 2, 'Tinte y color',    70000, 90, '#F2B632', 4);

INSERT INTO clientes (id, negocio_id, nombre, telefono, autorizo_datos, autorizado_en) VALUES
  (6, 2, 'Sandra Molina',  '3101112233', 1, NOW()),
  (7, 2, 'Julián Peña',    '3109998877', 1, NOW()),
  (8, 2, 'Camila Rojas',   '3112223344', 1, NOW());

-- Sandra Molina: se cortaba el cabello cada ~20 días y dejó de venir hace 40 (se debe reactivar).
INSERT INTO citas (sede_id, cliente_id, servicio_id, nombre_servicio, precio, fecha_hora, duracion_min, estado) VALUES
  (2, 6, 1, 'Corte de cabello', 20000, NOW() - INTERVAL 80 DAY, 30, 'completada'),
  (2, 6, 1, 'Corte de cabello', 20000, NOW() - INTERVAL 60 DAY, 30, 'completada'),
  (2, 6, 1, 'Corte de cabello', 20000, NOW() - INTERVAL 40 DAY, 30, 'completada');

-- Julián Peña: viene cada 15 días y tiene una cita agendada para dentro de poco (no necesita reactivación).
INSERT INTO citas (sede_id, cliente_id, servicio_id, nombre_servicio, precio, fecha_hora, duracion_min, estado) VALUES
  (2, 7, 1, 'Corte de cabello', 20000, NOW() - INTERVAL 30 DAY, 30, 'completada'),
  (2, 7, 1, 'Corte de cabello', 20000, NOW() - INTERVAL 15 DAY, 30, 'completada'),
  (2, 7, 1, 'Corte de cabello', 20000, NOW() + INTERVAL 2 DAY, 30, 'confirmada');

-- Camila Rojas: apenas una cita (aún no hay suficiente historia).
INSERT INTO citas (sede_id, cliente_id, servicio_id, nombre_servicio, precio, fecha_hora, duracion_min, estado) VALUES
  (2, 8, 3, 'Peinado', 35000, NOW() - INTERVAL 5 DAY, 60, 'completada');
