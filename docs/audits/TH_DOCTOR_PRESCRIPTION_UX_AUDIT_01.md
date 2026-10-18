# TH-DOCTOR-PRESCRIPTION-UX-AUDIT-01

## Auditoría focalizada de diseño, UX, accesibilidad y seguridad estática

**Proyecto:** Clínica del Dolor  
**Área:** Doctor  
**Fecha:** 2026-10-15  
**Alcance:** módulo de recetas médicas internas del paciente  
**Modalidad:** inspección estática; no se ejecutaron HTTP, navegador, SQL ni escrituras

## 1. Alcance y separación de módulos

Esta auditoría se limita a las recetas médicas internas asociadas al expediente del paciente. No se mezclan con:

- recetas externas (`/receta-externo/...` y sus endpoints propios);
- registros COFEPRIS (`/pdf/cofepris/...` y vistas/endpoints propios);
- laboratorio, notas subsecuentes, PIN u otros módulos del expediente.

La comparación con Laboratorio se utiliza únicamente para evaluar patrones de composición, tarjetas, estados vacíos y modales.

No se modificó código de aplicación, base de datos, configuración, rutas, modelos, controladores ni assets. El único archivo creado por esta actividad es este informe.

## 2. Identificación real

| Elemento | Identificación verificada |
|---|---|
| Ruta objetivo | `GET /clinica/receta/paciente/{idPaciente}` |
| Vista | `app/views/clinica/pacientes-recetas.php` |
| Controlador de pantalla | `ClinicaController::pacienteReceta()` |
| Alta de receta | `POST /clinica/paciente/insert-receta` → `ClinicaController::pacienteInsertReceta()` |
| Modelo principal | `RecetaModel` |
| Persistencia del alta | `ClinicaModel::insertPacienteReceta()` |
| Tabla clínica | `receta_medica` |
| Listado AJAX | `GET /buscar/tabla-recetas/{idPaciente}` → `BusquedasController::tableRecetas()` |
| Detalle AJAX | `GET /buscar/receta/{idReceta}` → `BusquedasController::buscarReceta()` |
| Detalle dedicado | `GET /receta/{idReceta}` → `RecetaController::receta()` |
| PDF | `POST /pdf/receta/{idReceta}` → `PdfController::pdfReceta()` |
| Modal documental | `app/views/components/modal-impresion-receta.php` |
| CSS scoped existente | bloque `doctor-patient-recipes-page` en `public/assets/css/app.css` |
| JavaScript | inline en la vista, más `loader.js`, `app.js`, `main.js`, `search-main.js`, Quill, Feather y Simple-DataTables |

La ruta de pantalla autentica el área clínica y llama a `assertPatientAccess()`. El endpoint de alta vuelve a autenticar y valida el paciente indicado en el JSON mediante el mismo mecanismo. Los endpoints de tabla, detalle y PDF también tienen comprobaciones estáticas de acceso al paciente o al recurso derivado.

## 3. Composición actual

### Encabezado y paciente

- Shell Doctor con sidebar, topbar/buscador y footer Doctor.
- Hero institucional con eyebrow `Seguimiento farmacológico`, título `Recetas`, descripción contextual y acciones `Volver al expediente` y `toggleSize()`.
- La vista ya conserva el estándar del botón de distribución: icono `columns`, tamaño compacto, `aria-label` y `title`.
- Después del hero existe una tarjeta independiente con nombre, fecha de alta, fecha de nacimiento, edad y sexo.
- La identidad del paciente aparece en la descripción del hero y nuevamente en la tarjeta clínica; la duplicidad es contextual, pero puede compactarse si el propietario lo aprueba.

### Listado

- Área izquierda de una composición Bootstrap aproximada 5/12.
- Tarjeta `Recetas` con tabla de dos columnas: número y fecha/hora.
- La tabla inicial usa `#table1`; la respuesta AJAX usa `#tableRecetas`.
- Las filas se activan mediante `onclick="DetalleReceta(...)"`, sin semántica de botón/enlace, `tabindex` ni estado de foco propio.
- El listado no presenta en su encabezado un contador ni un estado vacío institucional equivalente al directorio de pacientes.

### Detalle y alta

- Área derecha aproximada 7/12.
- Panel fijo `Detalle de la Receta`, inicialmente cargado con la última receta.
- Tarjeta `Nueva Receta` debajo del detalle, con diagnóstico, TA, FC, SpO2, temperatura y editor Quill de medicamento.
- El alta se realiza desde la tarjeta, no desde un modal.
- La vista permite abrir el modal documental de impresión/PDF después de guardar.

## 4. Comparación visual con Laboratorio y patrones Doctor

### Hallazgos positivos

- El hero y las tarjetas usan variables institucionales (`--clinic-*`) y selectores limitados al body de la pantalla.
- Las tarjetas tienen altura natural en CSS y no se detectó una regla global nueva que afecte otras pantallas.
- El toggle es consistente con el estándar aprobado.
- Los botones principales respetan una altura táctil aproximada de 44 px.
- El bloque de Laboratorio ofrece un patrón más compacto: contexto de paciente, tarjeta de ancho completo, encabezado con contador/acción y estados vacíos explícitos.
- El directorio `/clinica/pacientes` utiliza el patrón tipográfico `dashboard-eyebrow` + título + texto auxiliar + contador. El token actual es morado institucional, `font-size: .7rem`, `font-weight: 800`, `letter-spacing: .18em` y mayúsculas.

### Diferencias o deuda visual en Recetas

1. La composición 5/7 deja el listado estrecho y hace que detalle y alta compitan por altura en el lado derecho.
2. El listado no sigue todavía el patrón de tarjeta de ancho completo con contador y estado vacío de Laboratorio/Pacientes.
3. La inicialización AJAX usa `fixedHeight: true`, aunque la instrucción de la tabla también contiene `searchable: true` y después `searchable: false`; el último valor efectivo es `false`. Esto hace ambiguo el contrato visual y puede generar scroll o comportamiento inesperado.
4. Después de guardar se reinicializa Simple-DataTables sin una referencia de destrucción explícita; existe riesgo de duplicar wrappers/estado en repetidos guardados.
5. El detalle y la alta se muestran simultáneamente como tarjetas permanentes; un modal de detalle y un modal de alta permitirían una lista más clara, pero requieren preservar cuidadosamente el contrato actual.
6. Se encontraron etiquetas no estándar `<smallal>` alrededor de `Diagnostico` y `Medicamento`; no es un elemento semántico válido para una etiqueta de formulario.
7. El documento HTML de la vista de Recetas declara `lang="en"`, mientras el módulo es español; esto afecta lectores de pantalla y localización.

## 5. Formulario de creación

### Campos identificados

| Campo | Selector/contrato | Tipo | Observación |
|---|---|---|---|
| Diagnóstico | `#Diagnostico` | `textarea` | Validación cliente de no vacío |
| Medicamento | editor `#snow`, contenido `.ql-editor` | Quill/HTML | Validación cliente contra editor vacío |
| Tensión arterial | `#TA` | texto | Opcional en la vista; validación de dominio no visible |
| Frecuencia cardiaca | `#FC` | número | Sin validación de rango visible en la vista |
| Saturación | `#SpO2` | número | Sin validación de rango visible en la vista |
| Temperatura | `#Temperatura` | número decimal | `step=".1"`; sin rango visible en la vista |
| Paciente | `idPaciente` | dato de ruta/JSON | Se verifica en servidor mediante `assertPatientAccess()` |

El endpoint interno existente es reutilizable desde esta pantalla porque el paciente ya está contextualizado por la ruta. Su complejidad es **media**: diagnóstico, cuatro signos/vitales y un editor enriquecido, además de la posterior opción de impresión.

### Viabilidad de modal de alta

**FEASIBLE_STATIC — requiere decisión de propietario.** Un modal grande, con foco inicial, cierre por Escape, gestión de cambios no guardados, contenido desplazable y disposición de una columna en móvil puede contener el formulario. La opción de pantalla dedicada sería más robusta si el texto del medicamento o el diagnóstico son extensos.

No se recomienda implementar el modal dentro de esta auditoría porque debe resolverse antes:

- bloqueo contra doble clic y reintentos;
- estado de guardado y errores de red;
- preservación del flujo de impresión;
- validación de campos en servidor;
- compatibilidad con Quill y restauración de foco;
- protección CSRF.

## 6. Presentación de detalle

### Campos identificados

El detalle actual expone fecha, hora, diagnóstico, medicamento y los cuatro valores clínicos: TA, FC, SpO2 y temperatura. También contiene el botón de impresión que abre el modal de formato.

La respuesta de `RecetaModel::getReceta()` se inserta mediante `innerHTML` en `#detalleReceta`. En la ruta de vista dedicada `app/views/clinica/receta.php` sí se observan valores escapados, pero el camino AJAX construye HTML desde el modelo y no muestra escape/sanitización equivalente para diagnóstico y medicamento.

### Viabilidad de modal de detalle

**FEASIBLE_STATIC — recomendado con condiciones.** El endpoint AJAX y el contrato de selección ya existen. Un modal de solo lectura podría sustituir al panel fijo y conservar la impresión. Debe incluir carga, error, foco, Escape, respuesta obsoleta y tratamiento seguro del HTML del editor. El modal de impresión debe permanecer como segundo paso documental, sin superponer estados ni modificar la receta.

## 7. PDF, descarga e impresión

- `imprimirReceta(id, 'registrado')` almacena el identificador/tipo en `#modalImprimirReceta` y abre el modal Bootstrap.
- El usuario elige datos simples o formato completo.
- `generarPDF()` crea un formulario temporal `POST` a `/pdf/receta/{idReceta}`, incluye `con_formato` y abre la respuesta en `_blank`.
- `PdfController::pdfReceta()` autentica y valida el acceso al paciente derivado de la receta antes de generar el PDF TCPDF.
- El flujo observado es generación/visualización inline en nueva pestaña; no se verificó un flujo separado de descarga para esta pantalla.
- El formato institucional y los campos legales del PDF deben conservarse en cualquier rediseño.
- No se observó token CSRF en el formulario temporal de PDF; la existencia de una protección global no fue verificada.
- El botón de cierre del modal documental no muestra un `aria-label` explícito en el componente revisado.

## 8. Revisión estática de seguridad e integridad

### Autorización del paciente y clínica

**CONFIRMED_STATIC, NOT_RUNTIME_VERIFIED.** `AuthMiddleware::assertPatientAccess()` obtiene el paciente y su clínica desde la base mediante consulta preparada y permite el acceso del propio paciente o de la clínica correspondiente. La pantalla, el alta, el listado, el detalle y el PDF llaman a esta comprobación de forma estáticamente rastreable.

Esto no sustituye una prueba cruzada con dos pacientes sintéticos: no se ejecutó HTTP ni base de datos en esta actividad.

### CSRF

**POTENTIAL / NOT_VERIFIED.** En la vista y controlador del alta interna no se observó token CSRF ni una validación CSRF explícita. Tampoco se observó token en el formulario temporal que genera el PDF. No se inspeccionó un guard global suficiente para declarar protección existente. La decisión debe tratarse como hallazgo de seguridad antes de implementar cambios UX.

### Validación de servidor

**PARTIAL_STATIC.** El controlador valida método POST, JSON y acceso al paciente, pero no se observó una validación explícita y centralizada de obligatoriedad, longitud, tipos, rangos de signos vitales o sanitización del contenido Quill antes de delegar al modelo. Las validaciones visibles en la vista son sólo de diagnóstico y editor no vacío.

### SQL

- Las consultas principales de acceso, detalle y listado usan consultas preparadas o valores validados en el flujo revisado.
- `RecetaModel::idRecetaReferencia()` concatena identificadores en una cadena SQL aunque utiliza `prepare()` sin parámetros enlazados; es un hallazgo del modelo y debe corregirse en una actividad de seguridad separada si se confirma su alcance.
- `mostrarTablaRecetas()` construye su consulta con el `idPaciente` recibido; el controlador protege el flujo antes de invocarlo, pero la defensa no está encapsulada en el modelo.

### Escape HTML / XSS almacenado

**POTENTIAL HIGH.** El detalle AJAX se entrega como HTML y se inserta con `innerHTML`; el método `getReceta()` muestra diagnóstico y medicamento sin un escape/sanitización equivalente al de la vista dedicada. El medicamento proviene de Quill y puede contener HTML legítimo, por lo que se necesita una política explícita de sanitización/allowlist antes de moverlo a un modal.

### Reintentos y duplicación de escritura

**POTENTIAL MEDIUM/HIGH.** `AgregarReceta()` no deshabilita el botón ni muestra una compuerta de envío en curso, no usa una clave idempotente visible y no tiene un `catch` de red. Dos clics o reintentos pueden producir altas repetidas antes de que la interfaz se actualice. La tabla se vuelve a inicializar después del guardado.

### Manipulación de identificadores

**PARTIAL_STATIC.** El `idPaciente` de la ruta/JSON no debe considerarse confiable por sí mismo; el controlador sí lo pasa por `assertPatientAccess()`. El detalle y PDF derivan el paciente desde la receta antes de autorizar. Falta una prueba runtime de ID manipulado y de respuesta sin filtración.

## 9. Accesibilidad y responsive estático

### Accesibilidad

- Las filas de receta dependen de `onclick` y no tienen `tabindex`, rol de acción, Enter/Space ni estilo de foco propio.
- El enlace/icono de impresión construido por el modelo es icon-only y no muestra nombre accesible verificable.
- El botón de cierre del modal de impresión no tiene `aria-label` explícito.
- `#mensaje` tiene `role="alert"` en la vista, pero las respuestas de detalle/listado no muestran una región de estado equivalente.
- Las etiquetas con `<smallal>` no son semánticas y reducen la relación confiable entre nombre de campo y control.
- El Quill requiere una etiqueta accesible correctamente asociada; el texto visible actual está dentro del elemento no estándar.
- La vista de Recetas declara idioma `en` en lugar de `es-MX`.

### Responsive

**STATIC REVIEW ONLY.** El grid Bootstrap apila las columnas en anchos estrechos y el CSS reacomoda acciones, paddings y botones. Existen reglas específicas para 375/430 implícitamente mediante el breakpoint de 767 px, pero no hay evidencia de captura real.

Riesgos a comprobar en navegador:

- altura fija de Simple-DataTables (`fixedHeight: true`);
- ancho del editor Quill y textos largos;
- tabla inicial `#table1` frente a tabla AJAX `#tableRecetas`;
- interacción táctil con filas sin semántica de acción;
- apilado del panel de detalle y la tarjeta de alta;
- modal de impresión dentro de una pantalla ya densa.

## 10. Hallazgos priorizados

| Prioridad | ID | Hallazgo | Evidencia estática | Acción recomendada |
|---|---|---|---|---|
| P1 | `REC-SEC-01` | CSRF no observado en alta ni PDF | JSON POST y formulario temporal sin token/guardia visible | Resolver en trabajo de seguridad antes de exponer nuevos modales |
| P1 | `REC-SEC-02` | HTML de diagnóstico/medicamento llega a `innerHTML` sin sanitización visible | `RecetaModel::getReceta()` + `DetalleReceta()` | Definir allowlist/escape y prueba XSS sintética |
| P1 | `REC-FUNC-01` | Alta susceptible a doble clic/reintento | botón no bloqueado, sin idempotencia/catch | Añadir estado de envío y contrato idempotente antes de UI modal |
| P2 | `REC-UX-01` | Listado estrecho junto a dos tarjetas derechas | grid 5/7, detalle y alta simultáneos | Evaluar listado ancho completo; detalle/alta en modales |
| P2 | `REC-UX-02` | Tabla con configuración contradictoria y altura fija | `searchable` duplicado y `fixedHeight: true` | Unificar configuración, destruir/recrear instancia de forma controlada |
| P2 | `REC-A11Y-01` | Filas y acciones no son plenamente operables por teclado | `onclick` sin semántica/foco | Usar controles semánticos o contrato de teclado explícito |
| P3 | `REC-A11Y-02` | Etiquetas no estándar e idioma incorrecto | `<smallal>` y `lang="en"` | Corrección focalizada de marcado, sin alterar datos |
| P3 | `REC-UX-03` | Falta contador/estado vacío homogéneo | no hay patrón de directorio en la tarjeta Recetas | Adoptar patrón de Laboratorio/Pacientes |

Los riesgos anteriores son de revisión estática. No se declara explotación HTTP ni impacto en registros reales.

## 11. Viabilidad y plan mínimo de implementación posterior

1. **Contrato y seguridad primero:** decidir CSRF, sanitización de Quill, validación de servidor, bloqueo de doble envío y pruebas cruzadas. No implementar sólo el aspecto visual si estos contratos siguen indefinidos.
2. **Lista:** mantener los endpoints actuales, llevar la tabla a una tarjeta de ancho completo, añadir contador y estado vacío, y resolver la configuración de Simple-DataTables sin `fixedHeight` artificial.
3. **Encabezado:** compactar el contexto del paciente siguiendo el patrón de Laboratorio/PIN, sin eliminar información clínica necesaria.
4. **Alta:** utilizar modal grande y desplazable sólo si se aprueba el comportamiento de cambios no guardados, foco, móvil, error/reintento y post-alta/PDF. En móvil debe conservarse una ruta cómoda de una columna.
5. **Detalle:** utilizar modal de solo lectura si se define el tratamiento seguro del HTML, manteniendo fecha, hora, diagnóstico, medicamento, signos vitales e impresión.
6. **Validación:** ejecutar con datos sintéticos aislados los casos propio/cruzado, alta, recuperación, doble clic, error de red, PDF, Escape/foco y los viewports solicitados.

## 12. Archivos necesarios para una implementación posterior

Sólo después de aprobación y pruebas de seguridad:

- `app/views/clinica/pacientes-recetas.php`
- `public/assets/css/app.css` con CSS scoped a `doctor-patient-recipes-page`
- `app/Models/RecetaModel.php` para escape/sanitización y consultas si se aprueba la corrección
- `app/Controllers/ClinicaController.php`, `BusquedasController.php` y `PdfController.php` para validación/CSRF, si la revisión de seguridad lo requiere
- `app/views/components/modal-impresion-receta.php` para accesibilidad del modal documental
- pruebas sintéticas focalizadas y documentación del cambio

Estos archivos no fueron modificados en esta auditoría.

## 13. Decisiones requeridas del propietario

1. Confirmar que el alcance visual es únicamente receta médica interna.
2. Autorizar o separar la remediación CSRF, sanitización HTML y validación de servidor.
3. Elegir modal de alta, pantalla dedicada o conservar la tarjeta para formularios extensos.
4. Confirmar si después de guardar debe continuar la apertura automática de impresión/PDF.
5. Confirmar qué contexto del paciente debe permanecer visible en el encabezado compacto.
6. Confirmar si el listado debe mostrar sólo fecha/hora o también diagnóstico/estado.
7. Confirmar que el formato legal del PDF institucional no se cambia.

## 14. Validaciones ejecutadas

- PHP lint: PASS en vista objetivo, controladores, modelos, componente modal y vistas de comparación revisadas.
- Sintaxis JS externa (`loader.js`, `app.js`, `main.js`, `search-main.js`): PASS.
- Integridad sintáctica CSS básica: PASS; llaves balanceadas 3907/3907.
- `git diff --check`: PASS, con advertencias históricas de conversión LF/CRLF y sin errores de whitespace reportados.
- Inspección de contratos HTML/JS y rutas: PASS estático; no equivale a ejecución.
- Browser/DevTools/Network: NOT_EXECUTED; Codex no tiene una sesión local reproducible contra XAMPP.
- HTTP, BD, PDF real, autorización cruzada y persistencia: NOT_EXECUTED.

## 15. Resultado formal

```yaml
TH_DOCTOR_PRESCRIPTION_UX_AUDIT_01:
  STATUS: PARTIAL_STATIC_AUDIT_COMPLETED_OWNER_REVIEW_REQUIRED
  TARGET_ROUTE: GET /clinica/receta/paciente/{idPaciente}
  MAIN_VIEW_IDENTIFIED: PASS app/views/clinica/pacientes-recetas.php
  CONTROLLER_IDENTIFIED: PASS ClinicaController::pacienteReceta
  MODEL_IDENTIFIED: PASS RecetaModel plus ClinicaModel::insertPacienteReceta
  ROUTES_IDENTIFIED: PASS screen/create/table/detail/dedicated-detail/pdf
  CURRENT_LAYOUT: PASS hero + patient card + 5/7 list/detail/create workspace
  CURRENT_PATIENT_HEADER: PASS hero contextual text plus separate patient information card
  CURRENT_PRESCRIPTION_LIST: PASS two-column list with AJAX replacement and Simple-DataTables
  CURRENT_CREATE_FORM: PASS fixed card with diagnosis/vitals/Quill medication
  CURRENT_DETAIL_PRESENTATION: PASS fixed panel plus dedicated detail route
  LABORATORY_VISUAL_COMPARISON: COMPLETE_STATIC
  INSTITUTIONAL_TYPOGRAPHY: CONFIRMED dashboard-eyebrow pattern available
  REDUNDANT_HEADINGS_FOUND: POTENTIAL contextual repetition; no text removed
  FULL_WIDTH_CARD_FEASIBLE: YES_STATIC
  COMPACT_PATIENT_HEADER_FEASIBLE: YES_STATIC_WITH_OWNER_APPROVAL
  CREATE_MODAL_FEASIBLE: YES_STATIC_WITH_CONTRACT_SECURITY_WORK
  CREATE_FORM_FIELDS_IDENTIFIED: PASS diagnosis, medication, TA, FC, SpO2, temperature, patient context
  EXISTING_ENDPOINT_REUSABLE: YES_WITH_SECURITY_GATES
  PRESCRIPTION_FORM_COMPLEXITY: MEDIUM
  CREATE_MODAL_RECOMMENDATION: RECOMMENDED_AFTER_CSRF_VALIDATION_AND_SUBMIT_LOCK
  DETAIL_MODAL_FEASIBLE: YES_STATIC_WITH_SAFE_HTML_AND_PRINT_COORDINATION
  DETAIL_FIELDS_IDENTIFIED: PASS date, time, diagnosis, medication, TA, FC, SpO2, temperature
  DETAIL_MODAL_RECOMMENDATION: RECOMMENDED_AFTER_SANITIZATION_AND_FOCUS_PLAN
  PDF_GENERATION_FLOW: CONFIRMED_STATIC_POST_TO_PdfController
  PDF_DOWNLOAD_FLOW: INLINE_NEW_TAB_OUTPUT; SEPARATE_DOWNLOAD_NOT_VERIFIED_FOR_TARGET
  PRINT_FLOW: CONFIRMED_STATIC_MODAL_FORMAT_SELECTION
  PRESCRIPTION_INTEGRITY_RISKS: P1 CSRF/XSS/double-submit; P2 validation/idempotency/table-reinit
  CSRF_STATUS: POTENTIAL_NOT_VERIFIED_GLOBAL_GUARD
  PATIENT_AUTHORIZATION: CONFIRMED_STATIC_NOT_RUNTIME_VERIFIED
  CLINIC_ISOLATION: CONFIRMED_STATIC_NOT_RUNTIME_VERIFIED
  RESOURCE_AUTHORIZATION: CONFIRMED_STATIC_NOT_RUNTIME_VERIFIED
  SERVER_SIDE_VALIDATION: PARTIAL_STATIC
  SQL_AND_HTML_REVIEW: PARTIAL; prepared main reads, secondary concatenation/raw HTML findings
  ACCESSIBILITY_FINDINGS: PENDING_REMEDIATION rows, labels, modal close, language
  RESPONSIVE_STATIC_REVIEW: PARTIAL; browser evidence absent
  BROWSER_VALIDATION: NOT_EXECUTED
  PRIORITIZED_FINDINGS: REC-SEC-01, REC-SEC-02, REC-FUNC-01, REC-UX-01, REC-UX-02, REC-A11Y-01
  MINIMAL_IMPLEMENTATION_PLAN: security/contracts, full-width list, compact header, optional create/detail modals, synthetic validation
  FILES_REQUIRED_FOR_IMPLEMENTATION: view, scoped CSS, model/controllers/modal component only if approved
  OWNER_DECISIONS_REQUIRED: scope, modal choice, CSRF/XSS remediation, PDF behavior, patient context, list fields
  DOCUMENT_CREATED: PASS docs/audits/TH_DOCTOR_PRESCRIPTION_UX_AUDIT_01.md
  APPLICATION_CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  PRESCRIPTIONS_CREATED: NO
  PDF_TEMPLATES_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  READY_FOR_OWNER_REVIEW: YES
  NEXT_RECOMMENDED_ACTION: Owner review and explicit authorization for a separate implementation/security phase
```

> Nota: `CREATE_MODAL_RECOMMENDATION` contiene la decisión textual `CSRF`; debe leerse como “validación CSRF”. No se ejecutaron pruebas de explotación ni se generaron recetas.

