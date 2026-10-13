# P11 — pruebas sintéticas reproducibles

Estas pruebas sólo admiten la base `clinica_dolor_demo_20261010_final` y
rechazan cualquier otro nombre. No ejecutarlas contra `bd_clinica`.

## Preflight sin escrituras

```powershell
php tests/p11/preflight.php --database=clinica_dolor_demo_20261010_final
node tests/p11/contract-check.mjs
php -l tests/p11/http-crud.php
```

El preflight confirma la base activa, el esquema mínimo, los pacientes
sintéticos 9301/9302 y la línea base de P11 sin modificar datos.

## Recorrido HTTP completo

Usar una clave efímera exclusiva del servidor de pruebas; no copiar una clave
de producción ni guardarla en el repositorio:

```powershell
$testKey = '0123456789abcdef0123456789abcdef'
powershell -ExecutionPolicy Bypass -File tests/p09/start-server.ps1 -Port 8113 -AppKey $testKey
php tests/p11/http-crud.php `
  --database=clinica_dolor_demo_20261010_final `
  --base-url=http://127.0.0.1:8113 `
  --host=p11clean.localhost:8113 `
  --app-key=$testKey
php tests/p11/preflight.php --database=clinica_dolor_demo_20261010_final
```

El runner valida acceso propio, sesión ausente, acceso cruzado, AJAX, lectura
y guardado de mapas, limpieza independiente, cuestionario, dependencias,
persistencia, no-op HTTP, finalización e idempotencia. Al terminar restaura la
línea base de las tablas P11 y elimina los archivos privados creados durante la
prueba. Detener únicamente el PID del servidor sintético iniciado para esa
ejecución.

El no-op se verifica a nivel HTTP; para demostrar que no se ejecutó una
sentencia `UPDATE` se requiere instrumentación SQL separada y se reporta como
NO VERIFICADO en ausencia de ella.

## Navegador

Si existe un navegador aislado, abrir el host `p11clean.localhost:8113`, iniciar
sesión sólo con el PIN sintético local y verificar el modal, Escuchar/Detener,
Escape, frente, espalda, cuestionario, Pointer Events y consola. Registrar el
viewport real con `window.innerWidth`/`window.innerHeight`; no declarar PASS en
dimensiones no aplicadas por el navegador. La reproducción audible humana se
reporta por separado de la señal `SpeechSynthesis`.
