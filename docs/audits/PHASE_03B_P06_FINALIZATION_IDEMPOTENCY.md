# PHASE-03B-P06-FINALIZATION-IDEMPOTENCY

Fecha: 2026-10-12  
Proyecto: Clínica del Dolor  
Alcance: corrección focalizada de la finalización del módulo P06 (id clínico 4) en la base sintética allowlisted `clinica_dolor_demo_20261010_final`.

## Resultado ejecutivo

La reproducción de PHASE-03 confirmó que una primera finalización de P06 devolvía HTTP 200 y una segunda finalización también devolvía HTTP 200, pero creaba una segunda fila. La causa era el `INSERT` incondicional del camino genérico, junto con una tabla sintética/histórica que no tiene una restricción única para `(id_modulo, id_paciente)`.

La corrección quedó limitada a P06: el controlador deriva y valida el módulo, y el modelo serializa por paciente mediante `GET_LOCK`, comprueba la fila existente y sólo inserta cuando no existe. Una repetición compatible devuelve 200 sin insertar otra fila. No se modificó el esquema ni se eliminaron duplicados históricos.

```text
PHASE_03B_P06_FINALIZATION_IDEMPOTENCY: PARTIAL
ROOT_CAUSE: PacienteModulosModelo::finalizarModulos() ejecutaba INSERT incondicional; pac_historia_clinica_finalizar no tiene UNIQUE(id_modulo, id_paciente).
DUPLICATE_ROWS_CONFIRMED: YES; PHASE-03 reprodujo 2 filas para (4, 9301). Después de la corrección, la repetición conserva 1 fila.
CORRECTION: Rama exclusiva para idModulo=4 con comprobación parametrizada y GET_LOCK por paciente; validación de módulos fuera del catálogo 1..9 devuelve 422. Sin migración ni cambio de esquema.
FIRST_FINALIZATION: PASS; HTTP 200, una fila y next_url local.
REPEATED_FINALIZATION: PASS; HTTP 200, una fila, mismo next_url y sin incremento de filas.
CONCURRENT_FINALIZATION: PASS; dos procesos PHP independientes, solicitudes simultáneas a puertos distintos, misma base sintética; ambas respuestas HTTP 200 y una sola fila.
CROSS_PATIENT_AUTHORIZATION: PASS; token de 9302 no puede finalizar 9301, HTTP 403.
CROSS_CLINIC_AUTHORIZATION: PASS; 9301/9101 y 9302/9102 pertenecen a clínicas distintas; el cruce fue rechazado con HTTP 403.
NEXT_URL: PASS; primera y repetición devolvieron destino local estable.
P06_FUNCTIONAL_REGRESSION: PASS; GET sin escritura, CRUD sintético, persistencia, contratos, audio mock, invalidación de módulo y finalización.
P04_P11_FINALIZATION_REGRESSION: PARTIAL; el flujo de finalización P04 en mock, y las finalizaciones HTTP de P08, P09, P10 y P11 pasaron; los scripts HTTP ejecutados para P05 y P07 no cubren finalización, por lo que esa parte queda NO VERIFICADA. Sus pruebas HTTP de lectura/escritura/autorización sí pasaron.
GET_WITHOUT_WRITES: PASS; GET autenticado de P06 dejó sin cambios las filas clínicas comprobadas.
SYNTHETIC_CLEANUP: PASS; se eliminaron las cirugías y finalizaciones creadas para 9301/9302; no se modificó bd_clinica.
GLOBAL_DIFF_CHECK: PASS (exit 0; Git sólo reportó advertencias históricas LF/CRLF).
MODIFIED_FILES: app/Models/PacienteModulosModelo.php; app/Controllers/ModulosController.php; tests/p06/http-crud.php; docs/audits/PHASE_03B_P06_FINALIZATION_IDEMPOTENCY.md.
REMAINING_RISKS: No se hizo backfill ni compactación de duplicados históricos; la garantía depende de que todas las instancias compartan el mismo servidor MySQL; la prueba de navegador/responsive y la reproducción audible humana permanecen fuera de esta validación; los riesgos documentales ACT-05C-02 y la validación Apache de archivos/mapas siguen abiertos.
PRODUCTION_READY: NO
```

## Implementación

- `app/Controllers/ModulosController.php`: rechaza `idModulo` fuera de 1–9 y dirige exclusivamente el módulo 4 al método idempotente P06. Las rutas, autorización por paciente y cálculo de `next_url` existentes se conservaron.
- `app/Models/PacienteModulosModelo.php`: añade `finalizarModuloP06()`. El bloqueo de aplicación se obtiene con `GET_LOCK` usando un nombre derivado de un identificador entero; dentro del bloqueo se busca la finalización existente y se devuelve éxito compatible sin `INSERT`. Si no existe, se realiza el `INSERT` parametrizado. `RELEASE_LOCK` se ejecuta en `finally`.
- `tests/p06/http-crud.php`: añade sesión anónima, módulo inválido sin escritura, segundo paciente legítimo, repetición con `next_url` estable y una prueba concurrente que permite dirigir las dos solicitudes a dos procesos HTTP independientes mediante `--concurrent-base-url=` y `--concurrent-capable=yes`.

No se cambió `pac_historia_clinica_finalizar`, no se añadió un índice único y no se tocaron módulos P04, P05 ni P07–P11. El contrato vigente de P06 permite finalizar sin cirugías registradas; por ello el escenario de “formulario incompleto” no aplica a este módulo y no se inventó una regla de completitud.

## Evidencia ejecutada

### P06

- HTTP CRUD con dos procesos PHP independientes: PASS.
- Primera finalización: PASS.
- Repetición secuencial: PASS, una fila.
- Repetición enviada como ráfaga concurrente: PASS, una fila.
- Autorización propia, cruzada y sin sesión: PASS (200 / 403 / 302 según escenario).
- Módulo inválido: PASS, HTTP 422 y sin escritura.
- GET sin escritura: PASS.
- Contrato estático y sensibilidad CSS: PASS.
- Audio mock: PASS; reproducción audible humana: NO VERIFICADO.

### Regresión compartida

Pasaron los scripts HTTP sintéticos de P04, P05, P07, P08, P09, P10 y P11 para las coberturas que cada script implementa. El flujo de finalización P04 en mock, las finalizaciones HTTP P08–P11, el resolver `tests/p04/next-module.php` y `tests/phase02/doctor-read-only.php` también pasaron. Los scripts HTTP P05 y P07 no ejercitan la finalización de sus módulos: esa cobertura permanece NO VERIFICADA y no se presenta como PASS.

La prueba no declara PASS para navegador, responsive, consola de navegador ni reproducción audible humana: no fueron ejecutadas en esta fase.

## Limpieza y límites

La base activa fue comprobada como `clinica_dolor_demo_20261010_final`; la prueba fail-closed no permite otra base. Las filas creadas para los pacientes sintéticos 9301 y 9302 se limpiaron en `finally`. No se usó el paciente real 310, no se tocó `bd_clinica`, no se realizaron commits ni despliegues y no se inició Phase 4.

La compatibilidad con duplicados históricos se limita a no crear nuevos duplicados; su investigación, consolidación o migración requiere una actividad y autorización separadas.
