# 01 — Alcance, evidencia y cobertura

## Alcance autorizado

Se incluyeron Portal Paciente P01–P11, Entrevista Conversacional, Doctor D01–D40 y rutas/admin compartidas, persistencia inferida, autenticación/autorización, PDF/descargas/archivos, IA, UX/UI, pruebas, operaciones y documentación. La instrucción de esta fase autoriza incluir Entrevista aunque trabajos visuales anteriores la hubieran excluido.

## Método

1. Inventario con `git`, `rg`, `Get-ChildItem` y lectura de archivos fuente.
2. Lectura focalizada de bootstrap, rutas, Router, Database, middleware, controladores críticos, modelos y servicios.
3. Búsqueda automatizada de SQL, entrada HTTP, auth, filesystem, PDF, proveedores y tokens.
4. Lint PHP/JS y `git diff --check` sin ejecutar la aplicación.
5. Reconciliación contra documentos del repositorio; cada afirmación histórica queda etiquetada como histórica.

## Evidencia disponible

- Código actual en worktree y HEAD registrado.
- `docs/testing.md`, `docs/UI_DESIGN_CLOSURE_MASTER.md`, `docs/audits/`, `docs/architecture/`, `docs/security-backlog.md`, `docs/AI_PATIENT_INTERVIEW/`.
- Suites bajo `tests/`, incluyendo P04–P11, patient-interview, security, phase02/03/04b y harnesses offline.

## No ejecutado

No hubo conexión PDO/MySQL, HTTP, browser, XAMPP, Docker, migraciones, POST, PDF/descarga, upload, audio audible, consola/Network ni pruebas de dos usuarios. Por ello GET/POST, aislamiento, persistencia, conteo de no-op SQL, latencia y responsive se marcan `UNVERIFIED` salvo que se cite evidencia histórica como tal.

## Cobertura

Automatizada: todos los PHP bajo `app` y `public` para lint; todos los JS/MJS no minificados bajo `public/assets/js` para sintaxis; todos los archivos listados por `rg --files` para conteos y categorías. Directa/focalizada: bootstrap, Router, Database, AuthMiddleware, rutas, controladores de entrada/sensibles, modelos clínicos clave, servicios de resumen/entrevista/IA, PDF/Download, vistas por familia y documentación de riesgos. Parcial: revisión semántica línea por línea de todas las vistas, terceros, binarios, SQL real de la base y contratos runtime.

## Denominadores

Se reportan denominadores explícitos por fase; no se convierte `136 addRoute` en `136 pantallas`. Los artefactos untracked son parte del estado local observado, no se presume que estén listos para incorporación.

## Denominadores V1.1

El inventario detallado usa 3,435 archivos propios candidatos, 163 rutas efectivas, 11 módulos de paciente, 10 componentes de entrevista, 40 vistas Doctor, nueve áreas administrativas, 170 archivos de prueba, 31 recursos de datos estáticos y 170 documentos. Estos números acreditan inventario, no revisión semántica total ni ejecución runtime. Las exclusiones de terceros, cachés, uploads y artefactos generados se mantienen explícitas en el manifiesto.
