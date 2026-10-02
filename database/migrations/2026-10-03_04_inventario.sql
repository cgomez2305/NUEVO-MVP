-- "Agotado por hoy" que se quita solo al día siguiente (agotado_hasta) e
-- inventario opcional por producto (stock: NULL = no se controla). Cada
-- pedido descuenta unidades; al llegar a 0 el producto sale como agotado y
-- al cancelar el pedido las unidades vuelven.

ALTER TABLE productos
  ADD COLUMN agotado_hasta DATE DEFAULT NULL AFTER agotado,
  ADD COLUMN stock         INT  DEFAULT NULL AFTER agotado_hasta;
