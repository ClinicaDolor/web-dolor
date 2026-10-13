# P08-COMPLETE-01 — pruebas reproducibles

La actividad usa únicamente `clinica_dolor_demo_20261010_final`, validada con
`SELECT DATABASE()`. Si la base activa es `bd_clinica` u otra diferente, los
scripts fallan antes de escribir. No se modifica `.env`.

```powershell
php tests/p08/prepare-synthetic.php --database=clinica_dolor_demo_20261010_final --json
node tests/p08/contract-check.mjs
```

Para las pruebas HTTP se inicia un servidor PHP aislado con
`tests/p04/router.php`, variables de proceso `DB_DATABASE` y `P08_TEST_DATABASE`
apuntando a la base allowlisted, `APP_ENV=testing` y una `APP_KEY` efímera. El
runner `http-crud.php` prueba GET propio/cruzado/anónimo, render AJAX, CRUD real,
no-op sin UPDATE, aislamiento de registro y finalización idempotente de módulo 6.

```powershell
powershell -ExecutionPolicy Bypass -File tests/p08/start-server.ps1 -Port 8108
php tests/p08/http-crud.php --database=clinica_dolor_demo_20261010_final --base-url=http://127.0.0.1:8108 --app-key=<APP_KEY_EFIMERA_DEL_SERVIDOR>
php tests/p08/cleanup-synthetic.php --database=clinica_dolor_demo_20261010_final
```

La limpieza elimina únicamente el marcador `P08-SYNTH-20261011` y la
finalización de módulo 6 después de que `prepare-synthetic.php` confirma que no
había una finalización previa. No usar el procedimiento contra `bd_clinica`.

El servidor de prueba se inicia con un proceso `pwsh` hijo para que funcione
tanto desde Windows PowerShell como desde PowerShell 7; el script imprime la
`APP_KEY` efímera que debe pasarse al runner. La validación visual autenticada
requiere un navegador que pueda abrir el servidor local. En la ejecución de
P08 el navegador integrado devolvió `net::ERR_BLOCKED_BY_CLIENT` y Chrome no
estuvo disponible, por lo que capturas, viewports y consola quedan
`NO VERIFICADO`.
