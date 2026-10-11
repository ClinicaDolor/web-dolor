# Registro de decisiones técnicas

## ADR-001 — Conservar el stack durante el cierre visual

Estado: adoptada.

La modernización visual usa PHP, FastRoute, PDO, Bootstrap y los assets existentes. No se migró framework ni se reestructuró toda la aplicación porque el objetivo de esta fase es entregar dashboards funcionales y reducir riesgo de regresión clínica.

## ADR-002 — Sistema visual compartido

Estado: adoptada.

Los tokens y componentes se concentran en public/assets/css/app.css. Las pantallas intervenidas conservan rutas, campos y scripts.

## ADR-003 — Autorización por ámbito antes de consultar el recurso

Estado: adoptada y verificada parcialmente.

AuthMiddleware::assertPatientAccess consulta la relación paciente-clínica y rechaza 404/403 antes de continuar. Las pruebas Iteración 3 cubren lectura y escritura cruzadas; aún falta cobertura exhaustiva por recurso.

## ADR-004 — Fixtures sintéticos aislados

Estado: adoptada.

La validación Iteración 3 usa tests/fixtures/security-fixtures.sql y una base local separada. No se usaron filas productivas ni se modificó .env.

## ADR-005 — Cambios de seguridad pequeños y verificables

Estado: adoptada.

Se corrigió el filtro de KPI por clínica y se restringió la resolución física de archivos a public/storage. CSRF, auditoría, SQL heredado y autorización por propietario de archivo requieren incrementos posteriores.

## ADR-006 — Identidad única para portales médico y paciente

Estado: adoptada para la implementación visual.

La identidad común se implementa sin cambiar framework, rutas, consultas ni
identificadores consumidos por `public/assets/js/login.js`. El componente
`app/views/components/auth-visual.php` comparte la composición de acceso y
recibe únicamente el rol visual (`doctor` o `patient`); los campos y funciones
de autenticación permanecen en cada vista. Los tokens se limitan a los cuerpos
de autenticación y áreas privadas para no intervenir la web pública. La
validación de contraste y la prueba formal con lector de pantalla permanecen
pendientes.
