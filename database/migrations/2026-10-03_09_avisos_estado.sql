-- Avisos de estado al cliente por WhatsApp: el último estado del que ya se
-- le avisó (por la API automática o a mano con el botón). Si el estado
-- actual es otro, el panel ofrece "Avisarle".

ALTER TABLE pedidos ADD COLUMN aviso_estado VARCHAR(20) DEFAULT NULL;
ALTER TABLE citas ADD COLUMN aviso_estado VARCHAR(20) DEFAULT NULL;

-- Lo que ya existía se da por avisado: si no, el panel pediría "Avisarle"
-- de pedidos y citas viejos que el cliente ya conoce.
UPDATE pedidos SET aviso_estado = estado;
UPDATE citas SET aviso_estado = estado;
