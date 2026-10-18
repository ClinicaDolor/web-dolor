# 05 — Doctor, administración y roles

## Superficies

El grupo `/clinica` incluye login, dashboard, indicadores, pacientes, expediente, PIN, notas, laboratorio, recetas internas/externas, COFEPRIS, módulos Doctor, resumen, perfil y búsquedas. El grupo compartido `/buscar` se protege en `Router` salvo `/buscar/web`; las rutas `/pdf` y `/descargar` reciben una barrera clínica de sesión en Router.

## Autenticación y autorización observadas

`AuthMiddleware` decodifica cookie `CLINICAJWT` con `APP_KEY`, comprueba expiración/vista y obtiene `id_clinica`/permisos. `assertPatientAccess` consulta `pc_paciente` por ID y compara clínica o identidad autorizada. Muchos controladores invocan `authPermisos` y, para operaciones ligadas a paciente, `assertPatientAccess`.

## Gaps a probar

- Matriz completa de roles/permisos, superadmin, multi-clínica y permisos por documento: `UNVERIFIED`.
- Rutas externas de receta y búsquedas por ID: revisar que el registro cargado se vincule a paciente/clínica antes de renderizar o generar documento.
- `DownloadController` sólo demuestra sesión y confinamiento de ruta, no autorización del recurso.
- COFEPRIS y folios requieren separación entre catálogo/folio global y documento clínico propietario.
- Las vistas con SQL directo deben retirar acceso a DB del HTML y usar servicios autorizados.

## Estado

`PARTIAL_STATIC`; no se declara aislamiento por clínica ni seguridad de roles completa.
