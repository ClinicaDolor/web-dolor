# Matriz de decisión M03 — preguntas fuera de Tabaquismo

Fecha: 2026-10-12  
Fuente: catálogo M03 del fixture sintético, `AntecedentesNoPatologicosModel` y
la vista tradicional. Este documento no modifica catálogos ni respuestas.

## Reglas verificadas

- Hay 25 preguntas oficiales distribuidas en siete temas; el piloto aprobado
  cubre únicamente Tabaquismo, preguntas 1–5.
- Las preguntas de tipo `select` aceptan `Si` o `No`.
- En el formulario tradicional, una respuesta `No` en la pregunta inicial de
  un tema limpia las respuestas hermanas de ese tema. La entrevista debe pedir
  confirmación explícita antes de reutilizar esa operación.
- Las preguntas posteriores a una respuesta negativa se muestran como no
  aplicables en la navegación tradicional; no deben guardarse como `No`.
- La persistencia autorizada es `pac_respuestas_paciente_modulo_3` mediante el
  modelo existente, con sesión de paciente, CSRF y control de concurrencia.

## Matriz concreta

| ID | Tema / texto oficial | Tipo | Dependencia verificable | Persistencia tradicional | Estado conversacional |
|---:|---|---|---|---|---|
| 6 | Alcoholismo — ¿Usted consume alcohol? | select | Controla 8–11 | Misma respuesta M03; `No` limpia hermanas | Requiere aprobación de confirmación destructiva |
| 8 | Alcoholismo — ¿Con qué frecuencia? | input | Visible si 6=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 9 | Alcoholismo — ¿Durante cuánto tiempo ha consumido alcohol? | input | Visible si 6=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 10 | Alcoholismo — ¿Qué tipo de bebida alcohólica consume con mayor frecuencia? | input | Visible si 6=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 11 | Alcoholismo — Si ya dejó de consumir alcohol, ¿Hace cuánto tiempo lo suspendió? | input | Condicional semántica del texto; no codificada como regla explícita | Campo `respuesta` de la pregunta | Requiere decisión sobre cuándo mostrarla |
| 12 | Ejercicio — ¿Practica algún ejercicio? | select | Controla 13–15 | Misma respuesta M03; `No` limpia hermanas | Requiere aprobación de confirmación destructiva |
| 13 | Ejercicio — ¿Con qué frecuencia? | input | Visible si 12=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 14 | Ejercicio — ¿Hace cuánto tiempo comenzó a ejercitarse regularmente? | input | Visible si 12=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 15 | Ejercicio — ¿Qué tipo de ejercicio practica principalmente? | input | Visible si 12=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 16 | Estrés — ¿Considera que actualmente experimenta estrés? | select | Controla 17 | Misma respuesta M03; `No` limpia hermanas | Requiere aprobación de confirmación destructiva |
| 17 | Estrés — Si experimenta estrés, ¿Cómo describiría su nivel de estrés: leve, moderado o severo? | input | Visible si 16=`Si`; valores no restringidos por modelo | Campo `respuesta` de la pregunta | Requiere catálogo/validación clínica de valores |
| 18 | Drogas — ¿Usted consume algún tipo de droga? | select | Controla 19–20 | Misma respuesta M03; `No` limpia hermanas | Requiere aprobación de confirmación destructiva |
| 19 | Drogas — ¿Con qué frecuencia? | input | Visible si 18=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 20 | Drogas — ¿Qué tipo de droga consume con mayor frecuencia? | input | Visible si 18=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 21 | Dormir — ¿Usted consume algún tipo de medicación para poder dormir? | select | Controla 23, 24 y 26 | Misma respuesta M03; `No` limpia hermanas | Requiere aprobación de confirmación destructiva |
| 23 | Dormir — ¿Con qué frecuencia? | input | Visible si 21=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 24 | Dormir — ¿Cuál es el nombre del medicamento que utiliza? | input | Visible si 21=`Si` | Campo `respuesta` de la pregunta | Reutilizable tras aprobar dependencia |
| 26 | Dormir — ¿Cuánta dosis utiliza de ese medicamento? | input | Visible si 21=`Si` | Campo `respuesta` de la pregunta | Requiere definición de unidad de dosis |
| 27 | Grupo sanguíneo — ¿Cuál es su grupo sanguíneo? | input | Sin dependencia en el código revisado | Campo `respuesta` de la pregunta | Requiere catálogo de valores o aprobación de texto libre |
| 28 | Grupo sanguíneo — ¿Cuál es su factor RH? | input | Sin dependencia en el código revisado | Campo `respuesta` de la pregunta | Requiere catálogo de valores o aprobación de texto libre |

## Decisiones puntuales requeridas

1. Confirmar que las respuestas `No` de Alcoholismo, Ejercicio, Estrés,
   Drogas y Dormir pueden limpiar respuestas dependientes después de una segunda
   confirmación conversacional.
2. Definir si la pregunta 11 se muestra solo cuando el paciente declara que
   dejó de beber, o si se mantiene como campo opcional al responder `Si`.
3. Aprobar valores `leve`, `moderado` y `severo` para la pregunta 17.
4. Definir unidad y formato de dosis para la pregunta 26.
5. Aprobar catálogo cerrado para grupo sanguíneo y factor RH, o autorizar
   texto libre con validación adicional.

Hasta recibir estas decisiones no se implementan las 20 capturas ni se cambia
el significado de las preguntas originales.
