# TH-SEC-ENV-OFFLINE-PREP-05 — Artefactos offline

Estos archivos son plantillas revisables. No se ejecutaron desde Codex y no crean infraestructura por sí mismos.

## Archivos

- `compose.offline.yml`: plantilla de un único servicio MariaDB 10.6, sin puertos, red interna, volumen exclusivo, `pull_policy: never` y secreto externo.
- `preflight-offline.ps1`: inspección de sólo lectura con compuertas H01–H08; aborta ante daemon, imagen, secreto, hash, procedencia, colisión o configuración insegura. No contiene `compose up`, `pull`, `build`, SQL ni migraciones.
- `fixtures.sha256`: hashes revisados para los fixtures candidatos.
- `.gitignore`: evita agregar secretos locales.
- `README.md`: procedimiento y límites.

## Revisión de fixtures

`security-fixtures.sql` se declara sintético y utiliza IDs reservados; los catálogos P05/P07 y el fixture KPI son definiciones de prueba. Su procedencia runtime y su admisibilidad para una base nueva deben ser aprobadas por el propietario. No se importan automáticamente.

No se aceptan respaldos, volcados, datos anonimizados de producción, credenciales, tokens, documentos clínicos reales ni referencias a `bd_clinica`.

## Requisitos de secretos

El propietario debe crear un directorio de secretos fuera del repositorio, por ejemplo en una ubicación local protegida, con un archivo `mariadb-root-password`. No debe copiarse ninguna contraseña existente ni incluirse el valor en comandos, logs o Git.

La única imagen candidata de esta plantilla es `mariadb:10.6`. El propietario reportó previamente el prefijo local `1e008264230a`; el preflight debe confirmar el ID completo y bloquea cualquier otra imagen. La plantilla no descarga imágenes: `pull_policy: never` hace que falle si la imagen no existe localmente.

La ruta de secretos debe ser absoluta, externa al repositorio y verificable mediante ACL. El preflight nunca lee ni imprime el contenido de `mariadb-root-password`. La procedencia sintética de los fixtures requiere una evidencia separada del propietario; hashes correctos no autorizan importación.

## Preflight manual posterior

Desde el PowerShell autorizado del propietario, después de revisar esta carpeta:

```powershell
$env:TH_SEC_SECRET_DIR = '<directorio-de-secretos-fuera-del-repositorio>'
& '.\tests\security\infra\preflight-offline.ps1' `
  -Context 'desktop-linux' `
  -SecretDirectory $env:TH_SEC_SECRET_DIR
```

El preflight sólo debe imprimir estados PASS/BLOCKED y metadatos no sensibles. Siempre informa `DATABASE_CREATION_AUTHORIZED: NO` y `READY_FOR_INFRASTRUCTURE_CREATION: NO`; no debe ejecutarse `docker compose up` hasta que el propietario revise el resultado, la procedencia de fixtures y autorice una fase posterior.

## Secuencia futura, no ejecutada

1. Ejecutar el preflight y guardar su salida sin secretos.
2. Revisar el ID completo de `mariadb:10.6`, hashes, procedencia sintética y ausencia de colisiones.
3. En una fase autorizada separada, crear exclusivamente la red, volumen y contenedor con el Compose revisado.
4. Dentro de esa instancia, crear las dos bases y las cuentas mínimas; no usar XAMPP ni `.env`.
5. Verificar grants y base efectiva dentro del nuevo motor.
6. Importar sólo fixtures aprobados, sin inventar esquema faltante.
7. Documentar la evidencia y detenerse antes de pruebas HTTP o clínicas.

La plantilla no monta el proyecto, `.env`, `C:\xampp\mysql\data`, documentos, socket Docker, `host.docker.internal` ni redes de producción. No publica puertos.
