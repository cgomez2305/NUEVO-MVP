-- Demo de visitas a domicilio: "Frío Express", técnicos de aires
-- acondicionados en Bucaramanga. Se puede correr sobre una base que ya
-- existe (no usa ids fijos y no hace nada si la tienda ya está):
--   mysql veci < database/demo_visitas.sql
-- Acceso: WhatsApp 3007778899 · contraseña veci123 · tienda /t/frioexpress

SET NAMES utf8mb4;
SET @ya := (SELECT COUNT(*) FROM sedes WHERE slug = 'frioexpress');

INSERT INTO negocios (nombre, color_marca, tipo_negocio, modalidad)
SELECT 'Frío Express', '#1F9AA6', 'reservas', 'domicilio' FROM DUAL WHERE @ya = 0;
SET @n := IF(@ya = 0, LAST_INSERT_ID(), NULL);

INSERT INTO usuarios (negocio_id, nombre, whatsapp, password_hash, rol)
SELECT @n, 'Frío Express', '3007778899', '$2y$12$Rp/Uc3/Mg/f0A7wwOH/7w.OrB97It.nmx2n1sExSPVsAdf6Q/cQFW', 'dueno' FROM DUAL WHERE @n IS NOT NULL;

INSERT INTO sedes
  (negocio_id, slug, nombre, descripcion, whatsapp, inicial, llave_breb_tipo, llave_breb_valor,
   horario_atencion, intervalo_citas_min, colchon_min, publicada)
SELECT @n, 'frioexpress', 'Frío Express', 'Mantenimiento e instalación de aires · Bucaramanga y área metropolitana',
   '3007778899', 'F', 'celular', '3007778899',
   '{"1":[["08:00","12:00"],["14:00","18:00"]],"2":[["08:00","12:00"],["14:00","18:00"]],"3":[["08:00","12:00"],["14:00","18:00"]],"4":[["08:00","12:00"],["14:00","18:00"]],"5":[["08:00","12:00"],["14:00","18:00"]],"6":[["08:00","13:00"]]}',
   30, 30, 1
FROM DUAL WHERE @n IS NOT NULL;
SET @s := IF(@n IS NOT NULL, LAST_INSERT_ID(), NULL);

INSERT INTO servicios (sede_id, nombre, precio, precio_tipo, precio_max, duracion_min, color, orden, repetir_cada_meses)
SELECT @s, 'Mantenimiento de aire', 120000, 'fijo', NULL, 90, '#1F9AA6', 1, 6 FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Revisión y diagnóstico', 50000, 'fijo', NULL, 60, '#3B4CCA', 2, NULL FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Instalación de aire', 250000, 'desde', NULL, 180, '#F28C28', 3, NULL FROM DUAL WHERE @s IS NOT NULL;

INSERT INTO empleados (sede_id, nombre, especialidad)
SELECT @s, 'Andrés Rueda', 'Aires split e inverter' FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Mauricio León', 'Instalaciones y cableado' FROM DUAL WHERE @s IS NOT NULL;

INSERT INTO zonas_domicilio (sede_id, nombre, costo)
SELECT @s, 'Bucaramanga', 0 FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Floridablanca', 8000 FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Girón', 12000 FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Piedecuesta', 15000 FROM DUAL WHERE @s IS NOT NULL;
