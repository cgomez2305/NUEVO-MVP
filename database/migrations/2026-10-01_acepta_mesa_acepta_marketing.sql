-- Migración para una base de datos EXISTENTE (instalaciones nuevas ya
-- traen estas columnas en database/schema.sql, desde el checkout público
-- rediseñado el 2026-10-01: ver carrito.php, TiendaController::crearPedido,
-- Sede::actualizar y Cliente::buscarOCrear).
--
-- Agrega:
--   sedes.acepta_mesa      — si el checkout ofrece "Comer aquí" como forma
--                            de entrega (toggle en panel/Editar sede).
--   clientes.acepta_marketing — opt-in de promociones, SEPARADO del
--                            consentimiento de procesar el pedido
--                            (clientes.autorizo_datos).
--
-- Ambas quedan con un DEFAULT que no cambia el comportamiento de filas ya
-- existentes (acepta_mesa=1 mantiene "Comer aquí" visible como antes;
-- acepta_marketing=0 no suscribe a nadie a nada sin que lo haya marcado).
--
-- Aplicar UNA sola vez, con un backup reciente a mano:
--   mysql -u <usuario> -p <base_de_datos> < database/migrations/2026-10-01_acepta_mesa_acepta_marketing.sql

USE veci;

ALTER TABLE sedes
  ADD COLUMN acepta_mesa TINYINT(1) NOT NULL DEFAULT 1 AFTER intervalo_citas_min;

ALTER TABLE clientes
  ADD COLUMN acepta_marketing TINYINT(1) NOT NULL DEFAULT 0 AFTER autorizado_en;
