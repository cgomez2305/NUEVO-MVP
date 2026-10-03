-- Referidos entre negocios: cada negocio tiene un código para invitar a
-- otros (tuveci.co/registro?ref=CODIGO). Cuando el invitado paga su primer
-- plan, al que invitó se le regalan 30 días (una sola vez por invitado).

ALTER TABLE negocios ADD COLUMN codigo_referido VARCHAR(16) DEFAULT NULL;
ALTER TABLE negocios ADD UNIQUE KEY uniq_negocios_codigo_referido (codigo_referido);

CREATE TABLE IF NOT EXISTS referidos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referidor_id  INT UNSIGNED NOT NULL,
  referido_id   INT UNSIGNED NOT NULL,
  dias_premio   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  premiado_en   DATETIME     DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_referido (referido_id),
  FOREIGN KEY (referidor_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (referido_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
