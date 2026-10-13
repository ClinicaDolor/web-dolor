# Pruebas P05 — Antecedentes personales no patológicos

La prueba debe ejecutarse sólo contra la base
clinica_dolor_demo_20261010_final. El preflight es de lectura y falla si la
base activa no coincide exactamente con esa allowlist.

    $env:P05_TEST_DATABASE = 'clinica_dolor_demo_20261010_final'
    php tests/p05/prepare-synthetic.php --database=clinica_dolor_demo_20261010_final --repair-schema --seed-catalog --json
    php tests/p05/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
    php tests/p05/http-crud.php --database=clinica_dolor_demo_20261010_final --base-url=http://127.0.0.1:8087
    node tests/p05/contract-check.mjs
    node tests/p05/audio-mock.mjs
    node tests/p05/navigation-contract.mjs
    node tests/p05/flow-speed-contract.mjs
    php tests/p05/cleanup-synthetic.php --database=clinica_dolor_demo_20261010_final

La preparación sólo puede reparar el esquema contractual de la base
allowlisted y exige `--repair-schema`; nunca toma `DB_DATABASE` implícitamente.
El preflight reporta si el catálogo de siete temas está disponible. La opción
`--seed-catalog` carga únicamente `tests/fixtures/p05-catalog.php`, extraído de
los dos SQL originales suministrados, y nunca ejecuta los volcados completos.
La carga exige coincidencia exacta, es idempotente y falla ante conflictos.
El fixture contiene 7 temas y 25 preguntas, con los IDs, tipos, textos y orden
originales; no contiene pacientes ni respuestas.

La apertura GET de P05 conserva un efecto lateral preexistente: crea una fila
de respuesta vacía por pregunta faltante. Las pruebas HTTP lo permiten sólo en
la base sintética y `cleanup-synthetic.php` elimina las respuestas, comentarios
y finalización de los pacientes 9301/9302 al terminar, manteniendo el catálogo.

## Cobertura

| Escenario | Resultado actual |
|---|---|
| A. GET autorizado / paciente propio | PASS HTTP y navegador sintético |
| B. GET cruzado / otra clínica | PASS; HTTP 403 y HTML «No autorizado.» |
| C. Sí/No exclusivo y clic/teclado | PASS en navegador sintético; radios/labels/foco |
| D. Dependencias clínicas | PASS; input dependiente guardado y recuperado |
| E. Guardado y recuperación | PASS HTTP/AJAX; respuesta restaurada y limpieza final |
| F. Navegación de siete temas | PASS; recorrido real por los 7 temas |
| G. No duplicar AJAX | PASS estático y sin errores durante recorrido |
| H. Error de guardado y rollback visual | NO VERIFICADO en fallo forzado |
| I. Comentario y finalización | PASS; comentario y finalización reales |
| J. Doble clic de finalización | PASS estático; doble clic real NO VERIFICADO |
| K. next_url | PASS HTTP; navegación real a P06 |
| L. Dashboard después de finalizar | NO VERIFICADO |
| M. Escritura cruzada | PASS HTTP; POST cruzado 403 |
| N. Respuesta ajena por idRespuesta | PASS HTTP; contexto de respuesta validado |
| O. Sin sesión | PASS HTTP 302 en regresión focal |

La cobertura estática de audio se ejecuta con `audio-mock.mjs`: verifica la
utterance, voz española preferente, estados de inicio/fin/error, cancelación
al navegar y narración automática de instrucciones/preguntas. Es un mock de
contrato y no confirma reproducción audible real.

`navigation-contract.mjs` verifica el HTML contractual del modelo para una
pregunta `select`: el botón Siguiente permanece en el DOM cuando la respuesta
está vacía, queda deshabilitado con `aria-disabled`/`title` accesibles sin
agregar un mensaje permanente al footer y se conserva la rama de avance para
respuestas guardadas. Es una prueba sintética estática; no afirma persistencia
ni navegación real mientras falte el catálogo aprobado.

La creación de respuestas faltantes durante el GET de la vista es un efecto
secundario preexistente (`antecedentesNoPatologicos()`); se conserva y sólo se
probó en la base sintética allowlisted. La reproducción audible humana queda
NO VERIFICADA: se comprobaron estados y eventos del navegador, no la escucha
por una persona.

## P05-FLOW-SPEED-01

El flujo de navegación usa delegación de eventos y conserva en cada control el
valor confirmado por el servidor. `Siguiente` guarda los controles modificados
antes de avanzar; si un guardado iniciado por `change` está pendiente, deja una
sola intención de navegación en cola y la ejecuta después de la confirmación.
Las tarjetas Sí/No guardan y avanzan automáticamente cuando la pregunta queda
completa; las dependencias permanecen en el tema y Grupo Sanguíneo conserva sus
dos campos de texto. La interfaz no reconstruye la pregunta durante el guardado.

La prueba `flow-speed-contract.mjs` cubre estáticamente la delegación, la cola,
la transición única, la limpieza de dependencias, la narración posterior y el
catálogo 7/25. La validación HTTP/browser sintética debe ejecutarse después de
un preflight exacto de `clinica_dolor_demo_20261010_final` y debe limpiar las
respuestas de 9301/9302 al terminar.
