# 19 — Auditoría de seguridad de rutas V1.2

## Estado

'PARTIAL_STATIC / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY'

La auditoría concilia **136 declaraciones textuales** con **163 rutas efectivas**. La diferencia corresponde a la clausura común registrada para los grupos /clinica y /historia-clinica: no se inventan rutas, se explicita cada expansión efectiva. Las **163/163** filas tienen documentación estática completa en 'route_security_matrix_v1_2.csv'; esto no significa que la autorización de cada ruta esté verificada.

## Método y alcance

- Evidencia leída: 'app/Config/routes.php', controladores, 'AuthMiddleware', 'ClinicalSummarySecurity' y fuentes ya reconciliadas por V1.1.
- No se ejecutaron MySQL/PDO, HTTP, navegador, Docker, POST ni escrituras.
- Se clasificó autenticación, identidad, alcance clínico, ownership, CSRF, entrada, SQL, GET side effects, PDF/descargas y manejo de errores con estados explícitos.
- 'YES_STATIC', 'PARTIAL_STATIC', 'MISSING_STATIC' y 'UNVERIFIED' son estados de evidencia, no resultados de explotación.

## Conteos reconciliados

| Control | Resultado |
|---|---:|
| Declaraciones textuales conciliadas | 136 |
| Rutas efectivas | 163 |
| Filas documentadas en matriz | 163/163 |
| GET / POST | 85 / 78 |
| Autenticación requerida estáticamente | 105 |
| Registro público (web y login) | 26 |
| Autenticación no resuelta por ruta compartida | 32 |
| Rutas con ownership/resource | 120 |
| Ownership con guard estático del controlador | 23 |
| Ownership parcial | 109 |
| Ownership faltante/no demostrada | 8 |
| CSRF con validación estática localizada | 12 |
| CSRF requerido pero no demostrado | 66 |
| GET con posible efecto lateral no verificado | 52 |
| GET público/login con lectura estática | 24 |
| Rutas PDF/descarga de alto riesgo | 4 |

## Autenticación e identidad

'AuthMiddleware::authPermisos()' valida la cookie 'CLINICAJWT', decodifica JWT HS256 con 'APP_KEY', comprueba expiración y compara 'vista'. 'assertPatientAccess()' resuelve 'pc_paciente.id_clinica' y permite identidad paciente o clínica según el contexto. El registro de rutas no declara middleware; la protección observada es controller-level. Las rutas compartidas no pueden considerarse uniformemente protegidas sin pruebas runtime.

## Hallazgos que la matriz deja visibles

- Ownership por recurso: 'SEC-AUTH-007'; el guard estático no sustituye pruebas A/B por paciente y clínica.
- Recetas externas/PDF: 'SEC-DOC-001' y 'SEC-DOC-002' siguen abiertas.
- Archivos: 'SEC-FILE-003'; path confinement no equivale a propiedad documental.
- CSRF: 'SEC-CSRF-004'; la validación estática se localiza en entrevista/resumen, no de forma uniforme en los POST clínicos.
- SQL: 'SEC-SQL-005'; la matriz marca superficies mixtas/potencialmente inseguras sin ejecutar consultas.
- GET con escritura histórica o no reconciliada: 'CLIN-GET-006'.
- Runtime y explotación: 'QA-RUNTIME-010'; ninguna prueba HTTP/DB fue ejecutada.

## Criterio de cierre de rutas

Para declarar una ruta segura se requiere evidencia de sesión autorizada, recurso propio, caso cruzado denegado, CSRF cuando corresponda, errores controlados, lectura/escritura idempotente y trazabilidad de documento/archivo. V1.2 sólo entrega el inventario y el estado estático.

Artefacto principal: 'route_security_matrix_v1_2.csv'.
