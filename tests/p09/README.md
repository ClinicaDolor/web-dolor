# P09-RESTORE-REAL-01 — pruebas reproducibles

La preparación es fail-closed y sólo acepta `clinica_dolor_demo_20261010_final`.
No modifica `.env`, no usa `bd_clinica`, no ejecuta volcados completos y no
importa respuestas clínicas del ZIP.

```powershell
php tests/p09/import-catalog.php --archive=C:\Users\silvi\Downloads\P09_CATALOGOS_SEGUROS.zip --json
php tests/p09/prepare-synthetic.php --database=clinica_dolor_demo_20261010_final --json
node tests/p09/contract-check.mjs
node tests/p09/audio-mock.mjs
powershell -ExecutionPolicy Bypass -File tests/p09/start-server.ps1 -Port 8109
php tests/p09/http-crud.php --base-url=http://p09clean.localhost:8109 --database=clinica_dolor_demo_20261010_final --app-key=<clave-de-la-ejecución>
php tests/p09/cleanup-synthetic.php --database=clinica_dolor_demo_20261010_final
```

`import-catalog.php` valida las cuatro entradas esperadas del ZIP, confirma la
base activa y carga exactamente 6 temas y 46 preguntas. Si la estructura
sintética está vacía, añade sólo la columna `pregunta` faltante y las
relaciones del fixture; una segunda ejecución no duplica filas ni cambios.
Los pacientes sintéticos 9301/9302 deben pertenecer a clínicas distintas.

`http-crud.php` usa el login real y cubre GET/AJAX, persistencia, aislamiento
cruzado, finalización e idempotencia. La prueba de navegador debe usar el host
local de pruebas que resuelva al servidor aislado; no se reporta PASS si sólo
se inspecciona el HTML estático.

El mock confirma `es-MX`, cancelación y estados de SpeechSynthesis, pero la
reproducción audible humana permanece `NO VERIFICADO`.

La limpieza elimina sólo respuestas/finalizaciones P09 de 9301/9302 y conserva
el catálogo. Las pruebas responsive solicitadas fuera de la dimensión disponible
del navegador, la consola DevTools y el audio humano permanecen `NO VERIFICADO`.
