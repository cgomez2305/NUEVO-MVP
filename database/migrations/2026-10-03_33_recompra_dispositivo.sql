-- "Volver a pedir": la tienda reconoce al cliente en el mismo celular
-- donde ya pidió (cookie con este token), nunca por el número que alguien
-- escriba: así nadie ve el historial de otra persona tecleando su teléfono.
ALTER TABLE clientes
  ADD COLUMN token_recompra CHAR(32) DEFAULT NULL AFTER token_preferencias,
  ADD UNIQUE KEY uniq_cliente_token_recompra (token_recompra);
