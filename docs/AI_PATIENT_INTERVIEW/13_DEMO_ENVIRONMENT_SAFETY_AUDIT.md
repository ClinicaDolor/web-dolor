# Auditoría de seguridad del entorno demo y de la Entrevista Conversacional

Fecha: 11 de octubre de 2026  
Proyecto: `C:\xampp\htdocs\clinica-dolor`  
Modalidad: solo lectura. No se realizaron capturas, guardados, limpiezas ni cambios de configuración.

## 1. Resultado ejecutivo

`http://clinica.test` es atendido por Apache/XAMPP mediante el proyecto local `C:\xampp\htdocs\clinica-dolor`, pero la conexión efectiva de la aplicación devuelve `bd_clinica`. Por tanto, es un entorno conectado a la base principal y no puede considerarse un entorno demo aislado.

El servidor PHP de pruebas en `127.0.0.1:8087` está escuchando, pero se observaron dos procesos PHP en el mismo puerto y no fue posible identificar sus variables de proceso sin acceder a información protegida del sistema. La intención y la guarda del arnés P05 son sintéticas, pero la instancia actualmente activa no queda demostrada de forma independiente por una consulta HTTP sin sesión.

Conclusión: no se autoriza ninguna escritura desde `clinica.test`. La prueba de guardado debe realizarse únicamente en un servidor P05 aislado cuya base haya sido confirmada inmediatamente antes mediante su preflight.

## 2. Entorno `clinica.test`

### Configuración verificada

- Hosts local: `127.0.0.1 clinica.test`.
- Apache activo: `C:\xampp\apache\bin\httpd.exe`, PID principal observado 4304.
- Apache escucha en el puerto 80.
- VirtualHost: `ServerName clinica.test`.
- DocumentRoot: `C:\xampp\htdocs\clinica-dolor`.
- Bootstrap: `public/index.php` carga Composer, `app/Config/constantes.php`, Dotenv y el router.
- `.htaccess` raíz redirige las solicitudes a `public/`; `public/.htaccess` redirige rutas dinámicas a `public/index.php`.
- `http://clinica.test/`: HTTP 200.
- `http://clinica.test/historia-clinica/acceso`: HTTP 200.
- `http://clinica.test/historia-clinica/entrevista`: HTTP 302 sin sesión en la solicitud de comprobación.

### Base efectiva

La consulta de solo lectura ejecutada mediante el mismo bootstrap de la aplicación fue `SELECT DATABASE()` y devolvió `bd_clinica`.

Esto permite afirmar:

- El proyecto físico está verificado.
- La base efectiva de `clinica.test` es `bd_clinica`.
- `clinica.test` comparte la base principal con los datos no sintéticos que existan allí.
- No hay evidencia de una separación por cuenta demo en la base de Apache.
- La API key de Gemini está configurada en `.env`, pero su contenido no fue mostrado ni registrado.
- Las banderas no secretas actuales son `CLINICAL_AI_SUMMARY_ENABLED=1`, `CLINICAL_AI_SYNTHETIC_MODE=0` y `CLINICAL_AI_EXTERNAL_ENABLED=0`. Esto mantiene Gemini externo deshabilitado, pero no convierte `bd_clinica` en una base demo.

`CLINICA_TEST_SHARED_WITH_PRODUCTION: VERIFIED (base bd_clinica; aislamiento demo no demostrado)`

### Sesiones

El login usa la cookie `CLINICAJWT` con `path=/`, `HttpOnly` y `SameSite=Lax`, sin dominio explícito; por comportamiento del navegador es una cookie ligada al host. Esto evita compartir automáticamente la cookie entre `clinica.test` y `127.0.0.1`, pero no prueba aislamiento de datos ni impide que ambos entornos utilicen la misma base.

No se inspeccionaron cookies, JWT, credenciales ni la sesión abierta por el propietario.

## 3. Identidad del expediente demo

La solicitud HTTP utilizada para esta auditoría no incluyó la sesión del propietario. No se accedió a credenciales ni se intentó inferir el paciente a partir de HTML autenticado.

`DEMO_ACCOUNT_IDENTITY: NOT_VERIFIED`

No puede afirmarse que el usuario demo de `clinica.test` sea uno de los pacientes sintéticos 9301/9302. Los nombres o etiquetas de demostración no bastan para probar aislamiento.

`DEMO_PATIENT_ISOLATION: NOT_VERIFIED; entorno rechazado para escrituras`

`DEMO_WRITE_SCOPE: NOT_VERIFIED`

El alcance real de una sesión no identificada no puede comprobarse sin que el propietario realice el login manualmente y documente sólo un identificador técnico no sensible. No se debe realizar esa verificación escribiendo en `bd_clinica`.

`DEMO_RESTORE_AVAILABLE: NOT_VERIFIED`

Los limpiadores P05 sólo están autorizados para la base sintética y los pacientes sintéticos 9301/9302. No existe evidencia de un procedimiento específico para restaurar de forma segura un expediente demo desconocido dentro de `bd_clinica`.

## 4. Comparación con P05

| Componente | `clinica.test` | `127.0.0.1:8087` |
|---|---|---|
| Proyecto físico | VERIFICADO: `C:\xampp\htdocs\clinica-dolor` | Diseñado para el mismo proyecto mediante router P05 |
| Servidor | Apache/XAMPP, puerto 80 | PHP integrado; actualmente hay dos listeners PHP observados |
| Base efectiva | VERIFICADA: `bd_clinica` | Diseñada y allowlisted como `clinica_dolor_demo_20261010_final`; instancia activa actual NOT_VERIFIED por proceso ambiguo |
| Usuario demo | NOT_VERIFIED | Pacientes sintéticos 9301/9302 en el fixture P05 |
| Sesiones | Cookie de host `clinica.test` | La guarda P05 inyecta una `APP_KEY` efímera por proceso; no se debe compartir cookie |
| Escrituras | NO SEGURAS para esta prueba | Permitidas sólo tras preflight y autorización explícita de fixture |
| Riesgo | Alto: base principal y cuenta no identificada | Diseñado como aislado; confirmar instancia antes de escribir |

Comprobaciones HTTP sin sesión:

- `clinica.test/historia-clinica/acceso`: HTTP 200.
- `127.0.0.1:8087/historia-clinica/acceso`: HTTP 200.
- Ambas entrevistas sin sesión redirigen con HTTP 302.

La similitud de respuestas no prueba que compartan base. La consulta efectiva de la aplicación sólo se realizó para el proceso local de Apache; no se expuso un diagnóstico de base desde el servidor 8087.

## 5. Entrevista independiente

### Autenticación y autorización

VERIFICADO estáticamente:

- `PatientInterviewController::index()` usa `AuthMiddleware('historia-clinica')`.
- La captura vuelve a autenticar y llama a `assertPatientAccess`.
- No existe un campo de paciente elegible por el navegador para cambiar el expediente de sesión.

Runtime autenticado en `clinica.test`: `NOT_VERIFIED`, porque no se usó la sesión del propietario.

### CSRF y concurrencia

VERIFICADO en contratos y en la prueba sintética previa:

- El endpoint exige `X-M03-CSRF`.
- Ausencia de CSRF fue rechazada con HTTP 403 en el entorno sintético.
- La captura comprueba el valor esperado de la respuesta y puede devolver HTTP 409 ante concurrencia obsoleta.

Runtime autenticado contra `bd_clinica`: `NOT_VERIFIED` y no debe probarse mediante escritura.

### Catálogo y persistencia

VERIFICADO estructuralmente:

- La entrevista lee las cinco preguntas M03 del catálogo existente.
- La lectura usa `LEFT JOIN` y no siembra filas al abrir la vista independiente.
- La captura delega al modelo clínico M03 existente.
- La tabla potencialmente modificada por una respuesta confirmada es `pac_respuestas_paciente_modulo_3`.
- Según la respuesta y la regla condicional, el mismo modelo puede limpiar respuestas dependientes en esa tabla.
- No se creó tabla, catálogo ni historia paralela.

No se ejecutó captura en ningún entorno durante esta auditoría.

`CHAT_SHARED_PERSISTENCE: VERIFIED (estructura/modelo; escritura runtime NOT_VERIFIED)`

### Separación del formulario tradicional

VERIFICADO por contrato y lectura de archivos:

- `/historia-clinica/entrevista` tiene vista y controlador propios.
- M03 tradicional no contiene el selector conversacional ni ramas guiadas.
- El chat no añade controles a M01–M09.
- La vista conversacional no contiene API key ni llamada a Gemini.

`CHAT_ROUTE: VERIFIED`

`CHAT_AUTHORIZATION: VERIFIED (estático; runtime clinica.test NOT_VERIFIED)`

`CHAT_CSRF: VERIFIED (contrato y sintético; runtime bd_clinica NOT_VERIFIED)`

## 6. Efectos secundarios y riesgos

- La vista independiente de entrevista está diseñada para no escribir al abrirse.
- El formulario tradicional M03 conserva un efecto lateral heredado: ciertos GET pueden inicializar filas vacías en `pac_respuestas_paciente_modulo_3`. No debe confundirse una fila vacía con una respuesta clínica.
- La captura confirmada puede insertar/actualizar la respuesta M03 y limpiar dependientes conforme a las reglas existentes.
- En `clinica.test`, cualquier efecto de lectura o guardado recaería sobre `bd_clinica` y podría afectar datos compartidos.
- La presencia de `GEMINI_API_KEY` no implica una llamada externa mientras `CLINICAL_AI_EXTERNAL_ENABLED=0`; no se hizo ninguna prueba Gemini.

`CHAT_READ_SIDE_EFFECTS: VERIFIED (entrevista independiente sin siembra estructural; efecto heredado del GET M03 tradicional documentado)`

## 7. Resultado de seguridad para escrituras

`SAFE_FOR_DEMO_WRITES: NO`

Razones bloqueantes:

1. `clinica.test` conecta efectivamente a `bd_clinica`.
2. La identidad del usuario demo y del expediente no fue verificada.
3. No se demostró aislamiento respecto a otros expedientes.
4. No existe un procedimiento de restauración específico para ese expediente en `bd_clinica`.
5. El formulario M03 tradicional tiene un efecto lateral GET heredado.
6. Probar el guardado modificaría potencialmente `pac_respuestas_paciente_modulo_3` de la base principal.

`BLOCKING_RISKS: VERIFIED_BASE_MAIN; DEMO_IDENTITY_NOT_VERIFIED; RESTORE_NOT_VERIFIED; LEGACY_GET_SIDE_EFFECT`

## 8. Entorno recomendado

`RECOMMENDED_TEST_ENVIRONMENT: 127.0.0.1:8087, sólo después de confirmar una única instancia P05 y el preflight sintético`

Comprobación sin escritura:

```powershell
php tests/p04/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
php tests/p05/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
```

El script `tests/p05/start-server.ps1` está diseñado para aceptar exclusivamente `clinica_dolor_demo_20261010_final`, pero ejecuta preparación del catálogo con opciones que pueden escribir en la base sintética. Por eso no se ejecutó durante esta auditoría de solo lectura. No debe iniciarse otra instancia sobre el puerto 8087 hasta resolver la presencia de los dos listeners observados.

Una futura prueba de escritura requiere autorización separada para el fixture, preflight inmediatamente anterior, un único PID del servidor, login normal con el PIN sintético local sin publicarlo y limpieza focal P05 al finalizar. No se debe usar `clinica.test`, `bd_clinica`, cuentas reales ni credenciales nuevas.

## 9. Reporte obligatorio

```text
CLINICA_TEST_PROJECT: VERIFIED — C:\xampp\htdocs\clinica-dolor
CLINICA_TEST_DATABASE: VERIFIED — bd_clinica
CLINICA_TEST_ENVIRONMENT: VERIFIED — Apache/XAMPP, VirtualHost clinica.test, puerto 80
CLINICA_TEST_SHARED_WITH_PRODUCTION: VERIFIED — comparte bd_clinica; aislamiento demo NO demostrado

DEMO_ACCOUNT_IDENTITY: NOT_VERIFIED — no se accedió a la sesión del propietario
DEMO_PATIENT_ISOLATION: NOT_VERIFIED — no puede asumirse aislamiento en bd_clinica
DEMO_WRITE_SCOPE: NOT_VERIFIED — no se ejecutaron escrituras
DEMO_RESTORE_AVAILABLE: NOT_VERIFIED — no hay restauración específica demostrada para bd_clinica

P05_TEST_DATABASE: VERIFIED_BY_ALLOWLIST/DESIGN; ACTIVE_INSTANCE NOT_VERIFIED — dos PHP listeners en 127.0.0.1:8087
P05_ISOLATION: VERIFIED_FOR_PRIOR_PREFLIGHT; CURRENT_RUNTIME NOT_VERIFIED

CHAT_ROUTE: VERIFIED
CHAT_AUTHORIZATION: VERIFIED_STATIC; RUNTIME_CLINICA_TEST NOT_VERIFIED
CHAT_CSRF: VERIFIED_CONTRACT/SYNTHETIC; RUNTIME_BD_CLINICA NOT_VERIFIED
CHAT_SHARED_PERSISTENCE: VERIFIED_STRUCTURAL; WRITE_RUNTIME NOT_VERIFIED
CHAT_READ_SIDE_EFFECTS: VERIFIED — entrevista sin siembra; GET M03 tradicional heredado puede sembrar filas vacías

SAFE_FOR_DEMO_WRITES: NO
BLOCKING_RISKS: VERIFIED_BASE_MAIN; DEMO_IDENTITY_NOT_VERIFIED; RESTORE_NOT_VERIFIED; LEGACY_GET_SIDE_EFFECT
RECOMMENDED_TEST_ENVIRONMENT: 127.0.0.1:8087 tras confirmar un único servidor P05 y preflight sintético

REAL_LLM_CONVERSATION: NOT_IMPLEMENTED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La auditoría se detiene sin realizar escrituras y sin modificar ningún entorno.

---

## Addendum de rectificación — 12 de octubre de 2026

### A. Corrección de la interpretación anterior

El propietario confirmó que `bd_clinica` es una copia local de producción, alojada en XAMPP, sin conexión activa ni sincronización con el servidor de producción.

La afirmación anterior:

```text
CLINICA_TEST_SHARED_WITH_PRODUCTION: VERIFIED
```

era ambigua y debe rectificarse. La evidencia no demuestra que exista una conexión activa a producción. Lo que sí demuestra es que el origen histórico de los datos es una copia de producción y que la aplicación local apunta a esa copia.

Distinción correcta:

- **Origen de los datos:** copia local derivada de producción; confirmado por la aclaración del propietario, no por una consulta de contenido clínico.
- **Conectividad actual:** local; verificada técnicamente.
- **Sincronización activa:** no observada en la configuración local ni en el estado de MySQL; no se afirma una prueba absoluta contra software externo no visible.
- **Riesgo para producción:** no se observó una ruta de escritura hacia un servidor de producción; persiste el riesgo de modificar la copia local compartida por el entorno de desarrollo.

### B. Evidencia de conectividad y replicación

Las operaciones de solo lectura ejecutadas el 12 de octubre fueron:

1. Bootstrap de la aplicación y `SELECT DATABASE()`: `bd_clinica`.
2. Clasificación no reveladora de `DB_HOST`: `LOCAL_LOOPBACK`; no se mostró su valor.
3. Variables MySQL:
   - host del servidor MySQL: `PC`;
   - puerto: `3306`;
   - `server_id=1`;
   - `log_bin=0`;
   - `read_only=0`;
   - `report_host=NULL`;
   - `SHOW SLAVE STATUS`: cero filas;
   - `SHOW REPLICA STATUS`: no disponible en esta versión/privilegios, por lo que no se usa como prueba positiva.
4. Listener observado: `C:\xampp\mysql\bin\mysqld.exe` en el equipo local.
5. `C:\xampp\mysql\bin\my.ini`: `log-bin` comentado, sin `master-host`, sin `relay-log` y sin directivas `replicate` activas.

Resultado: no se observan mecanismos de replicación MySQL activos ni una conexión remota configurada por el flujo de la entrevista. La configuración local permite escrituras en MySQL (`read_only=0`), pero eso describe la copia local y no autoriza una prueba.

`PRODUCTION_DATABASE_CONNECTION: NOT_OBSERVED`

`ACTIVE_REPLICATION: NOT_OBSERVED_IN_LOCAL_MYSQL`

`PRODUCTION_WRITE_RISK: NO EVIDENCE OF ACTIVE PRODUCTION CONNECTION; LOCAL COPY REMAINS SHARED DEVELOPMENT DATA`

También se buscaron destinos remotos, `curl`, webhooks, FTP/SFTP, `rsync`, sincronizadores y tareas programadas relacionadas con la entrevista. No se encontraron referencias aplicables. La búsqueda no puede descartar herramientas externas no declaradas fuera del proyecto o tareas administrativas no visibles.

### C. Expediente demo y límites de identificación

No fue posible acceder a la sesión del navegador del propietario: el controlador de ventanas no estuvo disponible. No se solicitaron credenciales, no se leyeron cookies/JWT y no se mostró información clínica.

Por ello el paciente de la sesión `clinica.test` sigue sin identificarse técnicamente. No se debe inferir que sea 9301/9302 sólo por la etiqueta “demo”.

`DEMO_PATIENT_IDENTIFIED: NOT_VERIFIED`

`DEMO_PATIENT_ID: NOT_VERIFIED`

El procedimiento mínimo seguro para identificarlo, sin revelar credenciales, es:

1. El propietario inicia sesión manualmente en `clinica.test`.
2. Desde la pantalla de entrevista o portal, copia sólo el identificador técnico mostrado en la URL o en el contexto de la página, sin copiar nombres, respuestas, tokens ni capturas clínicas.
3. El propietario entrega únicamente ese identificador técnico al auditor, o ejecuta localmente una consulta de conteo/relación sin mostrar valores clínicos.
4. Se verifica que el identificador pertenece a una clínica y a la cuenta autenticada mediante una consulta parametrizada de solo lectura.
5. Antes de cualquier escritura se conserva una instantánea de las filas M03 de ese único paciente.

Hasta completar esos pasos, el expediente no está autorizado para pruebas de guardado.

### D. Tablas y efectos de la entrevista

La estructura comprobada de solo lectura es:

- `pac_respuestas_paciente_modulo_3`: `id`, `id_pregunta`, `id_paciente`, `respuesta`.
- `pac_historia_clinica_comentario`: `id`, `id_modulo`, `id_paciente`, `fecha_hora`, `comentario`.
- `pac_historia_clinica_finalizar`: `id`, `id_modulo`, `id_paciente`, `fecha_hora`.

La captura de la entrevista independiente sólo delega al guardado M03. La tabla directamente afectable es `pac_respuestas_paciente_modulo_3`: puede actualizar la respuesta existente o crear la fila faltante sólo después de confirmación. Si se confirma `No` en la pregunta condicional, el modelo puede vaciar respuestas dependientes dentro de esa misma tabla. La entrevista no agrega comentarios ni finaliza el módulo.

La lectura independiente usa `LEFT JOIN` y no crea filas. El formulario tradicional M03 conserva el efecto secundario heredado de inicializar filas vacías en ciertos GET; ese efecto no debe ejecutarse contra la copia sin respaldo previo.

`DEMO_WRITE_SCOPE: ONLY pac_respuestas_paciente_modulo_3 FOR THE IDENTIFIED PATIENT; NOT_AUTHORIZED_YET`

### E. Procedimiento preparado de respaldo y restauración

Este procedimiento se preparó, pero no se ejecutó.

#### E.1 Precondiciones

- Autorización explícita del propietario para una escritura sintética/controlada.
- Identificador técnico del paciente confirmado y documentado fuera de este informe.
- Confirmación de `SELECT DATABASE() = bd_clinica` y de que la prueba sigue siendo local.
- Sesión única del propietario; sin otra edición concurrente del paciente.
- Gemini externo deshabilitado.
- Registro de hora, PID/servidor y base efectiva sin guardar secretos.

#### E.2 Instantánea previa

Guardar en una ubicación local protegida, fuera del repositorio y sin publicarla:

```sql
SELECT id, id_pregunta, id_paciente, respuesta
FROM pac_respuestas_paciente_modulo_3
WHERE id_paciente = :demo_patient_id
ORDER BY id;
```

Registrar además, sin contenido clínico en el informe, los identificadores de fila, cantidad de filas, hash local del archivo de respaldo y hora. No usar `TRUNCATE`, volcados globales ni consultas sin filtro por el paciente confirmado.

Como comprobación de relaciones, conservar los identificadores `id_pregunta` y confirmar que existen en `pac_preguntas_modulo_3`. No modificar el catálogo.

#### E.3 Prueba posterior autorizada

Realizar una sola captura explícitamente confirmada desde la interfaz. Antes y después comparar únicamente las filas del paciente identificado: filas nuevas, IDs existentes, respuesta modificada y conteo. No ejecutar la prueba si la acción `No` implicaría vaciar respuestas dependientes que no estén incluidas en la instantánea.

#### E.4 Restauración focal preparada

Si la restauración fuese autorizada, detener nuevas ediciones, verificar nuevamente la base local y ejecutar una transacción limitada al paciente confirmado:

1. Restaurar por `id` y `id_paciente` las filas que existían en la instantánea.
2. Eliminar sólo las filas nuevas creadas por la prueba para ese paciente, identificadas por la comparación de IDs y hora/ejecución controlada.
3. Comprobar que cada `id_pregunta` restaurado pertenece al catálogo original.
4. Confirmar dentro de la transacción el conteo y los valores contra la instantánea local.
5. Hacer `COMMIT` sólo con autorización; ante cualquier diferencia, `ROLLBACK` y detenerse.

No se debe restaurar toda la tabla, usar `TRUNCATE`, borrar por clínica completa ni sobrescribir filas ajenas al paciente confirmado. Si hubo concurrencia, no se debe intentar una restauración automática: se requiere revisión manual del respaldo.

`DEMO_BACKUP_PROCEDURE: PREPARED_NOT_EXECUTED`

`DEMO_RESTORE_PROCEDURE: PREPARED_NOT_EXECUTED; PATIENT_SCOPED_TRANSACTION_REQUIRED`

### F. Estado rectificado

```yaml
LOCAL_DATABASE: VERIFIED — bd_clinica, copia local de producción
LOCAL_DATABASE_HOST: VERIFIED — MySQL local en PC:3306; DB_HOST clasificado como loopback sin revelar el valor
PRODUCTION_DATABASE_CONNECTION: NOT_OBSERVED
ACTIVE_REPLICATION: NOT_OBSERVED_IN_LOCAL_MYSQL
PRODUCTION_WRITE_RISK: NO_EVIDENCE_OF_ACTIVE_PRODUCTION_CONNECTION; LOCAL_COPY_WRITE_RISK_REMAINS

DEMO_PATIENT_IDENTIFIED: NOT_VERIFIED
DEMO_PATIENT_ID: NOT_VERIFIED
DEMO_WRITE_SCOPE: NOT_AUTHORIZED; sólo pac_respuestas_paciente_modulo_3 del paciente aún no identificado
DEMO_BACKUP_PROCEDURE: PREPARED_NOT_EXECUTED
DEMO_RESTORE_PROCEDURE: PREPARED_NOT_EXECUTED

SAFE_FOR_CONTROLLED_DEMO_WRITES: NOT_VERIFIED
BLOCKING_RISKS: DEMO_PATIENT_ID_NOT_VERIFIED; NO_RESTORE_EXECUTED_OR_VALIDATED; TWO_PHP_LISTENERS_ON_8087; LEGACY_M03_GET_SIDE_EFFECT

LEGACY_MODULES_MODIFIED: NO
CHAT_MODIFIED: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La rectificación no autoriza todavía escrituras. Se detiene el trabajo antes de cualquier captura o restauración y queda a la espera de autorización explícita del propietario.
