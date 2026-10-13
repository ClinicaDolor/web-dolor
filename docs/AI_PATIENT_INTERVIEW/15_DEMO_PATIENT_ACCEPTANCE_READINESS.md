# Fase 4.3 — Identificación del paciente demo y preparación de prueba controlada

Fecha: 12 de octubre de 2026  
Proyecto: Clínica del Dolor  
Entorno objetivo: `http://clinica.test`  
Base local: `bd_clinica`

## Estado ejecutivo

Esta fase no modificó producto, `.env`, esquema, `bd_clinica`, P05–P11 ni Gemini. No se capturaron respuestas en `clinica.test` y no se ejecutó ninguna restauración sobre `bd_clinica`.

El mecanismo de autenticación permite obtener el paciente desde el JWT ya validado por `AuthMiddleware`: `PatientInterviewController` usa `auth['id_usuario']` y lo pasa a la vista como `data-patient`. Sin embargo, la sesión del propietario no estuvo accesible para esta ejecución. No se solicitaron ni leyeron PIN, contraseña, JWT, cookies o tokens.

Por tanto, el paciente demo no está identificado y la prueba de escritura permanece bloqueada.

## 1. Identificación segura del paciente

### Evidencia del mecanismo existente

- `GET /historia-clinica/entrevista` requiere `AuthMiddleware('historia-clinica')`.
- El identificador técnico proviene de `auth['id_usuario']`, no de un parámetro libre del navegador.
- `assertPatientAccess` se ejecuta en `/historia-clinica/entrevista/capturar` antes de delegar al modelo.
- La vista autenticada contiene el identificador técnico en `#main[data-patient]`; no se creó ningún endpoint de diagnóstico.

### Procedimiento para el propietario

Con la sesión demo ya iniciada manualmente, sin compartir credenciales ni cookies:

1. Abrir `http://clinica.test/historia-clinica/entrevista`.
2. Abrir DevTools del navegador y ejecutar localmente:

   ```javascript
   document.querySelector('#main')?.dataset.patient
   ```

3. Conservar el resultado localmente o comunicar únicamente el número técnico, nunca el HTML completo, nombre, respuestas, JWT, cookie o captura clínica.
4. Verificar después, mediante una consulta parametrizada de solo lectura, que ese ID existe en `pc_paciente` y pertenece a la clínica de la sesión. La consulta no debe imprimir nombre, fecha de nacimiento, teléfono, correo ni respuestas.

Si el elemento no existe o el número no puede correlacionarse de forma segura con la sesión autenticada, se debe detener la preparación.

`DEMO_IDENTITY: NOT_VERIFIED`

`DEMO_PATIENT_ID: NOT_VERIFIED`

## 2. Alcance M03/Tabaquismo

### Lectura

La entrevista consulta:

- `pac_temas_modulo_3`, para el tema;
- `pac_preguntas_modulo_3`, para las preguntas y tipos;
- `pac_respuestas_paciente_modulo_3`, mediante `LEFT JOIN`, para la respuesta existente del paciente.

El alcance autorizado es el tema 1 y las preguntas M03 IDs 1–5 según el catálogo sintético y los contratos existentes. La vista independiente no crea filas al abrirse.

### Guardado potencial

La captura confirmada puede afectar únicamente, dentro del paciente identificado y tema autorizado:

- `pac_respuestas_paciente_modulo_3`: crear la fila faltante o actualizar `respuesta`.
- Si se confirma `No` en la pregunta condicional y existen respuestas dependientes, el endpoint devuelve 409 para exigir revisión en el formulario tradicional; el modelo tradicional sí contiene una regla de limpieza de respuestas dependientes.

La entrevista no agrega comentarios ni finaliza el módulo. No se encontraron llamadas externas ni uso de Gemini.

Las tablas `pac_historia_clinica_comentario` y `pac_historia_clinica_finalizar` forman parte del sistema clínico, pero no son escritas por la captura independiente. Sus relaciones locales fueron inspeccionadas sólo con `information_schema`.

No se deben interpretar filas con `respuesta=''` o `NULL` como respuestas contestadas.

### Efecto GET heredado

- La entrevista independiente usa `obtenerPreguntasEntrevista()` con `LEFT JOIN` y no siembra respuestas.
- El formulario tradicional M03 conserva un efecto secundario heredado: `antecedentesNoPatologicos()` puede insertar filas vacías al abrir ciertas preguntas.
- Ese GET heredado no se ejecutó contra el expediente demo y no debe abrirse en `bd_clinica` antes de realizar un respaldo focal.

`M03_GET_SIDE_EFFECT: VERIFIED — sólo formulario tradicional heredado; entrevista independiente sin siembra estructural`

## 3. Integridad y respaldo focal preparado

### Filas a proteger

Una vez identificado el ID, la instantánea debe limitarse a:

```sql
SELECT id, id_pregunta, id_paciente, respuesta
FROM pac_respuestas_paciente_modulo_3
WHERE id_paciente = :demo_patient_id
ORDER BY id;
```

Debe conservarse localmente, fuera del repositorio y con permisos restringidos. El informe no debe contener valores clínicos. Registrar sólo localmente: IDs de filas, IDs de preguntas, conteo, hora, hash del archivo y base efectiva.

Antes de cualquier prueba, verificar que cada `id_pregunta` pertenece al catálogo M03 y que `id_paciente` coincide exactamente con el ID autenticado. No incluir otros pacientes.

`DEMO_BACKUP_READY: PREPARED_NOT_EXECUTED — pendiente de identificar el paciente`

## 4. Ensayo de restauración aislado

Se ejecutó un ensayo transaccional únicamente sobre `clinica_dolor_demo_20261010_final`, usando el paciente sintético 9301 y una pregunta sintética M03. El ensayo:

1. leyó el conteo inicial;
2. insertó temporalmente una respuesta marcada para el ensayo;
3. actualizó temporalmente esa fila;
4. eliminó sólo la fila temporal del paciente sintético;
5. comprobó el conteo restaurado;
6. ejecutó `ROLLBACK` de la transacción;
7. confirmó conteo final cero.

Resultado técnico: inserción/actualización/restauración observadas y `persistent_write=false`. No se imprimieron valores clínicos. Esto valida el patrón transaccional de fixture, pero no prueba una restauración persistida con `COMMIT` ni una restauración del expediente demo real.

`DEMO_RESTORE_TESTED: PASS_TRANSACTIONAL_DRY_RUN_ONLY`

### Restauración futura autorizada

Para `bd_clinica`, sólo después de autorización explícita:

1. detener ediciones concurrentes;
2. confirmar `SELECT DATABASE() = 'bd_clinica'`, host local y el paciente identificado;
3. guardar la instantánea focal anterior;
4. realizar una sola captura desde la interfaz;
5. comparar IDs, conteo y respuestas del único paciente;
6. restaurar en transacción sólo las filas de la instantánea y eliminar únicamente IDs nuevos creados por esa prueba;
7. comprobar catálogo y paciente antes de confirmar;
8. hacer `COMMIT` sólo con autorización; ante cualquier diferencia, `ROLLBACK` y revisión manual.

No usar `TRUNCATE`, limpieza global, restauración de toda la tabla ni borrado por clínica.

`OTHER_PATIENTS_PROTECTED: PASS_BY_SCOPE_DESIGN; NOT_RUNTIME_VERIFIED_FOR_UNKNOWN_DEMO`

## 5. Preparación manual futura

No ejecutar todavía la siguiente secuencia; es el procedimiento que queda preparado para autorización:

1. Confirmar que `clinica.test` sigue apuntando a `bd_clinica` local y que el ID demo fue verificado.
2. Crear el respaldo focal de M03.
3. Abrir `http://clinica.test/historia-clinica/entrevista` con el login normal del propietario.
4. Confirmar que aparecen las preguntas originales M03/Tabaquismo.
5. Seleccionar una respuesta de prueba sintética autorizada, cancelar la confirmación y verificar que no cambia el conteo.
6. Solicitar autorización explícita del propietario antes de confirmar una respuesta.
7. Confirmar una única respuesta y registrar sólo HTTP, conteo, IDs y estado, nunca el contenido clínico.
8. Recargar y comprobar recuperación.
9. Revisar el mismo estado desde el formulario tradicional sin provocar navegación adicional innecesaria.
10. Comparar contra la instantánea focal y restaurar sólo si se autoriza.

`READY_FOR_OWNER_WRITE_APPROVAL: NO`

## 6. Reporte obligatorio

```text
CLINICA_TEST_LOCAL_ISOLATION: VERIFIED — bd_clinica es copia local; MySQL loopback; no replicación observada
DEMO_PATIENT_IDENTIFIED: NOT_VERIFIED
DEMO_PATIENT_AUTHORIZATION: NOT_VERIFIED
DEMO_WRITE_SCOPE: NOT_AUTHORIZED — pendiente de ID; sólo pac_respuestas_paciente_modulo_3

M03_GET_SIDE_EFFECT: VERIFIED — efecto heredado del formulario tradicional; entrevista independiente sin siembra
M03_EXISTING_RESPONSES_PROTECTED: NOT_YET — paciente no identificado y respaldo real no ejecutado

DEMO_BACKUP_READY: PREPARED_NOT_EXECUTED
DEMO_RESTORE_TESTED: PASS_TRANSACTIONAL_DRY_RUN_ONLY — fixture sintético, sin persistencia
OTHER_PATIENTS_PROTECTED: PASS_BY_SCOPE_DESIGN; RUNTIME NOT_VERIFIED

CHAT_INDEPENDENCE: PASS — rutas/vista/modelo separados del formulario tradicional
LEGACY_M03_PRESERVED: PASS — sin cambios de producto

READY_FOR_OWNER_WRITE_APPROVAL: NO
BLOCKING_ISSUES: DEMO_IDENTITY_NOT_VERIFIED; REAL_BACKUP_NOT_EXECUTED; CLINICA_TEST_USES_BD_CLINICA; LEGACY_GET_SIDE_EFFECT

PRODUCT_CODE_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La fase se detiene antes de cualquier escritura en `bd_clinica` y queda a la espera de autorización expresa del propietario.

---

## Addendum de identificación — 12 de octubre de 2026

El propietario proporcionó el identificador técnico `310`, obtenido desde `#main[data-patient]` en su sesión demo. Se ejecutaron únicamente consultas parametrizadas de lectura contra la copia local.

### Correlación verificada

- `pc_paciente.id = 310` existe.
- `pc_paciente.id_clinica = 1`.
- Existe una fila de acceso para `paciente_id = 310` y se encontró un acceso activo/no expirado. No se leyó ni mostró el PIN.
- El flujo real de autenticación genera el JWT con `id_usuario = idPaciente`; `AuthMiddleware` devuelve ese valor como `auth['id_usuario']`; `PatientInterviewController` lo usa como `$patientId` y lo expone sólo como `data-patient`.
- El expediente técnico queda correlacionado con la sesión demo declarada por el propietario. No se incluyeron nombres ni valores clínicos.

`DEMO_IDENTITY: VERIFIED`

`DEMO_CLINICAL_RECORD: VERIFIED — paciente técnico 310, clínica técnica 1`

### Estado M03 del expediente 310

La consulta de metadatos confirmó:

- 25 filas M03 totales para el paciente, correspondientes al catálogo de siete temas.
- Cinco filas para las preguntas M03/Tabaquismo 1–5.
- Una fila por pregunta; cuatro tienen contenido no vacío y una está vacía. Los valores no se imprimieron.
- La pregunta 1 es `select`; las preguntas 2–5 son `input`.
- Existen tres respuestas dependientes no vacías entre las preguntas 2–5.

Consecuencia de seguridad: no debe probarse el cambio de la pregunta 1 a `No`, porque el modelo detectaría respuestas dependientes y debe devolver 409 para exigir revisión tradicional. Una prueba controlada debe utilizar exclusivamente la pregunta 5, que está vacía según el metadato de lectura, y un valor de prueba elegido por el propietario, seguido de recuperación y restauración focal.

`DEMO_WRITE_SCOPE: pac_respuestas_paciente_modulo_3, paciente 310, pregunta 5 únicamente para la prueba propuesta`

### Respaldo focal — preparado, no ejecutado

Los siguientes comandos son instrucciones para una ejecución posterior autorizada. No se ejecutaron durante esta fase. Solicitan la contraseña de MySQL de forma interactiva y no la incluyen en la línea de comandos. El archivo se ubica en `%TEMP%`, fuera del repositorio.

```powershell
$demoPatientId = 310
$backupPath = Join-Path $env:TEMP "clinica-demo-m03-$demoPatientId.sql"

mysqldump --host=127.0.0.1 --port=3306 --user=<USUARIO_LOCAL_MYSQL> --password `
  --single-transaction --skip-lock-tables --no-create-info --skip-triggers `
  --where="id_paciente = $demoPatientId" `
  bd_clinica pac_respuestas_paciente_modulo_3 > $backupPath
```

Después debe verificarse sólo metadata, sin imprimir `respuesta`:

```powershell
mysql --host=127.0.0.1 --port=3306 --user=<USUARIO_LOCAL_MYSQL> --password bd_clinica `
  --batch --skip-column-names `
  --execute="SELECT id,id_pregunta,id_paciente,OCTET_LENGTH(respuesta) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=310 ORDER BY id;"
```

Debe registrarse localmente el hash del archivo y el conteo, nunca el contenido clínico. El respaldo debe permanecer fuera de Git; verificar antes de iniciar que la ruta no esté bajo el repositorio y no subirlo a ningún servicio.

`DEMO_BACKUP_READY: YES — procedimiento focal preparado; respaldo aún no creado`

### Restauración focal — preparada y probada en patrón sintético

El ensayo transaccional previo sobre `clinica_dolor_demo_20261010_final` observó inserción, actualización, eliminación focal y `ROLLBACK`, terminando con cero filas persistentes. No fue una restauración persistida de `bd_clinica`.

Para una restauración autorizada del respaldo de 310, con la aplicación detenida o sin concurrencia, debe usarse una única transacción y sólo el paciente 310:

```powershell
$restoreScript = Join-Path $env:TEMP 'restore-clinica-demo-m03-310.sql'
@"
START TRANSACTION;
DELETE FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = 310;
SOURCE $backupPath;
COMMIT;
"@ | Set-Content -LiteralPath $restoreScript -Encoding UTF8

mysql --host=127.0.0.1 --port=3306 --user=<USUARIO_LOCAL_MYSQL> --password bd_clinica < $restoreScript
```

Antes de confirmar se debe comparar conteo e IDs con la instantánea. Si existe cualquier diferencia o concurrencia, no ejecutar restauración automática; detenerse y usar `ROLLBACK`/revisión manual. No usar `TRUNCATE`, no borrar por clínica y no restaurar tablas de otros pacientes.

`DEMO_RESTORE_READY: YES — procedimiento focal y patrón transaccional sintético validados; no ejecutado en bd_clinica`

### Prueba controlada propuesta, aún no ejecutada

Una sola prueba, después de autorización explícita:

1. Crear y verificar el respaldo focal del paciente 310.
2. Abrir `/historia-clinica/entrevista` con la sesión demo ya autenticada.
3. Avanzar hasta la pregunta 5, que actualmente está vacía.
4. Introducir un valor de prueba elegido por el propietario y confirmar el diálogo nativo.
5. Verificar HTTP 200, sólo la fila de pregunta 5, sin cambios en las preguntas 1–4 ni en otros pacientes.
6. Recargar la entrevista y comprobar recuperación.
7. Comparar contra la instantánea y restaurar sólo si el propietario lo solicita.
8. Repetir verificación de conteos e IDs; no guardar respuestas en este informe.

`CONTROLLED_WRITE_TEST: READY — BLOCKED_PENDING_OWNER_AUTHORIZATION`

### Reporte rectificado

```text
DEMO_IDENTITY: VERIFIED
DEMO_CLINICAL_RECORD: VERIFIED
DEMO_BACKUP_READY: YES
DEMO_RESTORE_READY: YES
CONTROLLED_WRITE_TEST: READY / BLOCKED_PENDING_OWNER_AUTHORIZATION
```

No se ejecutó el respaldo, la escritura ni la restauración. El archivo de respaldo futuro será local, focal, fuera de Git y no se enviará a servicios externos.
