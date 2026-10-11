# Configuración, despliegue, respaldos y recuperación

## Comprobado

- La configuración usa variables de entorno cargadas con Dotenv.
- La base de datos se conecta mediante PDO/MySQL.
- El punto de entrada público es `public/index.php`.
- Apache usa `.htaccess` para dirigir tráfico a `public`.

## No incluido en el repositorio

- Procedimiento de despliegue aprobado.
- Migraciones.
- Scripts de respaldo.
- Procedimiento de restauración.
- RPO/RTO.
- Monitoreo y alertas.
- Gestión de secretos.

## Requisitos antes de producción clínica

1. HTTPS obligatorio.
2. Variables de entorno fuera del control de versiones.
3. Archivos clínicos fuera del webroot.
4. Backup cifrado y restauración probada.
5. `composer.lock` versionado.
6. Logs sin datos clínicos innecesarios.
7. Pruebas de autorización y regresión.
