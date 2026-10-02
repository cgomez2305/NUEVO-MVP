# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Dueños de negocios de barrio en Colombia que hoy venden o agendan por WhatsApp de forma manual. Cuatro líneas de negocio con el mismo peso, ninguna prioritaria sobre las demás: tiendas/minimarkets y restaurantes (modo pedidos), peluquerías y barberías, entrenadores personales, y consultorios odontológicos (modo reservas). Su situación actual: anotan pedidos o citas a mano por chat, confirman horarios uno por uno, pierden ventas por cruces de agenda o pedidos mal anotados, y no tienen forma sistemática de saber qué cliente dejó de comprar o agendar para reactivarlo.

## Product Purpose

Veci le da a ese negocio una tienda o agenda pública propia por WhatsApp, sin pagar comisión por venta, para vender/agendar de forma ordenada y recuperar clientes que dejaron de volver. Éxito = el negocio publica su catálogo o agenda en minutos (no días), deja de perder pedidos/citas por desorganización, y usa el copiloto de recompra para reactivar clientes inactivos.

## Positioning

0% de comisión siempre — plan fijo mensual, nunca un porcentaje de las ventas — frente a Rappi/DiDi (25–30% por pedido). Onboarding con foto + IA real (API de Claude/Anthropic) que arma el catálogo o los servicios desde una foto, con revisión humana antes de publicar. Un mismo producto cubre pedidos y reservas (dos modos), con multisede y colaboradores ya incluidos sin costo adicional.

El espacio "vender por WhatsApp sin comisión" ya tiene jugadores establecidos en Colombia (Comerciaya, Whataform, ReservaSimple en reservas). La diferenciación defendible es la ejecución — IA de onboarding real, copiloto de recompra, ambos modos en un mismo producto — no la exclusividad de la categoría. Evitar el mensaje "nadie más hace esto".

## Operating Context

El dueño opera principalmente desde el celular, en modo "atendiendo clientes" (4G, gama media), no desde escritorio. Flujo real: foto del menú o servicios → la IA arma el catálogo → el dueño revisa y publica → el cliente final pide o agenda por el enlace público → el pedido/cita llega al WhatsApp del dueño → cobro por Bre-B (llave, menos de 20 segundos, sin comisión), Nequi (transferencia + comprobante) o efectivo contraentrega → cada mañana el copiloto revisa quién dejó de comprar o agendar y sugiere el mensaje de reactivación. El panel del dueño es la otra mitad del mismo producto (se construye en otra sesión/rama de este mismo repositorio).

## Capabilities and Constraints

Confirmado y funcionando hoy en el producto real (verificado contra el código del backend, no solo contra lo que dice la web):
- Modo pedidos (catálogo + carrito) y modo reservas (agenda + empleados + duración + anticipo) — un negocio usa uno u otro, nunca ambos a la vez; esto es un límite de diseño, no una función faltante.
- Onboarding con foto real vía API de Claude, con catálogo de ejemplo como respaldo si no hay credenciales configuradas.
- Cobro por Bre-B (webhook funcional; falta una cuenta de participante/PSP real para automatizarlo del todo — hoy el flujo es llave + confirmación), Nequi (transferencia manual + comprobante), efectivo contraentrega.
- Copiloto de recompra: segmentación real de clientes (inactivo/vip/nuevo/recurrente) con mensaje sugerido listo para WhatsApp.
- Multisede y colaboradores con acceso por sede — ya construido y disponible para cualquier negocio, sin costo adicional ni límite.
- Selección del profesional/empleado al agendar, cuando el negocio tiene más de uno.
- Notificaciones push al dueño (pedido o cita nueva, aunque el panel esté cerrado).

Restricción abierta e importante: no existe todavía sistema de planes, suscripción ni cobro recurrente automático en el backend. Los precios publicados ($0 / $59.000 / $129.000 COP) son el modelo comercial declarado, pero su aplicación técnica (límites de uso, cobro mensual) sigue pendiente de definir con el equipo de backend. No prometer en el sitio ninguna función de plan que no esté realmente aplicada en el código.

Sin sistema de referidos construido todavía — `referidos.html` ya está marcada como "Próximamente", no como función activa.

## Brand Commitments

Nombre: Veci. Identidad visual ya compartida con la app del producto (mismo panel/tienda, no una marca distinta para marketing): paleta de color (`--sello` #3B4CCA, `--carbon`, `--tiquete`, `--caja`) y tipografía (Bricolage Grotesque + Inter Tight) idénticas entre la web de marketing y la app — mantener esa unificación, no reintroducir fuentes o colores propios de marketing. Tono honesto y directo, sin lenguaje de "producto en construcción"; una función no construida se marca explícitamente "Próximamente", nunca como disponible.

## Evidence on Hand

Ninguna prueba social real todavía: sin testimonios, sin clientes piloto, sin cifras de uso reales (confirmado con el usuario). El sitio no debe insinuar prueba social — cifras de negocios activos, citas con nombre y negocio, capturas de clientes reales — hasta que exista de verdad.

## Product Principles

1. Honestidad verificable: toda función que se promete en la web existe de verdad en el código. Ya se corrigieron varias promesas falsas (Daviplata como método de pago, "pedidos y reservas en la misma cuenta", multisede marcado como futuro cuando ya está construido) — no repetir el patrón.
2. 0% de comisión, siempre: el precio fijo es la posición central frente a Rappi/DiDi/Bold, nunca un porcentaje de las ventas.
3. Hecho para el barrio colombiano, no una plantilla genérica adaptada: Bre-B, pesos colombianos, Ley 1581 de datos personales, WhatsApp como canal nativo desde el primer commit.
4. El dueño opera desde el celular: todo se diseña primero para gama media y 4G, la versión de escritorio es la adaptación.
5. Las cuatro líneas de negocio pesan igual: ninguna (tiendas, peluquerías, entrenadores, odontólogos) es el foco por encima de las demás.
