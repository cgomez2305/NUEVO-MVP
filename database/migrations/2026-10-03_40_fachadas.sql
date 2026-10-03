-- Fachadas de la tienda pública según la línea de negocio. La de barrio
-- (toldo, carta) no le sirve a un consultorio ni a un despacho de abogados:
-- cada familia tiene su cabecera, su letra, su vocabulario y sus secciones.
--   barrio       comida, tiendas, salones (la de siempre)
--   consultorio  salud: odontología, fisioterapia, psicología…
--   despacho     servicios profesionales: abogados, contadores, arquitectos…
-- Solo aplica a negocios de reservas; los de pedidos siempre van de barrio.
ALTER TABLE negocios
  ADD COLUMN fachada ENUM('barrio','consultorio','despacho') NOT NULL DEFAULT 'barrio' AFTER rubro,
  -- Lo que respalda al profesional ("Registro ReTHUS 1234", "T.P. 123.456
  -- del C.S. de la J."). Lo escribe el dueño; Veci no lo verifica.
  ADD COLUMN credencial   VARCHAR(140) DEFAULT NULL AFTER fachada,
  -- Párrafo "Quiénes somos" de la portada.
  ADD COLUMN presentacion VARCHAR(600) DEFAULT NULL AFTER credencial;

-- Los consultorios que ya existen pasan a su fachada.
UPDATE negocios SET fachada = 'consultorio' WHERE rubro = 'salud';
