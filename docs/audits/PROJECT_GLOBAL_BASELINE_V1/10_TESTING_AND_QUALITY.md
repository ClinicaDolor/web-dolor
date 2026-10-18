# 10 — Testing y calidad

## Inventario

Suites observadas: `tests/p04`–`p11`, `patient-interview`, `patient-traditional`, `clinical-summary`, `security`, `unit`, `fixtures`, `evidence`, `phase02`, `phase03`, `phase04b`, `voice-stt-01` y `voice-stt-02`. Existen scripts PHP, JS/MJS, PowerShell, harnesses offline, fixtures y reportes.

## Ejecutado en esta auditoría

- PHP lint de `156` archivos `app`/`public`: PASS.
- JS syntax de `92` JS/MJS no minificados en `public/assets/js`: PASS.
- `git diff --check`: PASS.
- No HTTP, DB, browser, Docker ni mutaciones.

## Evidencia histórica

`docs/testing.md` y auditorías registran ejecuciones sintéticas de P04–P11, Doctor y seguridad con distintos fixtures, además de límites de navegador y pruebas no verificadas. Se conservaron como evidencia histórica, no se mezclaron con resultados del corte. Deben repetirse sobre la revisión actual y con un manifest de fixture/commit.

## Gaps de calidad

No se observó pipeline CI/CD ni `composer.lock`; cobertura PHPUnit/JS, mutation testing, SAST/DAST, dependencias vulnerables, pruebas de carga, accesibilidad automatizada, compatibilidad PHP/DB y matriz completa de errores quedan `UNVERIFIED`.

## Reconciliación de pruebas V1.1

`tests_complete.csv` enumera 170 archivos propios de prueba, fixture y harness con riesgo de escritura y entorno requerido. El nivel de archivo es inventariado; los casos internos sólo se consideran cerrados cuando existe evidencia explícita. El run `P04-AUTH-20261014121758` se conserva como histórico y no como ejecución actual. En V1.1 no se ejecutaron DB, HTTP, navegador, audio audible ni mutaciones.
