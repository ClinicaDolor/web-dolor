# PHASE-05B — Regresión funcional integral P01–P11

**Proyecto:** Clínica del Dolor  
**Fecha de ejecución:** 2026-10-12  
**Base de pruebas:** `clinica_dolor_demo_20261010_final`  
**Resultado global:** `PARTIAL`  
**Archivos de aplicación modificados durante esta fase:** ninguno  
**Producción:** no

**Nota de consolidación posterior (2026-10-12):** este informe conserva los
resultados originales de PHASE-05B. La corrección posterior de finalización
idempotente y sus pruebas HTTP, la corrección del arnés cruzado de P05, el
contrato actualizado de P10 y la comprobación de archivos privados bajo
Apache/XAMPP están consolidados en
`docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md`.

## 1. Alcance y precondiciones

La ejecución se realizó exclusivamente por HTTP/AJAX y consultas de verificación sobre la base sintética allowlisted. No se repitió la automatización de Chrome, Edge ni IAB, conforme a la compuerta PHASE-05B. No se usaron pacientes reales ni se escribieron datos en `bd_clinica`.

La preparación confirmó:

- pacientes sintéticos `9301` y `9302` en clínicas distintas `9101` y `9102`;
- nueve módulos clínicos presentes;
- P05: 7 temas / 25 preguntas;
- P07: 2 temas / 2 preguntas / 2 enfermedades;
- P09: 6 clases / 46 preguntas;
- P10: 10 tratamientos de catálogo;
- P11: fila de evaluación sintética y cero finalizaciones iniciales del módulo 9.

Los runners sintéticos restauraron sus respuestas, finalizaciones y filas temporales. Se conservó el estado fixture preexistente de P04: una finalización sintética de `(id_modulo=2, id_paciente=9301)`. La verificación final no mostró filas temporales de P04–P11 fuera de ese estado base.

## 2. Matriz funcional

| Módulo | GET propio | Sin sesión | Acceso cruzado | AJAX / CRUD | Persistencia | Finalización / `next_url` | Estado |
|---|---|---|---|---|---|---|---|
| P01 Login | PASS, HTTP 200 | PASS, ruta de acceso pública | NO APLICA | Login sintético PASS | NO APLICA | NO APLICA | PASS |
| P02 Dashboard | PASS, HTTP 200 | PASS, HTTP 302 | NO APLICA por no recibir paciente en URL | Navegación básica PASS | No escritura no medida de forma independiente | NO APLICA | PASS parcial |
| P03 Ficha | PASS, HTTP 200 | PASS, HTTP 302 | PASS, HTTP 403 | Navegación PASS | GET sin cambio de fila PASS | NO APLICA | PASS |
| P04 Antecedentes familiares | PASS | PASS | PASS en GET/AJAX/POST | CRUD y autorización PASS | PASS | FAIL: repetición de finalización creó otra fila | PARTIAL |
| P05 Antecedentes no patológicos | PASS | PASS | PASS | POST propio, stale write y cruce PASS | PASS y limpieza PASS | FAIL: segunda finalización dejó 2 filas | PARTIAL |
| P06 Antecedentes quirúrgicos | PASS | PASS | PASS | CRUD y cruce PASS | PASS | PASS, `next_url`, idempotencia y concurrencia real | PASS |
| P07 Antecedentes patológicos V1/V2 | PASS | PASS | PASS | V1/V2 y cruce PASS | PASS y limpieza PASS | FAIL: segunda finalización dejó 2 filas | PARTIAL |
| P08 Medicación actual | PASS | PASS | PASS | AJAX/insert/update/delete PASS | PASS | PASS, `next_url` e idempotencia | PASS |
| P09 Medicación para el dolor | PASS | PASS | PASS | 6 clases, dependencias, comentarios y cruce PASS | PASS | PASS, `next_url` e idempotencia | PASS |
| P10 Procedimientos para el dolor | PASS | PASS | PASS | Catálogo 10/10, CRUD, dependencias y cruce PASS | PASS | PASS, `next_url` e idempotencia | PARTIAL |
| P11 Evaluación del dolor | PASS | PASS | PASS | mapas, cuestionario, cleanup y cruce PASS | PASS | PASS, `next_url` e idempotencia | PARTIAL |

### P01–P03 smoke HTTP

Se verificó en la sesión sintética:

- `/historia-clinica/acceso`: HTTP 200;
- login sintético: HTTP 200 y token emitido por el flujo real;
- `/historia-clinica`: propio HTTP 200, sin sesión HTTP 302;
- `/historia-clinica/ficha-identificiacion/paciente/9301`: propio HTTP 200, cruzado HTTP 403, sin sesión HTTP 302;
- el GET propio de P03 no alteró la fila sintética de `pc_paciente`.

## 3. Finalizaciones y navegación

### PASS

- P06: dos solicitudes simultáneas contra dos procesos HTTP independientes produjeron una sola fila; repetición idempotente y `next_url` PASS.
- P08, P09, P10 y P11: finalización, `next_url` e idempotencia PASS en los runners HTTP.
- El resolver de siguiente módulo de P04 pasó su prueba transaccional con rutas permitidas y fallback seguro.

### FAIL

La prueba HTTP específica de finalización observó duplicación lógica en P04, P05 y P07: la segunda solicitud devolvió HTTP 200, pero incrementó el número de filas en `pac_historia_clinica_finalizar` en lugar de conservar una sola fila por `(id_modulo, id_paciente)`. En P05 y P07 se partió de cero y el resultado posterior fue `2`; P04 tenía una fila fixture y llegó a `3` antes de restaurar el estado base.

Esto es un defecto funcional pendiente, no se corrigió durante PHASE-05B y bloquea declarar PASS el recorrido completo de finalización P04/P05/P07.

## 4. Seguridad y autorización

Resultados PASS:

- GET, AJAX y POST cruzados fueron rechazados para los módulos probados.
- Los accesos sin sesión fueron redirigidos o rechazados sin contenido clínico.
- P11 rechazó acceso cruzado a mapas y escrituras; la ruta de imagen propia funcionó.
- P10 validó catálogo, AJAX, edición, eliminación, paciente inexistente y finalización en la base sintética.
- Doctor/D40: GET de dashboard, resumen clínico, GET anónimo, acceso cruzado y delta clínico posterior al GET: PASS.

Resultado FAIL / limitación:

- `tests/p11/direct-image-security.php` devolvió HTTP 200 para los archivos sintéticos servidos directamente por el servidor PHP temporal, donde se esperaba 403. La prueba no ejerció reglas `.htaccess` de Apache; por ello la protección estática de Apache queda **NO VERIFICADA** y el resultado del ensayo directo se conserva como FAIL, sin corregirlo dentro de esta fase.

Se mantienen abiertos ACT-05C-02 (PDF, descargas y almacenamiento documental), autorización de recetas externas, riesgos COFEPRIS, SQL heredado y cualquier riesgo previamente documentado.

## 5. Contratos y calidad técnica

### PASS

- contratos P04–P11 y mocks de audio ejecutados, salvo la excepción indicada;
- contrato de navegación y velocidad P05;
- sensibilidad contractual P06;
- paridad estática del portal paciente;
- modales, sidebar, audio/resaltado y cierre responsive estático PHASE-04B: 103/103;
- cobertura clínica D40: 11 aserciones;
- narrativa clínica D40: 24 aserciones;
- PHP lint: 150 archivos, 0 fallos;
- sintaxis JavaScript: 98 archivos, 0 fallos;
- `git diff --check`: código 0.

### FAIL confirmado

El contrato estático `tests/p10/contract-check.mjs` falló porque el modal P10 no conserva el contrato de centrado de P04. No se modificó la aplicación.

### NO VERIFICADO

- El contador exacto de `UPDATE` para las pruebas no-op no se instrumentó a nivel SQL; los runners verificaron respuesta, estado y recuperación, pero no el número de sentencias.
- Reproducción audible humana real: los mocks PASS, reproducción audible NO VERIFICADA.
- Consola y Network de navegador, capturas PNG y dimensiones reales de viewport: NO VERIFICADOS en PHASE-05B por la limitación documentada en PHASE-05A-R1.
- Reglas de acceso estático específicas de Apache: NO VERIFICADAS; el ensayo con servidor PHP quedó FAIL.

## 6. Limpieza y estado final

PASS: se eliminaron únicamente respuestas, procedimientos, comentarios, archivos sintéticos y finalizaciones creados durante los runners. Los catálogos P05/P07/P09/P10 y los marcadores sintéticos autorizados se conservaron. La finalización fixture de P04 se restauró a una sola fila después de la prueba de duplicación.

La comprobación final de aislamiento volvió a confirmar la base `clinica_dolor_demo_20261010_final`, pacientes sintéticos en clínicas distintas y cero finalizaciones residuales para P11. No se realizaron commits, push ni despliegues.

## 7. Defectos y acciones posteriores

1. Corregir y volver a probar la idempotencia de finalización de P04, P05 y P07, con una restricción/operación segura según el contrato existente; requiere una actividad funcional independiente.
2. Revisar el contrato de centrado del modal P10.
3. Ejecutar las reglas de archivos privados bajo Apache real y cerrar la verificación P11.
4. Repetir la matriz visual PHASE-05A con navegador nativo, consola, Network y capturas reales.
5. Mantener visibles las vulnerabilidades documentales ACT-05C-02; este informe no las resuelve.

**STATUS:** `PARTIAL`  
**PHASE-05B completada:** sí, con defectos documentados  
**Producción:** `NO`  
**Siguiente fase iniciada:** no
