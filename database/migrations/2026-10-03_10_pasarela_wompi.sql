-- Pago de planes con Wompi: la transacción que pagó cada plan. Única para
-- que el webhook y el regreso del cliente no confirmen dos veces lo mismo.

ALTER TABLE pagos_plan ADD COLUMN transaccion_pasarela VARCHAR(64) DEFAULT NULL;
ALTER TABLE pagos_plan ADD UNIQUE KEY uniq_pago_transaccion (transaccion_pasarela);
