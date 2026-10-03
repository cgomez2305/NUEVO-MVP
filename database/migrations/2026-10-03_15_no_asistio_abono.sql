-- "No vino" con la regla "se abona": qué cupón se le dio al cliente por su
-- anticipo y de qué bono se le devolvió la sesión. Sirve para mostrarle el
-- código y para deshacerlo todo si el negocio marcó "No vino" por error.
ALTER TABLE citas
  ADD COLUMN cupon_abono_id INT UNSIGNED DEFAULT NULL AFTER ajuste_estado,
  ADD COLUMN bono_devuelto_id INT UNSIGNED DEFAULT NULL AFTER cupon_abono_id;
