-- Cancelar o rechazar una solicitud de plan ya no la borra: queda con
-- cancelado_en. Si Wompi aprueba tarde un pago que el dueño canceló (ya
-- había pagado en otra pestaña), el webhook todavía lo encuentra y el
-- plan se activa: antes la plata llegaba y no quedaba rastro de nada.
ALTER TABLE pagos_plan ADD COLUMN cancelado_en DATETIME DEFAULT NULL AFTER confirmado_en;
