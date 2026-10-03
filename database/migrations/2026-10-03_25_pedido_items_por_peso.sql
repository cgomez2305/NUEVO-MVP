-- Tiendas (fase 4, revisión): cada renglón de un pedido recuerda si el
-- producto iba por peso cuando se pidió (1 = 1 kg = 1000 g de inventario).
-- Así cancelar el pedido devuelve el inventario en la unidad correcta
-- aunque después el producto pase de "por peso" a "por unidad" o al revés.
-- Los pedidos que ya existían toman lo que el producto dice hoy.

ALTER TABLE pedido_items
  ADD COLUMN por_peso TINYINT(1) NOT NULL DEFAULT 0 AFTER cantidad;

UPDATE pedido_items i JOIN productos p ON p.id = i.producto_id
   SET i.por_peso = 1
 WHERE p.vende_por = 'peso';
