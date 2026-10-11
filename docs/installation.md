# Instalación y operación local

## Componentes comprobados

- PHP y Composer, según composer.json.
- Apache/XAMPP y MySQL en el entorno local auditado.
- Punto de entrada: public/index.php.
- Variables de entorno cargadas con vlucas/phpdotenv.
- Rewrite configurado mediante .htaccess.

## Variables

El archivo .env contiene APP_NAME, APP_KEY, OPENAI_API_KEY y variables DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME y DB_PASSWORD. Este documento no reproduce valores.

## Procedimiento pendiente de formalizar

No existe un script de instalación, migración o health check versionado. Antes de una entrega clínica se debe documentar instalación limpia, permisos de storage, configuración HTTPS, rotación de secretos, usuario mínimo de base de datos y validación de conexión.

## Datos y recuperación

No se encontró script de backup ni restore. La restauración debe probarse en una base aislada con criterios RPO/RTO aprobados y evidencia de integridad de expedientes.
