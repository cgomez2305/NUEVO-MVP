# Fachadas de la tienda pública

La Ruta 1 ("letrero de barrio") no le sirve a todo el mundo: un consultorio
o un despacho de abogados con toldo rayado pierde credibilidad. Por eso la
tienda pública tiene **fachadas**: mismos datos y mismo flujo, distinto
papel, tinta, letra, cabecera y vocabulario.

| Fachada | Para | Cabecera | Letra | Detalle propio |
|---|---|---|---|---|
| `barrio` | comida, tiendas, salones, talleres (y **todos** los de pedidos) | toldo + letrero (`_cabecera.php`) | Bricolage + Inter Tight | toldo rayado, carta con puntos guía |
| `consultorio` | salud: odontología, fisioterapia, psicología | membrete (`_membrete.php`) | Inter Tight en todo | membrete clínico: hoja blanca con banda del color arriba, bruma fría debajo; duración como etiqueta |
| `despacho` | abogados, contadores, arquitectos, consultores | membrete (`_membrete.php`) | Instrument Serif (nombre, títulos de sección, números) + Inter Tight | papel membreteado: doble filete, sello de marco doble, nombre centrado en serif, consultas numeradas 01, 02… |

## Cómo funciona

- Dato: `negocios.fachada` (migración 40), más `credencial` y `presentacion`.
  Se elige sola al registrarse (`salud` → consultorio, `profesional` →
  despacho) y el dueño la cambia en **Editar sede**. Solo agendas: un
  negocio de pedidos siempre es barrio (`fachada_tienda()` lo fuerza y el
  UPDATE también).
- `layouts/tienda.php` pone `pq-estilo-{fachada}` en el `<body>`. Esa clase
  redefine tokens (`--papel`, `--papel-hondo`, `--linea-tienda`,
  `--texto-calido`, `--texto-tenue`, `--tinta`, `--hoja`, `--fuente-titulo`,
  `--fuente-serif`), así TODAS las pantallas (reservar, reprogramar,
  confirmación…) cambian juntas sin tocar sus vistas.
- `--hoja` es el fondo de tarjetas e inputs de la tienda (antes `#FFFDF8`
  suelto): usa `var(--hoja, #FFFDF8)` en reglas nuevas.
- Vocabulario: `textos_fachada($fachada)` — turno / cita / consulta,
  "Nuestro equipo" / "Profesionales" / "Quiénes te atienden", etc. Nunca
  escribas "turno" fijo en una vista de agenda.
- Iniciales de personas: `inicial_persona()` salta "Dr.", "Dra.", "Abg."…
- La credencial la escribe el dueño y Veci **no la verifica**: va con ícono
  de documento, nunca con un check de "verificado".
- `presentacion` ("Quiénes somos") se imprime con `parrafos_texto()`: en el
  despacho va antes de la agenda; en las demás, después.
- La vista previa del onboarding (`pago.php`) y "¡Ya abriste!"
  (`publicada.php`) muestran la fachada real (filete y sello en vez de toldo).

## Agregar una fachada nueva

1. Valor en el ENUM (migración) + `FACHADAS_TIENDA` + `textos_fachada()`.
2. Bloque `.pq-estilo-{nueva}` con sus tokens y su detalle propio en
   `app.css` (sección "FACHADAS DE LA TIENDA").
3. Opción con miniatura en `panel/sede_editar.php` (`.pq-fachada-muestra-{nueva}`).
4. Ampliar `tests/fachadas.php`.

Pendientes del plan original: **Estudio** (belleza y bienestar, entrenador
personal), **Oficio** (técnicos a domicilio) y **Marca** (emprendimientos
con catálogo de fotos).
