# Pruebas sintéticas de P06

Estas comprobaciones son estáticas y de mock; no crean sesiones ni escriben en la base clínica.

```powershell
node tests/p06/contract-check.mjs
node tests/p06/audio-mock.mjs
php tests/p06/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
php tests/p06/http-crud.php --database=clinica_dolor_demo_20261010_final --base-url=http://127.0.0.1:8086
php -l app/Models/AntecedentesQuirurgicos.php
php -l app/Controllers/ModulosController.php
php -l app/Controllers/BusquedasController.php
php -l app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php
```

La verificación de modelo usa una transacción con rollback en la base allowlisted y no deja escrituras persistentes. La reproducción audible real, el CRUD HTTP autenticado, las capturas responsive y el flujo de autorización cruzada a nivel de sesión requieren una sesión sintética reproducible y navegador; si no está disponible deben reportarse como `NO VERIFICADO`.
