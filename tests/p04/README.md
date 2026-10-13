# Entorno sintético reutilizable para P04

Este directorio contiene la línea base aislada para validar el módulo P04 y
reutilizar la misma sesión/base en P05–P11. No modifica `.env`, la aplicación
clínica ni la base protegida `bd_clinica`.

## Base autorizada

La única base permitida por la guarda es:

`clinica_dolor_demo_20261010_final`

La precondición verifica, en modo de solo lectura, la conexión efectiva, dos
pacientes sintéticos independientes (`9301` y `9302`), dos clínicas, sus
accesos, los nueve módulos, comentarios y las tablas mínimas compartidas por
P04–P11.
También rechaza filas P04 fuera de esos pacientes. Los nombres de usuario,
PIN y contraseñas sintéticas no se documentan aquí.

La base no incluye los auxiliares de recetas externas ni COFEPRIS usados por
otros flujos. Esos módulos están fuera de esta prueba y no deben incorporarse
como requisito artificial de P04.

## Preflight de aislamiento

Desde la raíz del proyecto:

```powershell
$env:P04_TEST_DATABASE = 'clinica_dolor_demo_20261010_final'
php tests/p04/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
```

El comando debe finalizar con código `0`, devolver `"ok": true` y mostrar la
misma base en `database` y `active_database_matches_allowlist`. Cualquier otro
nombre, ausencia de la variable o discrepancia entre argumento y variable
termina con código `2` y no permite arrancar el servidor.

## Servidor aislado

```powershell
$server = .\tests\p04\start-server.ps1 -Port 8081
$server
```

El script vuelve a ejecutar el preflight, arranca `php -S` en
`127.0.0.1:8081`, inyecta sólo en ese proceso `DB_DATABASE`, `APP_ENV`,
`P04_TEST_MODE` y una clave efímera, y ejecuta PHP con `variables_order=EGPCS`
para que la aplicación existente pueda leer `$_ENV`. No altera `.env`. El servidor se detiene
con el PID reportado:

```powershell
.\tests\p04\stop-server.ps1 -Pid <PID>
```

No se deben usar `php -S` ni rutas de producción sin el preflight. Si la base
activa no coincide exactamente, se debe detener la prueba.

## Sesión y recorrido manual

El inicio de sesión debe hacerse desde el formulario real de paciente en
`http://127.0.0.1:8081/historia-clinica/acceso`, usando sólo la cuenta/PIN
sintéticos disponibles en el fixture y sin registrar esos secretos. No se
crean rutas de bypass ni cookies de prueba artificiales.

Recorrido P04: abrir el módulo, aceptar las instrucciones, responder una
pregunta, comprobar el guardado, avanzar, volver, recuperar una respuesta,
ejercitar un caso `Sí` con sus campos dependientes y comentarios. No finalizar
el módulo si el escenario sintético no está preparado para esa operación.

La prueba cruzada debe usar el contexto real de la sesión sintética y un
`idPaciente` de la otra clínica; se espera rechazo HTTP seguro y ningún cambio
en la base. No se deben modificar pacientes reales.

## Audio

Separar la verificación del contrato mock (`speechSynthesis`, texto solicitado,
cancelación antes de avanzar y ausencia de dos narraciones activas) de la
reproducción audible real. Esta última sólo puede marcarse PASS con un
navegador/interacción que permita observarla; en otro caso es `NO VERIFICADO`.

El mock reproducible del contrato compartido se ejecuta desde la raíz con:

```powershell
node tests/p04/audio-mock.mjs
```

Valida texto solicitado, una llamada de narración y cancelación antes de una
nueva lectura. No simula que el equipo reproduzca audio: esa parte continúa
siendo una comprobación de navegador/interacción.

## Reutilización futura

P05–P11 deben reutilizar esta misma base y el mismo preflight, ampliando sólo
las tablas/filas sintéticas que sus contratos existentes requieran. No crear
una base por módulo, no copiar datos reales y no cambiar `DB_DATABASE` a mano
sin repetir la guarda.

