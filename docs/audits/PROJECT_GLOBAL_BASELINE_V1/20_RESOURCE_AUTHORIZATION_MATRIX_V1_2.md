# 20 — Matriz de autorización de recursos V1.2

## Estado

'PARTIAL_STATIC / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY'

La matriz contiene **120 endpoints/recursos** derivados de las 163 rutas efectivas. No se consultó la base de datos ni se ejecutaron solicitudes HTTP. La propiedad se marca como demostrada sólo cuando existe una ruta estática clara hacia 'AuthMiddleware::assertPatientAccess()'; aun así requiere pruebas runtime propias y cruzadas.

## Resumen

| Estado estático | Filas |
|---|---:|
| Guard de ownership localizado, parcial | 1 |
| Guard/relación parcial | 109 |
| Propiedad faltante o no demostrada | 8 |
| No resuelto | 2 |

## Áreas críticas

1. **Recetas externas:** catálogo/folios y detalle no tienen propietario clínico verificable en toda la cadena; no asumir que son globales.
2. **PDF externo:** 'PdfController::pdfRecetaExterno()' queda vinculado a 'SEC-DOC-001' hasta demostrar ownership antes de renderizar.
3. **Descargas:** 'DownloadController::download()' confina el path a 'public/storage', pero la ruta no resuelve el propietario clínico del documento; 'SEC-FILE-003' permanece abierta.
4. **COFEPRIS:** se observan guardas en algunos detalles, pero la matriz completa de folio/carpeta/archivo y pruebas cruzadas no están ejecutadas.
5. **Endpoints por ID:** la presencia de 'idPaciente' o 'idReceta' en la URL/body no se acepta como autorización; la resolución debe ocurrir en servidor.

## Pruebas requeridas para cierre

- Paciente/clínica A lee y modifica sólo su recurso sintético.
- Paciente/clínica A intenta leer, modificar, eliminar, imprimir y descargar un recurso de B.
- ID inexistente, ID manipulado y recurso sin propietario verificable.
- Respuesta sin fuga de HTML, JSON, PDF, nombre de archivo o metadatos.
- Repetición idempotente y no-op cuando una operación no cambia estado.

Artefacto detallado: 'resource_authorization_matrix_v1_2.csv'.
