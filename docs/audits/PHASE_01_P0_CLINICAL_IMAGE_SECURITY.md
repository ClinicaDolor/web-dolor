# PHASE-01-P0-CLINICAL-IMAGE-SECURITY

## Resultado ejecutivo

```text
PHASE_01_P0_CLINICAL_IMAGE_SECURITY:
- STATUS: PARTIAL
- PRODUCTION_READY: NO
```

El acceso directo local a archivos de mapas con nombre de paciente quedó
bloqueado por Apache y las lecturas autorizadas continúan usando el endpoint
PHP protegido. El estado histórico de la Fase 1B fue `PARTIAL` porque no se
verificó el servidor productivo, no se obtuvo render autenticado en navegador
del resumen D40 y el runner P11 tenía un fallo de su fixture de token cruzado
que devolvía HTTP 500 en vez de 403. PHASE-01C aisló y resolvió el problema del
runner sin modificar la autenticación de la aplicación.

## Alcance y precheck

- Proyecto: `C:\xampp\htdocs\clinica-dolor`.
- Rama: `Silvino`.
- HEAD al iniciar: `1b08d9b4c41080c176fbe4588212863f3a1ac18c`.
- Base autorizada para pruebas: `clinica_dolor_demo_20261010_final`.
- Base prohibida: `bd_clinica`.
- Pacientes sintéticos: `9301` en clínica `9101` y `9302` en clínica `9102`.
- El worktree ya contenía cambios locales y archivos sin seguimiento. No se
  ejecutaron `git clean`, `git reset --hard`, commits, push ni despliegues.
- No se abrieron, descargaron ni copiaron imágenes clínicas existentes. Las
  pruebas de acceso estático usaron únicamente archivos temporales creados
  con nombres sintéticos `9301` y eliminados en un bloque de limpieza.

## Vulnerabilidad inicial y causa raíz

La estructura heredada almacenaba mapas individuales dentro de:

```text
public/assets/images/evaluacion-dolor/frente/
public/assets/images/evaluacion-dolor/espalda/
```

El servidor Apache servía archivos existentes bajo `public/` y no había una
regla local que diferenciara los PNG anatómicos base de los PNG con sufijo de
paciente. Por tanto, una URL predecible podía alcanzar directamente un mapa
individual sin ejecutar `AuthMiddleware`.

Además, el resumen Doctor D40 construía URLs físicas con `RUTA_IMAGES` y
seleccionaba el archivo por sexo e ID. Esa referencia evitaba el endpoint
autorizado aunque el resto de P11 ya utilizara la ruta protegida.

## Inventario sin abrir imágenes

### Recursos anatómicos base

Se conservaron como recursos públicos los nombres base usados por el modelo:

```text
frente/Frente_F.png
frente/Frente_M.png
espalda/Espalda_F.png
espalda/Espalda_M.png
```

### Archivos individuales o heredados

El inventario estructural encontró nombres con sufijo numérico, entre ellos
`Frente_F_2.png`, `Frente_M_1.png`, `Frente_M_172.png`, `Frente_M_183.png`,
`Frente_M_310.png` y sus equivalentes de espalda. No se abrió ni se copió
ninguno de esos archivos. La regla del servidor bloquea el patrón numérico
individual, sin borrar, renombrar ni migrar los archivos existentes.

### Almacenamiento privado actual

El P11 ya dispone de almacenamiento privado en:

```text
app/storage/evaluacion-dolor/
```

La prueba sintética confirmó la presencia/restauración de
`frente_9301.png`. El acceso al archivo privado se resuelve únicamente desde
`EvaluacionDolorModel::p11ImagePath()` después de autorización.

## Rutas y referencias afectadas

| Recurso | Evidencia | Resultado |
|---|---|---|
| Endpoint protegido | `app/Config/routes.php:73`, `GET /paciente/imagen-evaluacion-dolor/{orientacion}/{idPaciente}` | Conservado |
| Controlador | `app/Controllers/ModulosController.php:937-956`, valida sesión, paciente, orientación y entrega `image/png` sin caché público | Reutilizado |
| Vista Paciente P11 | `app/views/historia-clinica-paciente/evaluacion-dolor.php:101` | Ya consumía endpoint protegido |
| Vista Doctor P11 | `app/views/historia-clinica-doctor/evaluacion-dolor.php:56-263` | Ya consumía endpoint protegido |
| Resumen D40 | `app/views/resumen-clinica-doctor/evaluacion-dolor.php:32-35` | Cambiado a endpoint protegido |
| Generadores legacy | `app/Models/EvaluacionDolorModel.php:426-590` | Permanecen documentados; no se encontraron llamadas de vista/controlador activas |

La búsqueda de referencias activas no encontró otra vista que construya una
URL pública individual. Los métodos legacy todavía conocen la ubicación
heredada; la protección Apache impide que sus nombres resulten descargables
directamente, pero deben retirarse o encapsularse en una fase posterior si se
vuelven a utilizar.

## Solución implementada

1. Se añadieron reglas `.htaccess` en `frente/` y `espalda/` que deniegan sólo
   los nombres `Frente_[FM]_<id>.png` y `Espalda_[FM]_<id>.png`.
2. Se conservaron los cuatro archivos anatómicos base públicos.
3. D40 ahora genera:

   ```text
   /clinica/paciente/imagen-evaluacion-dolor/frente/{idPaciente}
   /clinica/paciente/imagen-evaluacion-dolor/espalda/{idPaciente}
   ```

   La aplicación decide el mapa privado o el fallback anatómico después de
   `assertPatientAccess()`.
4. No se modificaron modelos, rutas, consultas, persistencia, colores,
   modales, cuestionarios ni lógica de escritura de mapas.

## Matriz de autorización y acceso

| Caso | Esperado | Evidencia ejecutada | Resultado |
|---|---:|---|---:|
| Archivo paciente directo Apache, frente | Rechazado | Fixture temporal `Frente_M_9301.png`, `/assets/...` | PASS, HTTP 403 |
| Archivo paciente directo Apache, espalda | Rechazado | Fixture temporal `Espalda_M_9301.png`, `/assets/...` | PASS, HTTP 403 |
| Variante `/public/assets/...` | Rechazado | Ambos fixtures sintéticos | PASS, HTTP 403 |
| Ruta física con `..` | Rechazado | Ruta normalizada a fixture | PASS, HTTP 403 |
| Traversal codificado | Rechazado | `%2F` en URL sintética | PASS, HTTP 404 |
| Base anatómica frente | Público | `Frente_M.png` | PASS, HTTP 200 |
| Base anatómica espalda | Público | `Espalda_M.png` | PASS, HTTP 200 |
| Paciente 9301, mapa propio | Autorizado | Sesión real sintética | PASS, HTTP 200 |
| Paciente 9301, mapa de 9302 | Rechazado | Sesión real sintética, otra clínica | PASS, HTTP 403 |
| Doctor clínica 9101, paciente 9301 | Autorizado | Login Doctor sintético | PASS, HTTP 200 |
| Doctor clínica 9101, paciente 9302 | Rechazado | Login Doctor sintético, otra clínica | PASS, HTTP 403 |
| Sin sesión | Rechazado | Endpoint PHP protegido | PASS, HTTP 302 |
| Directo sobre archivo inexistente | 404 | No se completó en esta ejecución | NO VERIFICADO |

## Persistencia y compatibilidad

El runner existente `tests/p11/http-crud.php` se ejecutó contra la base
allowlisted y confirmó PASS para login sintético, GET propio sin escrituras,
lecturas de frente/espalda, guardado, limpieza, persistencia de cuestionario,
finalización, idempotencia y limpieza de la línea base. El `preflight` final
confirmó:

```text
database: clinica_dolor_demo_20261010_final
patients: 9301/9101, 9302/9102
writes: false
p11_finalizations: 0
```

El mismo runner reportó FAIL en sus comprobaciones cruzadas porque fabrica el
JWT de la cuenta cruzada con una clave distinta de la clave efectiva del
servidor sintético; el servidor registra `SignatureInvalidException` y
responde HTTP 500. No se interpretó ese 500 como autorización correcta. La
prueba independiente con la sesión real del paciente 9301 y con el login real
del Doctor A sí confirmó 403 entre clínicas para el endpoint de imagen.

La limpieza sintética terminó en PASS y no se modificó `bd_clinica`. En la
validación PHASE-01B se ejecutó el GET del resumen D40 con Doctor sintético
sobre 9301: respondió HTTP 200, incluyó los endpoints protegidos de frente y
espalda, no incluyó nombres físicos individuales y no incrementó las filas de
evaluación en el fixture ya preparado. Esto no elimina el riesgo de código:
la vista todavía llama a `cuestionarioModulo9()` y ese método conserva un
`INSERT` condicional durante GET cuando falta la fila de preparación. Por
tanto, el efecto secundario queda validado sólo como ausencia de delta en el
fixture poblado, no como resuelto.

La compatibilidad HTML del resumen quedó comprobada en HTTP, pero el render
visual autenticado, la consola y el responsive permanecen NO VERIFICADOS por
el bloqueo del navegador descrito en PHASE-01B.

## Validación técnica

- `httpd.exe -t`: PASS (`Syntax OK`).
- PHP lint en controlador, modelo y vistas P11/D40: PASS.
- `tests/p11/contract-check.mjs`: PASS.
- `tests/p11/preflight.php --database=clinica_dolor_demo_20261010_final`: PASS.
- `tests/p11/direct-image-security.php --base-url=http://clinica.test --host=clinica.test`:
  PASS; prepara y elimina únicamente fixtures sintéticos y verifica las dos
  variantes de URL estática para frente/espalda.
- `git diff --check` sobre la vista modificada: PASS. Git sólo informó la
  advertencia histórica de conversión LF/CRLF.
- Capturas de navegador, consola y responsive: NO VERIFICADO en esta fase.

## Servidor local y producción

El Apache local usa `DocumentRoot C:/xampp/htdocs`, vhost `clinica.test`,
`AllowOverride All` y `mod_rewrite`/`mod_authz_core` activos. La prueba de
entrega estática real se realizó contra Apache, no contra el endpoint PHP.

No se verificó el servidor productivo ni su tecnología. Si producción usa
Nginx, `.htaccess` no se interpreta y debe replicarse la denegación mediante
configuración equivalente antes de desplegar. Por esa razón no se declara
resuelto el P0 para producción.

## Archivos modificados en esta fase

- `app/views/resumen-clinica-doctor/evaluacion-dolor.php`
- `public/assets/images/evaluacion-dolor/frente/.htaccess`
- `public/assets/images/evaluacion-dolor/espalda/.htaccess`
- `tests/p11/direct-image-security.php`
- `docs/audits/PHASE_01_P0_CLINICAL_IMAGE_SECURITY.md`

Los cambios locales previos en controladores, modelos, vistas P11, pruebas y
documentación fueron preservados; no se atribuyen a esta fase.

## Riesgos residuales

- El servidor productivo y su configuración equivalente aún no están
  verificados.
- El render autenticado del resumen D40 no fue ejecutado por su escritura
  heredada durante GET.
- Los métodos legacy de `EvaluacionDolorModel` aún contienen construcción de
  nombres públicos y deben retirarse o aislarse en una actividad independiente.
- El problema histórico del JWT cruzado del runner quedó resuelto en
  PHASE-01C al ejecutar cliente y servidor con la misma clave sintética; no se
  modificó la autenticación de la aplicación.
- ACT-05C-02, PDF, descargas y almacenamiento documental continúan abiertos.
- Persisten los riesgos históricos de escrituras durante GET y SQL heredado.

## Pasos de despliegue propuestos (no ejecutados)

1. Confirmar con infraestructura la configuración del servidor productivo.
2. Replicar la denegación de nombres individuales en Apache o Nginx según
   corresponda.
3. Verificar permisos de lectura del almacenamiento privado.
4. Ejecutar las pruebas sintéticas y las comprobaciones de rollback en un
   entorno de staging aislado.
5. Obtener aprobación independiente antes de cualquier despliegue.

## Reversión propuesta (no ejecutada)

En staging, respaldar los tres archivos intervenidos, restaurar la vista
anterior sólo si la autorización de seguridad equivalente ya está instalada,
y conservar los archivos clínicos sin borrar ni renombrar. Repetir la matriz
de acceso antes de considerar cualquier reversión. No se ejecutó ninguna
reversión en este trabajo.

## PHASE-01B — Validación final de privacidad de mapas clínicos

### Resultado ejecutivo

```text
PHASE_01B_FINAL_PRIVACY_VALIDATION:
- STATUS: PARTIAL
- PRODUCTION_READY: NO
- CODE_CHANGES: NO; únicamente se actualizó este informe.
```

La fase se ejecutó únicamente contra la base sintética allowlisted
`clinica_dolor_demo_20261010_final`. No se modificó `bd_clinica`, no se usaron
imágenes reales, no se hicieron commits, push ni despliegues, y no se inició
Phase 2.

### Matriz de resultados

| Área | Resultado | Evidencia / limitación |
|---|---|---|
| D40 HTTP autenticado con Doctor A sobre paciente sintético 9301 | PASS | `POST /clinica/login` HTTP 200; `GET /clinica/resumen/evaluacion-dolor/paciente/9301` HTTP 200. |
| D40 mapa frontal | PASS | HTML contiene `/clinica/paciente/imagen-evaluacion-dolor/frente/9301`; el endpoint propio respondió HTTP 200 en la línea base previa. |
| D40 mapa posterior | PASS | HTML contiene `/clinica/paciente/imagen-evaluacion-dolor/espalda/9301`; el endpoint propio respondió HTTP 200 en la línea base previa. |
| URLs físicas de mapas individuales en D40 | PASS | No aparecieron `Frente_M_9301.png` ni `Espalda_M_9301.png` en el HTML. |
| D40 GET sin nueva fila | PASS parcial | `preflight` antes/después: `evaluation_rows=1`, `p11_finalizations=0`, `writes=false`; sólo demuestra el fixture preparado. |
| D40 render visual, consola y responsive | NO VERIFICADO | El navegador IAB conservó una cookie `CLINICAJWT` sintética inválida; el acceso redirigió a “Solicitud no disponible”. No se borraron cookies ni se alteró el perfil. |
| Impresión/PDF D40 | NO VERIFICADO | No se ejecutó una operación documental. |
| Acceso propio de paciente | PASS | Sesión sintética 9301 sobre su mapa: HTTP 200. |
| Acceso cruzado paciente 9301 → 9302 | PASS | Sesión real sintética: HTTP 403. |
| Doctor autorizado 9101 → paciente 9301 | PASS | Login Doctor sintético: HTTP 200 para endpoint protegido. |
| Doctor no autorizado 9101 → paciente 9302 | PASS | Sesión Doctor A contra otra clínica: HTTP 403. |
| Usuario sin sesión | PASS | Endpoint protegido: HTTP 302. |
| Paciente B propio | NO VERIFICADO | El fixture de login disponible resuelve el PIN sintético a 9301; no se fabricó un token alterno ni se alteraron fixtures. |
| Aislamiento entre pacientes de la misma clínica | NO VERIFICADO | Los dos pacientes disponibles pertenecen a clínicas distintas (9101/9102); no existe pareja sintética de misma clínica en esta validación. |
| Bloqueo Apache de archivos individuales | PASS | `tests/p11/direct-image-security.php --base-url=http://clinica.test --host=clinica.test`: frente/espalda y `/public/assets` HTTP 403; base anatómica HTTP 200; limpieza PASS. |
| Traversal / rutas manipuladas | PASS previo | Rutas normalizadas y codificadas rechazadas en la prueba directa de Phase 01. |
| P11 regresión de persistencia y finalización | PASS | El fallo histórico de clave del harness fue reproducido y resuelto en PHASE-01C; la reejecución completa devolvió PASS y limpieza PASS. |
| P11 regresión visual y consola | NO VERIFICADO | Bloqueo del navegador; no se inventaron capturas. |
| Configuración productiva Plesk/Apache/Nginx | BLOCKED | No hay acceso de lectura autorizado al servidor productivo ni a su configuración. |

### Evidencia HTTP de D40

Comando ejecutado contra el servidor sintético aislado en `127.0.0.1:8114`:

```text
LOGIN_HTTP=200 RESULTADO=True TOKEN_PRESENT=True
D40_HTTP=200 HTML_HAS_IMG=True PROTECTED_FRONT=True PROTECTED_BACK=True
DIRECT_FRONT_LEAK=False DIRECT_BACK_LEAK=False
```

El precheck antes y después conservó `evaluation_rows: 1`,
`p11_finalizations: 0` y `writes: false`. La evidencia no autoriza a retirar
la advertencia sobre el `INSERT` condicional de `cuestionarioModulo9()` cuando
la fila no existe.

### Bloqueo del navegador

Se intentó un origen sintético aislado en el IAB y un servidor local adicional
con perfil de aplicación de pruebas. Ambos quedaron afectados por una cookie
`CLINICAJWT` inválida preexistente; incluso `/cerrar-sesion` intenta decodificar
esa cookie antes de limpiarla y termina en el error controlado “Solicitud no
disponible”. No se eliminaron cookies, no se cerró el navegador habitual y no
se modificaron perfiles personales. En consecuencia, capturas, consola,
dimensiones reales, print y responsive se mantienen NO VERIFICADOS.

### Producción

`PRODUCTION_SERVER_CONFIGURATION: BLOCKED`. No se accedió a Plesk ni se
intentó cambiar infraestructura. Antes de cualquier despliegue se debe obtener
acceso de sólo lectura, confirmar el DocumentRoot/vhost efectivo, comprobar que
las reglas de denegación se aplican también si producción usa Nginx y repetir
la matriz con datos sintéticos en staging. La protección local Apache no
demuestra por sí sola la configuración productiva.

### Riesgos abiertos

- `cuestionarioModulo9()` mantiene una escritura condicional durante GET si no
  existe la fila de preparación.
- El fallo histórico `SignatureInvalidException` del runner P11 quedó
  resuelto como problema de configuración del harness; no se modificó la
  aplicación.
- La pareja sintética de pacientes de la misma clínica y el acceso propio de
  paciente 9302 no están disponibles para una prueba independiente.
- Métodos legacy aún conocen nombres de archivos públicos individuales.
- ACT-05C-02 (PDF, descargas y almacenamiento documental) permanece abierto.
- La configuración productiva y cualquier equivalente Nginx permanecen sin
  verificar.

### Estado de cierre

```text
PHASE_01B:
- D40_HTTP_PROTECTED_MAPS: PASS
- D40_BROWSER_VISUAL: NO VERIFICADO
- SAME_CLINIC_ISOLATION: NO VERIFICADO
- CROSS_CLINIC_ISOLATION: PASS
- APACHE_STATIC_DENIAL: PASS
- P11_REGRESSION: PARTIAL
- PRODUCTION_SERVER_VERIFICATION: BLOCKED
- FINAL_STATUS: PARTIAL
- PRODUCTION_READY: NO
```

## PHASE-01C — Cierre de validación local de seguridad

### Resultado ejecutivo

```text
PHASE_01C_SECURITY_VALIDATION:
- LOCAL_P0_STATUS: PASS
- GLOBAL_PHASE_01_STATUS: PARTIAL
- PRODUCTION_SERVER_VERIFICATION: BLOCKED
- PRODUCTION_READY: NO
```

La validación local quedó completa para autorización HTTP y entrega estática:
se cubrieron dos pacientes sintéticos de la misma clínica, se corrigió el
parámetro del harness que provocaba la firma JWT inconsistente y se reejecutó
la regresión P11. No se modificó `bd_clinica`, no se usó el paciente 310 y no
se ejecutaron commits, push o despliegues.

### Fixture sintético de misma clínica

Se creó temporalmente en `clinica_dolor_demo_20261010_final` el siguiente
fixture aislado, con IDs reservados exclusivamente para esta fase:

| Recurso | ID | Propósito |
|---|---:|---|
| Clínica sintética PHASE-01C | 9111 | Ámbito común de autorización |
| Doctor sintético PHASE-01C | 9211 | Token Doctor autorizado |
| Paciente sintético A | 9311 | Mapa frontal/posterior A |
| Paciente sintético B | 9312 | Mapa frontal/posterior B |

Los pacientes pertenecieron a la misma clínica sintética y recibieron mapas
temporales distintos en `app/storage/evaluacion-dolor/` y bajo
`public/assets/images/evaluacion-dolor/`. Se verificó la base activa antes de
insertar; no existían esos IDs ni archivos. La limpieza `finally` eliminó
filas y archivos temporales y terminó en PASS. El preflight posterior volvió a
confirmar sólo los pacientes base 9301/9302 y `writes=false`.

### PHASE_01C_SECURITY_VALIDATION

| Control | Resultado | Evidencia |
|---|---|---|
| `SYNTHETIC_FIXTURE_ISOLATION` | PASS | Base allowlisted confirmada; pacientes 9311/9312 en clínica 9111; contenido sintético distinto. |
| `SAME_CLINIC_PATIENT_A_OWN_ACCESS` | PASS | HTTP 200; cuerpo igual al mapa sintético A. |
| `SAME_CLINIC_PATIENT_B_OWN_ACCESS` | PASS | HTTP 200; cuerpo igual al mapa sintético B. |
| `SAME_CLINIC_CROSS_PATIENT_DENIAL` | PASS | A→B y B→A HTTP 403; respuestas sin cuerpo de imagen del otro paciente. |
| `AUTHORIZED_DOCTOR_ACCESS` | PASS | Doctor sintético del ámbito 9111 consultó A y B: HTTP 200/200. |
| `ANONYMOUS_DENIAL` | PASS | Sin cookie: HTTP 302 y sin contenido clínico. |
| `APACHE_STATIC_DENIAL` | PASS | PNG sintéticos individuales: HTTP 403; limpieza PASS. |
| `JWT_HTTP_500_ROOT_CAUSE` | PASS — defecto del harness | El runner firmaba el JWT cruzado con una clave distinta a la del servidor; al pasar la misma `--app-key`, la regresión completa dejó de producir 500 y devolvió 403. No se modificó autenticación productiva. |
| `P11_REGRESSION` | PASS | `tests/p11/http-crud.php` con clave consistente: todas las comprobaciones HTTP, autorización cruzada, persistencia, finalización e idempotencia PASS; limpieza PASS. |
| `D40_AUTHENTICATED_BROWSER` | NO VERIFICADO | Chrome no estuvo disponible en CUA; el IAB conservó cookie sintética inválida incluso en un origen nuevo. |
| `D40_FRONT_MAP` | PASS — HTTP | D40 HTTP 200 incluyó endpoint protegido frontal; no filtró nombre físico. |
| `D40_BACK_MAP` | PASS — HTTP | D40 HTTP 200 incluyó endpoint protegido posterior; no filtró nombre físico. |
| `D40_CONSOLE` | NO VERIFICADO | Sin sesión de navegador reproducible. |
| `D40_PRINT` | NO VERIFICADO | No se ejecutó impresión/PDF. |
| `GET_WITHOUT_WRITES` | PASS parcial | D40 sintético: `ROW_DELTA=0`, `FINAL_DELTA=0`; persiste el riesgo del `INSERT` condicional de `cuestionarioModulo9()` cuando falta la fila. |
| `FIXTURE_CLEANUP` | PASS | IDs, mapas temporales y listeners de prueba eliminados; preflight limpio. |
| `PRODUCTION_SERVER_VERIFICATION` | BLOCKED | Sin acceso Plesk/servidor productivo; no se reiniciaron servicios ni se modificó infraestructura. |

### Evidencia adicional de regresión D40

```text
LOGIN_HTTP=200
D40_HTTP=200
FRONT_ENDPOINT=True
BACK_ENDPOINT=True
DIRECT_NAMES_LEAK=False
ROW_DELTA=0
FINAL_DELTA=0
```

Esto confirma el contrato HTTP y la ausencia de escritura en el fixture
preparado; no sustituye una inspección de consola, Network, proporciones o
render visual en navegador.

### Validación técnica

- PHP lint de `tests/p11/phase01c-same-clinic.php`, controlador, modelo y
  vista D40: PASS.
- `node tests/p11/contract-check.mjs`: PASS.
- `tests/p11/direct-image-security.php`: PASS; 403 para individuales y 200
  para bases anatómicas públicas.
- `tests/p11/phase01c-same-clinic.php`: PASS; limpieza PASS.
- `tests/p11/http-crud.php` con `--app-key` coincidente: PASS completo.
- `git diff --check` global: FAIL histórico en
  `app/Controllers/ModulosController.php:996`; no pertenece a esta fase y no
  fue corregido.
- No se declaró PASS visual ni se generaron capturas sin navegador válido.

### Harness agregado

Se agregó el harness reproducible:

```text
tests/p11/phase01c-same-clinic.php
```

Su alcance está limitado a la base allowlisted, IDs temporales fail-closed,
mapas sintéticos y limpieza automática. No introduce bypass de autenticación,
rutas productivas ni cambios de aplicación.

### Estado final de la fase

```text
PHASE_01C:
- SYNTHETIC_FIXTURE_ISOLATION: PASS
- SAME_CLINIC_AUTHORIZATION: PASS
- APACHE_STATIC_DENIAL: PASS
- JWT_HARNESS: PASS
- P11_REGRESSION: PASS
- D40_HTTP: PASS
- D40_BROWSER: NO VERIFICADO
- GET_WITHOUT_WRITES: PASS parcial; riesgo legacy abierto
- FIXTURE_CLEANUP: PASS
- PRODUCTION_SERVER_VERIFICATION: BLOCKED
- LOCAL_P0_STATUS: PASS
- GLOBAL_PHASE_01_STATUS: PARTIAL
- PRODUCTION_READY: NO
```

## Formato final

```text
PHASE_01_P0_CLINICAL_IMAGE_SECURITY:
- STATUS: PARTIAL
- ROOT_CAUSE: archivos individuales heredados bajo public/ y D40 con URLs físicas.
- SECURITY_FIX: deny Apache por patrón de paciente + endpoint PHP autorizado.
- LEGACY_PUBLIC_ACCESS: PASS local Apache; métodos legacy quedan como riesgo residual.
- AUTHORIZED_PATIENT_ACCESS: PASS para paciente sintético propio.
- CROSS_PATIENT_DENIAL: PASS con sesión real 9301 contra 9302.
- CROSS_CLINIC_DENIAL: PASS con Doctor A contra 9302 y fixture de misma clínica; fallo histórico del harness resuelto.
- DOCTOR_ACCESS: PASS para Doctor A sobre 9301.
- SUMMARY_COMPATIBILITY: PARTIAL; D40 HTTP 200 con endpoints protegidos, render visual de navegador NO VERIFICADO.
- MAP_PERSISTENCE: PASS en la línea base sintética; limpieza PASS.
- PRODUCTION_SERVER_VERIFICATION: BLOCKED.
- TEST_RESULTS: PASS técnico y de acceso local; navegador y producción pendientes.
- MODIFIED_FILES: vista D40, dos reglas .htaccess, este informe.
- REMAINING_RISKS: producción/Nginx, navegador D40, métodos legacy, D40 GET-write y ACT-05C-02.
- PRODUCTION_READY: NO
```
