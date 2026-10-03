---
target: componentes cards + hero mockup del sitio Veci
total_score: 17
max_score: 24
na_heuristics: 5,7,9
p0_count: 0
p1_count: 2
target_identity: "file:/home/user/NUEVO-MVP/docs/index.html"
target_fingerprint: "sha256:764a35fb4f0bbdb403543fb1d130b03aa7d9b8a0cf2c678ddec909a7a0595b6a"
target_path: /home/user/NUEVO-MVP/docs/index.html
timestamp: 2026-10-03T07-15-11Z
slug: docs-index-html
---
# Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Buena: timestamps, doble check, puntos de progreso, chips "en vivo" |
| 2 | Match System/Real World | 3 | Fuerte anclaje local (Bre-B, pesos, "Doña María") pero los íconos genéricos rompen esa coherencia |
| 3 | User Control and Freedom | 3 | Toggles reales (cliente/admin, mensual/anual, tabs de industria) |
| 4 | Consistency and Standards | 2 | Shell de card consistente; iconografía inconsistente y a veces incorrecta |
| 5 | Error Prevention | n/a | Sin acciones destructivas en una superficie de marketing |
| 6 | Recognition over Recall | 3 | Badges claros, chat de WhatsApp reconocible |
| 7 | Flexibility and Efficiency | n/a | No aplica a una superficie Persuade |
| 8 | Aesthetic/Minimalist Design | 3 | Buena restricción de paleta; pierde un punto al notar la repetición de íconos |
| 9 | Error Recovery | n/a | No aplica |
| 10 | Help and Documentation | 3 | FAQ + blog con temas específicos de Colombia |
| **Total** | | **17/24** | **71% — Good** |

# Design Specificity Verdict

Estructuralmente propio, semánticamente genérico. El shell de card (radio 20px, sombra que crece en hover, spotlight que sigue el cursor) es realmente de Veci. Pero el set de íconos dentro se repite sin criterio: "escudo" 13 veces en conceptos sin relación, "calendario" 19 veces, mal asignado en integraciones.html a "Pasarela de tarjeta" y "Panel de estadísticas" (ninguno es un calendario).

Escaneo determinístico: 253 hallazgos en 6 páginas (157 warning, 96 advisory). Los más repetidos: design-system-color (61), undersized-ui-text (32, demo.html, texto 9.5-10.5px bajo el piso de 11px), pulsing-dot (30, 16 falsos positivos confirmados por cruce con el DOM real), nested-cards (21, demo.html), low-contrast (19, el peor: blanco sobre mostaza, 1.8:1).

# Problemas prioritarios

[P1] Sistema de íconos genérico e incorrecto en varios casos — pendiente, no tocado en esta ronda.
[P1] Burbuja de chat sin distinguir remitente — CORREGIDO.
[P2] Cromo de celular incompleto (sin status bar/íconos header/home indicator) — CORREGIDO.
[P3] Chips flotantes desaparecían en mobile — CORREGIDO.
[P3] Spotlight de hover no conectado a todas las familias de card — pendiente.

# Hero: cambios de presencia aplicados

Bisel con degradado + brillo + sombra en dos capas; balanceo sutil continuo (apagado con prefers-reduced-motion); burbujas enviado/recibido correctas; status bar + íconos de cabecera + home indicator.
