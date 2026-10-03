-- Consentimiento comercial con evidencia (Ley 1581 de 2012).
--
-- clientes.acepta_marketing sigue siendo el estado vigente (lo que mira el
-- copiloto); consentimientos guarda cada cambio con su origen, la versión
-- de la política aceptada y la IP cuando lo hizo el propio cliente. Es
-- solo de agregar: nunca se edita ni se borra una fila (salvo que se
-- eliminen los datos del cliente, por su derecho de supresión).
--
-- token_preferencias: enlace sin login para que el cliente active o
-- retire las promociones de ese negocio cuando quiera.
-- permiso_pedido_en: el negocio puede pedirle permiso UNA vez a quien no
-- lo ha dado (no se le insiste).

ALTER TABLE clientes
  ADD COLUMN marketing_actualizado_en DATETIME DEFAULT NULL AFTER acepta_marketing,
  ADD COLUMN token_preferencias CHAR(32) DEFAULT NULL AFTER marketing_actualizado_en,
  ADD COLUMN permiso_pedido_en DATETIME DEFAULT NULL AFTER token_preferencias,
  ADD UNIQUE KEY uniq_cliente_token_preferencias (token_preferencias);

CREATE TABLE IF NOT EXISTS consentimientos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id       INT UNSIGNED NOT NULL,
  cliente_id       INT UNSIGNED NOT NULL,
  finalidad        ENUM('datos', 'marketing') NOT NULL,
  otorgado         TINYINT(1)   NOT NULL,
  -- pedido | reserva | enlace (el cliente en su página de preferencias) |
  -- panel (lo anotó el negocio) | anterior (estado previo a esta tabla)
  origen           VARCHAR(20)  NOT NULL,
  politica_version VARCHAR(20)  NOT NULL,
  usuario_id       INT UNSIGNED DEFAULT NULL,
  ip               VARCHAR(45)  DEFAULT NULL,
  creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  INDEX idx_consentimientos_cliente (cliente_id, finalidad, id)
) ENGINE=InnoDB;

-- Lo que ya había queda como punto de partida, marcado como "anterior".
INSERT INTO consentimientos (negocio_id, cliente_id, finalidad, otorgado, origen, politica_version, creado_en)
SELECT negocio_id, id, 'datos', 1, 'anterior', '2026-10-01', COALESCE(autorizado_en, creado_en)
FROM clientes WHERE autorizo_datos = 1;

INSERT INTO consentimientos (negocio_id, cliente_id, finalidad, otorgado, origen, politica_version, creado_en)
SELECT negocio_id, id, 'marketing', 1, 'anterior', '2026-10-01', creado_en
FROM clientes WHERE acepta_marketing = 1;

UPDATE clientes SET marketing_actualizado_en = creado_en WHERE acepta_marketing = 1;
