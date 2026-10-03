-- "Volver a pedir" por celular, no por cliente (corrige la 33): cada
-- pedido o cita guarda el token del celular donde se hizo, y la tienda
-- solo propone repetir lo que se pidió desde ESE celular. Antes el token
-- era del cliente, y quien escribía el número de otra persona en el
-- checkout recibía su token y veía sus pedidos siguientes.
ALTER TABLE pedidos ADD COLUMN dispositivo CHAR(32) DEFAULT NULL, ADD INDEX idx_pedidos_dispositivo (dispositivo);
ALTER TABLE citas ADD COLUMN dispositivo CHAR(32) DEFAULT NULL, ADD INDEX idx_citas_dispositivo (dispositivo);
ALTER TABLE clientes DROP INDEX uniq_cliente_token_recompra, DROP COLUMN token_recompra;
