-- Cambiar o restablecer la contraseña cierra las demás sesiones abiertas:
-- cada sesión recuerda la versión con la que entró y, si ya no coincide
-- (ver Auth::usuarioActual), se cierra.
ALTER TABLE usuarios
  ADD COLUMN sesion_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER reset_token_expira;
