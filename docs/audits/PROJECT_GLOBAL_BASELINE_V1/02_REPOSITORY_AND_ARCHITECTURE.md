# 02 — Repositorio y arquitectura

## Estructura observada

`.git`, `app`, `public`, `tests`, `docs`, `logs`, `vendor`, `src` (sin archivos observados), `.env`, `.htaccess`, `composer.json`. La aplicación PHP vive en `app`; `public/index.php` es front controller y `.htaccess` reescribe solicitudes hacia `public`.

## Stack

- PHP CLI `8.4.24` observado; PSR-4 `App\\` → `app/`.
- FastRoute `^1.3`, firebase/php-jwt `^6.11`, vlucas/phpdotenv `^5.6`, FPDI `^2.6`, TCPDF `^6.9`.
- PDO/pdo_mysql, MySQL, mbstring, OpenSSL, cURL, GD y JSON disponibles en CLI.
- Node `20.19.6`, npm `10.8.2`; uso principal en checks/harnesses, no se encontró lock Composer.

## Bootstrap

`public/index.php` carga Composer, constantes, `SafeErrorHandler`, Dotenv immutable y Router. `Database` construye PDO con env, UTF-8mb4, excepciones, fetch asociativo y prepares no emulados. `BaseController::view()` extrae datos y carga vistas PHP.

## Capas

- Core: `Router`, `HttpMethod`, `SafeErrorHandler`, `PasswordHelper`.
- Middleware: `AuthMiddleware` con JWT y `assertPatientAccess`.
- Controllers: acceso web/paciente/clinica, módulos, búsqueda, PDF, descargas, entrevistas y clínicos.
- Models: pacientes, módulos, recetas, recetas externas, COFEPRIS, notas, laboratorio y evaluación.
- Services: resúmenes clínicos, suplementos, políticas de escritura, indicadores y entrevista/proveedores.
- Views/assets: PHP por área, CSS compartido `app.css`, JS/imagenes/libs.

## Dependencias y suministro

Existe `composer.json` pero no se observó `composer.lock`; esto reduce reproducibilidad de dependencias. `vendor/` está presente, pero su estado/versiones no se revalidó línea por línea. No hay una migración/DDL completo versionado identificado en el inventario amplio; la estructura real de DB queda `UNVERIFIED`.

## Estado Git

Rama `Silvino`; HEAD `59654e05dc9f931c21b5392238961bcddb3ab2e`; worktree sucio antes de esta fase con modificaciones y untracked en app, docs, public y tests. No se ejecutaron reset, clean, add, commit, push ni deploy.
