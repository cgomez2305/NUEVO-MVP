-- Decisiones del dueño para tiendas (2026-10-03):
-- 1. Lo que va por peso se pide en línea por medias libras: el renglón del
--    pedido guarda los gramos pedidos (NULL = por unidad, o un pedido viejo
--    en kilos enteros: ahí cantidad = kilos). Ver Producto::GRAMOS_PASO_EN_LINEA.
-- 2. Se puede fiar a quien no tiene WhatsApp (el cuaderno real tiene a doña
--    Rosa sin celular): el teléfono del cliente pasa a ser opcional. La
--    llave única (negocio, teléfono) deja pasar varios NULL.
ALTER TABLE pedido_items
  ADD COLUMN gramos INT UNSIGNED DEFAULT NULL AFTER por_peso;

ALTER TABLE clientes
  MODIFY telefono VARCHAR(20) DEFAULT NULL;
