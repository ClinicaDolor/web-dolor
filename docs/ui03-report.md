# Sprint UI-03 — Cierre visual y preparación de entrega

Fecha: 2026-10-10. Entorno: fixtures sintéticos aislados. No es una
declaración de preparación para producción.

## Resultado ejecutivo

- P0: ninguno detectado.
- P1 corregido: los identificadores de cuatro dígitos de las tablas de
  `/clinica/recetas` se partían carácter por carácter a 375 px. Se aplicó
  `table-layout: fixed` y una primera columna no envolvente, scoped a esa
  vista.
- P2 documentados, sin implementar: nombres largos que requieren varias
  líneas en tablas móviles y microcopy heredado en estados vacíos.
- Las rutas `/clinica/recetas` y `/clinica/cofepris` quedaron verificadas en
  modo lectura después de ampliar únicamente el fixture.

## Matriz final

| Superficie | Estado | Viewports | Funciones comprobadas | Pendientes |
|---|---|---|---|---|
| Login Doctor/Paciente | PASS | 1440×900, 1280×720, 768×1024, 375×667 | Campos, enlaces entre portales, sin overflow | Autenticación negativa completa fuera de este cierre |
| Dashboard Doctor | PASS | 4 tamaños | Render, navegación y KPI sintético | Operaciones clínicas no ejecutadas |
| Pacientes / Nuevo paciente | PASS visual | 4 tamaños | Render y navegación | Guardado no ejecutado |
| Expediente / Historia Clínica | PASS | 4 tamaños | 9 filas, dropdown `Acciones`, Escape, sin overflow | Escrituras y autorización cruzada no ejecutadas |
| Vistas internas médicas | PASS visual en rutas válidas | 4 tamaños | Nota, laboratorio, receta, COFEPRIS, PIN y módulos | PDF, descargas y persistencia no ejecutados |
| `/clinica/recetas` | PASS modo lectura | 4 tamaños | Dos tablas, 2 recetas sintéticas, estado vacío de externas | Crear/editar/PDF no ejecutados |
| `/clinica/cofepris` | PASS modo lectura | 4 tamaños | Formulario, tabla vacía y estados reales del fixture | Carga de archivos y folios no ejecutada |
| Dashboard Paciente | PASS | 4 tamaños | 9 tarjetas y estados existentes | Guardado/finalización no ejecutados |
| Nueve formularios paciente | PASS responsive | 36 casos (9×4) | Render, controles, footer y no overflow | Respuestas y guardado no ejecutados |

## Rutas antes no verificadas

Las rutas existen en `app/Config/routes.php` y apuntan a:

- `/clinica/recetas` → `ClinicaController::recetasIndex()` →
  `app/views/clinica/recetas.php`.
- `/clinica/cofepris` → `HomeController::indexCofepris()` →
  `app/views/clinica/configuracion-cofepris.php`.

La indisponibilidad anterior fue causada por dependencias de fixture ausentes,
no por una ruta inexistente: `receta_medica_externos` y `cofepris_folio` eran
consultadas por los modelos/vistas. Se agregó únicamente su DDL mínimo
verificado a `tests/fixtures/security-fixtures.sql`, sin registros clínicos
reales y sin alterar producción.

## Recorrido recomendado

1. Acceso médico.
2. Dashboard Doctor.
3. Directorio de pacientes.
4. Expediente sintético `9301`.
5. Historia Clínica y apertura de `Acciones`.
6. Vista Laboratorio en modo lectura.
7. Cierre de sesión.
8. Acceso paciente.
9. Dashboard Paciente y continuación de Antecedentes Personales No Patológicos.

El recorrido fue ejecutado sin crear, editar, finalizar, descargar ni eliminar
registros clínicos.

## Riesgos abiertos

Las vulnerabilidades críticas de autorización y almacenamiento documental de
ACT-05C-02 continúan abiertas. No se modificaron descargas, almacenamiento,
permisos, autenticación, reglas clínicas, esquemas productivos ni despliegues.
