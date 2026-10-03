---
name: compactar
description: Guarda todo el contexto de trabajo de la sesión (decisiones del usuario, estado del código, pendientes, credenciales de prueba, convenciones) en .claude/contexto/ESTADO.md, lo sube al repo y deja listo el comando /compact con instrucciones de foco, para compactar la conversación sin perder nada. Úsala cuando el usuario diga "compacta", "/compactar", "guarda el contexto", o cuando la conversación ya sea muy larga y vaya a compactarse.
---

# Compactar sin perder contexto

La compactación automática resume la conversación y en ese resumen se
pierden detalles: decisiones exactas del usuario, por qué se descartó algo,
comandos que ya funcionaron, datos de prueba, el siguiente paso preciso.
Esta skill los pone **en un archivo del repo antes** de compactar, para que
la sesión que sigue (o una sesión nueva, en otro contenedor) retome sin
preguntar de nuevo.

## Paso 1 — Reunir el estado real (no de memoria)

Corre en paralelo y usa la salida, no lo que recuerdas:

```bash
git branch --show-current
git status --short
git log --oneline -15
git log origin/$(git branch --show-current)..HEAD --oneline   # commits sin subir
```

Revisa también la lista de tareas (TaskList) si existe, y los archivos de
`database/migrations/` más recientes.

## Paso 2 — Escribir `.claude/contexto/ESTADO.md`

Si el archivo ya existe, léelo primero y **actualízalo** (no lo borres a
ciegas: lo que siga vigente se queda; lo resuelto pasa a "Hecho"). Usa
exactamente estas secciones, en español, concretas y verificables:

```markdown
# Estado de la sesión — <fecha AAAA-MM-DD>

## Objetivo actual
Qué pidió el usuario, con sus palabras textuales entre comillas para los
pedidos más recientes (los matices de redacción importan).

## Decisiones del usuario (no volver a preguntar)
- Decisión → valor elegido → fecha/contexto. Ej.: "Cobro de planes →
  Híbrido (manual Bre-B ahora, pasarela después)".

## Hecho (verificado)
- Qué se implementó, en qué commit (hash corto), y cómo se verificó
  (prueba en vivo, lint, captura). Solo lo que de verdad se probó.

## En curso
- La tarea exacta a medio hacer: archivo, función, línea aproximada, y qué
  falta. Si hay cambios sin commit, decirlo.

## Pendiente (en orden)
1. Siguiente paso concreto y accionable.
2. ...

## Archivos clave tocados
- `ruta/archivo.php` — qué rol cumple y qué se cambió.

## Convenciones del proyecto
- Rama de trabajo, formato de commits y líneas de atribución exigidas,
  convenciones de migraciones, helpers que hay que reutilizar, estilo de
  comentarios, lo que el usuario pidió no hacer (ej. no abrir PR sin pedirlo).

## Entorno y pruebas
- Cómo levantar todo (servicios, servidor local, rutas de navegador
  headless) y datos de prueba (usuarios demo, admin local).
- NO escribir secretos reales (API keys, contraseñas de producción, tokens):
  solo credenciales de demo/local que ya están en el repo, o decir "está en
  config/config.php".

## Errores ya resueltos (no repetir)
- Síntoma → causa → arreglo. Ej.: "curl exit 7 → el servidor PHP se cayó
  tras reiniciar el contenedor → relanzar con nohup php -S ...".
```

Reglas para el contenido:
- **Específico > completo.** Rutas, nombres de función, hashes de commit,
  valores numéricos (límites, precios, minutos). Nada de "se mejoró la
  seguridad"; sí "login: 20 fallos por IP / 15 min (LimiteTasa)".
- **Textual cuando importa.** Los pedidos del usuario más recientes van
  citados tal cual.
- **Sin secretos.** Revisa el archivo antes de guardarlo.
- Máximo ~250 líneas: si crece más, condensa lo "Hecho" antiguo a una línea
  por bloque de trabajo con su commit.

## Paso 3 — Guardarlo fuera del contenedor

El contenedor es efímero: lo que no se sube se pierde.

```bash
git add .claude/contexto/ESTADO.md
git commit -m "Contexto de sesión: <resumen en una línea>"   # + líneas de atribución del proyecto
git push -u origin <rama-de-trabajo>
```

Si hay cambios de código sin commit que estén a medias, **no** los
mezcles en este commit: anótalos en "En curso" y pregunta al usuario si
quiere un commit WIP aparte.

## Paso 4 — Compactar con foco

`/compact` es un comando del CLI: la skill no puede ejecutarlo sola. Dale
al usuario la línea lista para pegar, por ejemplo:

```
/compact Conserva: objetivo actual y pedidos textuales recientes del usuario, decisiones tomadas, siguiente paso exacto, archivos en curso. El detalle completo está en .claude/contexto/ESTADO.md — léelo al retomar.
```

Y explícale en una frase que, si la compactación ocurre sola (automática),
el archivo igual quedó guardado.

## Al retomar (después de compactar o en una sesión nueva)

1. Lee `.claude/contexto/ESTADO.md` completo antes de hacer nada.
2. Contrasta con `git log` / `git status` (el archivo puede ir un paso
   atrás del código).
3. Sigue con "En curso" / el primer "Pendiente" sin volver a preguntar las
   decisiones ya registradas.
