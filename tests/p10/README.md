# P10-CATALOG-UNBLOCK-02 — pruebas reproducibles

Todas las pruebas de escritura son fail-closed y sólo aceptan
`clinica_dolor_demo_20261010_final`. No usan `bd_clinica`, no modifican `.env` y
no importan un catálogo no autorizado. La preparación sólo inspecciona la
estructura y el conteo del catálogo; si el conteo es cero, los tratamientos se
mantienen en estado seguro de indisponibilidad.

```powershell
php tests/p10/import-catalog.php --archive=C:\Users\silvi\Downloads\P10_CATALOGO_SEGURO.zip --database=clinica_dolor_demo_20261010_final --json
php tests/p10/prepare-synthetic.php --database=clinica_dolor_demo_20261010_final --json
node tests/p10/contract-check.mjs
node tests/p10/audio-mock.mjs
```

Las pruebas HTTP requieren un servidor local aislado con el fixture confirmado.
Puede reutilizarse el router de pruebas ya existente en P09, sin modificar P09:

```powershell
powershell -ExecutionPolicy Bypass -File tests/p09/start-server.ps1 -Port 8109
php tests/p10/http-crud.php --base-url=http://127.0.0.1:8109 --database=clinica_dolor_demo_20261010_final --app-key=<clave-de-la-ejecución>
```

`http-crud.php` limpia únicamente los registros P10 que crea. La ejecución debe
detener el proceso del servidor al terminar; no se debe ejecutar un limpiador
de otro módulo para cerrar P10. La importación verifica el SHA-256 del SQL,
parsea sólo los diez registros autorizados y conserva el catálogo 10/10 sin
respuestas. Las pruebas de navegador requieren además una sesión sintética y
un navegador que no bloquee el host local.
