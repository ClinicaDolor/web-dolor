# 07 — Base de datos e integridad

## Alcance de evidencia

No se accedió a MySQL ni se ejecutaron SQL/PDO. La estructura se infiere de strings SQL, modelos, fixtures y documentación; no se afirma que el esquema desplegado coincida.

## Familias de tablas inferidas

`pc_paciente`, `pc_paciente_acceso`, `pc_antecedentes_familiares`, `pc_antecedentes_quirurgicos`, `pc_paciente_evaluacion_dolor`, `pac_temas_modulo_3/5/7`, `pac_preguntas_modulo_3/5/7`, respuestas de módulos 3/5/7/8, catálogos de enfermedades/tratamientos, `pac_medicacion_actual_modulo_6`, `pac_procedimientos_dolor_modulo_8`, `pac_historia_clinica_comentario`, `pac_historia_clinica_finalizar`, `receta_medica`, `receta_medica_externos`, `nota_subsecuente`, `laboratorio`, `cofepris`, `cofepris_folio`, `clinicas` y tablas de usuarios/roles inferidas por modelos.

## Integridad que debe demostrarse

- FK/índices/unicidad para paciente-clínica y registros clínicos.
- Propietario verificable para recetas externas, folios y archivos.
- Transacciones e idempotencia de captura/finalización/reintentos.
- No-op real cuando una respuesta no cambia.
- Limpieza reversible de fixtures y rollback de pruebas.
- Reglas de borrado, auditoría e historial.
- DDL versionado, migraciones y backups probados.

## Observaciones estáticas

Hay consultas directas con `id_paciente`, pero también catálogo global/consultas por ID y SQL concatenado. Algunos métodos crean o siembran registros al preparar la vista. La documentación histórica afirma correcciones divergentes; el código y la base real deben reconciliarse antes de cerrar.

## Estado

`UNVERIFIED` para esquema, constraints, datos, cardinalidad, backups y consistencia actual. No se modificó ninguna base.
