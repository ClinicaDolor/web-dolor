# Arquitectura y stack

## Hechos comprobados

- Aplicación PHP MVC ligera con punto de entrada en `public/index.php`.
- Enrutamiento con FastRoute en `app/Core/Router.php` y `app/Config/routes.php`.
- Persistencia MySQL mediante PDO en `app/Config/Database.php`.
- Autoload PSR-4 mediante Composer.
- Autenticación JWT con `firebase/php-jwt`.
- Generación de documentos con TCPDF y FPDI.
- Interfaz basada en Bootstrap, JavaScript embebido en vistas y bibliotecas locales/CDN.
- Vistas bajo `app/views`; lógica de dominio y SQL bajo `app/Models`.

## Capas observadas

```text
HTTP -> Router -> Controller -> Model/PDO -> MySQL
                    └-------> View/PHP/JS
```

La separación existe, pero algunas vistas ejecutan SQL y algunos modelos generan HTML. Esto es una deuda de mantenibilidad comprobada.

## Decisiones de esta iteración

- No migrar de framework.
- Mantener FastRoute, PDO, JWT, TCPDF y FPDI.
- Aplicar el sistema visual en `public/assets/css/app.css` para reutilizarlo en pantallas existentes.
- Mantener los endpoints y nombres de campos clínicos.
- Aplicar autorización incremental desde el middleware, sin migraciones de datos.

## Pendientes

- Esquema SQL versionado.
- Pruebas automatizadas.
- Auditoría de producción.
- Estrategia formal de archivos privados y respaldo.
