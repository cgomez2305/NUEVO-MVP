-- Migración para una base de datos EXISTENTE (instalaciones nuevas ya
-- traen esta columna en database/schema.sql).
--
-- Agrega:
--   sedes.direccion — dirección física en texto libre, opcional. Se
--                     muestra en la tienda pública (servicios.php,
--                     "Dónde estamos") y en el checkout (carrito.php,
--                     "Recoges en") con un enlace de búsqueda en Google
--                     Maps, y se edita desde panel/sede_editar.php.
--
-- NULL por defecto: no cambia nada en sedes ya existentes — simplemente
-- no se muestra la sección "Dónde estamos" hasta que el negocio la llene.
--
-- Aplicar UNA sola vez, con un backup reciente a mano:
--   mysql -u <usuario> -p <base_de_datos> < database/migrations/2026-10-01_sedes_direccion.sql

USE veci;

ALTER TABLE sedes
  ADD COLUMN direccion VARCHAR(200) DEFAULT NULL AFTER acepta_mesa;
