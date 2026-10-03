-- Lo que cada pedido o venta de mostrador descontó del inventario, por
-- producto (JSON {producto_id: unidades o gramos}). Al cancelar o anular se
-- devuelve exactamente eso, aunque después cambie la receta del combo o se
-- borre una de sus partes. NULL = pedido/venta anterior a esto (se calcula
-- como antes).
ALTER TABLE pedidos ADD COLUMN inventario_movido TEXT DEFAULT NULL;
ALTER TABLE ventas  ADD COLUMN inventario_movido TEXT DEFAULT NULL;
