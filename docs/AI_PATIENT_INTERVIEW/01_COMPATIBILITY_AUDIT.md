# Auditoría de compatibilidad — entrevista del paciente

## A1. Inventario arquitectónico

El directorio local es una aplicación PHP MVC propia, no Laravel. La entrada HTTP es `public/index.php`, que carga `.env`, el autoloader y despacha a `app/Core/Router.php`. Las rutas se declaran en `app/Config/routes.php`; la persistencia usa PDO mediante `app/Config/Database.php` y modelos en `app/Models`.

El árbol ya contiene controladores, modelos, vistas, JavaScript y servicios clínicos. El worktree estaba deliberadamente sucio por cambios concurrentes P05–P11 y por el resumen clínico; esos archivos no se modificaron en esta auditoría.

Rutas relevantes:

- Paciente: `/historia-clinica`, `/historia-clinica/{modulo}/paciente/{idPaciente}`.
- Doctor: `/clinica`, `/clinica/{modulo}/paciente/{idPaciente}`.
- Fragmento M03: `/buscar/contenido-preguntas-modulo-3/{idPaciente}/{idRol}/{idCuestionario}`.
- Guardado M03: `POST /historia-clinica/paciente/editar-cuestionario-modulo3` o su equivalente `/clinica/...`.
- Finalización y comentarios: rutas comunes `/paciente/agregar-comentario-modulo` y `/paciente/finalizar-modulo-paciente`.

## A2. Matriz M01–M09

| Módulo | Tablas de captura principales | Respuesta / campos clínicos | Catálogos o controles | Compatibilidad observada |
|---|---|---|---|---|
| M01 | `pc_paciente` | identificación pertinente, motivo y datos generales | `id`, `id_clinica` | Fuente existente; no forma parte del piloto |
| M02 | `pc_antecedentes_familiares` | enfermedad, detalle, tipo diabetes, parentesco | catálogos de enfermedades según modelo | Filas pueden inicializarse vacías; no usar nombre de catálogo como afirmación |
| M03 | `pac_respuestas_paciente_modulo_3` | `respuesta` por pregunta | `pac_temas_modulo_3`, `pac_preguntas_modulo_3`, `id_pregunta`, `tipo` | Compatible con entrevista guiada si se reutiliza el modelo; respuestas se inicializan vacías |
| M04 | `pc_antecedentes_quirurgicos` | fecha, cirugía, observaciones | `id` | Fuente existente; filas vacías posibles |
| M05 | `pac_respuestas_paciente_modulo_5`, `pac_enfermedades_respuestas_modulo_5` | respuestas y año de diagnóstico | preguntas, enfermedades, IDs | Reglas condicionales y dos estructuras; fuera del piloto |
| M06 | `pac_medicacion_actual_modulo_6` | medicamento, dosis, tiempo, médico | `id` | Puede contener fila inicializada sin medicamento |
| M07 | `pac_respuestas_paciente_modulo_7` | respuesta, dosis, resultados, consumo | preguntas, temas, `id_pregunta` | No tratar filas de catálogo como uso de medicamento |
| M08 | `pac_procedimientos_dolor_modulo_8`, `pac_respuestas_paciente_modulo_8` | procedimientos o `utilizo`/resultados | tratamientos, `id_tratamiento` | Dos fuentes con semántica distinta |
| M09 | `pc_paciente_evaluacion_dolor` | dolor, evolución, descripción, incremento | `id` | Fila inicializada posible; cero debe conservarse |

## A3. Estructura M03 verificada

Consulta de esquema de solo lectura: la conexión configurada apunta a `bd_clinica`; se revisaron únicamente nombres de columnas, nulabilidad y claves, sin imprimir filas de pacientes.

- `pac_temas_modulo_3`: `id` PK, `tema`.
- `pac_preguntas_modulo_3`: `id` PK, `id_tema`, `pregunta`, `tipo`.
- `pac_respuestas_paciente_modulo_3`: `id` PK, `id_pregunta`, `id_paciente`, `respuesta`.

El modelo `AntecedentesNoPatologicosModel` confirma que las filas pueden crearse con `respuesta=''`; por ello un registro existente no demuestra que el paciente haya contestado. La clasificación correcta es: catálogo, respuesta vacía inicializada, respuesta explícita o texto documentado.

## A4. Reglas y persistencia M03

La vista carga temas y preguntas mediante `fetch` al endpoint de búsqueda. El servidor produce HTML usando el catálogo y la respuesta persistida. La navegación muestra una pregunta a la vez y guarda posición de navegación en `localStorage` con claves ligadas al paciente/tema; no guarda la respuesta clínica allí.

El guardado actual sigue este recorrido:

```text
control HTML/JS
  -> POST JSON editar-cuestionario-modulo3
  -> ModulosController::pacienteEditarCuestionarioM3
  -> AuthMiddleware::authPermisos + assertPatientAccess
  -> AntecedentesNoPatologicosModel::editarCuestionarioModulo
  -> validar contexto idPaciente/idRespuesta/idTema/tipo
  -> UPDATE parametrizado de respuesta
```

Para un `select` con `No`, el modelo actualiza la respuesta principal y limpia las demás respuestas del tema dentro de una transacción. Para `Sí`, actualiza solo la respuesta seleccionada. Para `input`, actualiza el texto. Esta regla es clínicamente relevante y no debe duplicarse en JavaScript.

## A5. Riesgos de compatibilidad

- El modelo compara tipos `input`/`select`, mientras una consulta de porcentaje usa variantes `Select`/`Sí`; debe verificarse antes de reutilizar el cálculo como estado de entrevista.
- El modelo M03 inicializa filas vacías, por lo que “fila existente” no equivale a “respuesta documentada”.
- El guardado actual valida autorización de paciente/tema/respuesta, pero no se identificó un token CSRF específico en el endpoint M03. Esto es un bloqueo P0 para un nuevo flujo.
- La selección `No` limpia respuestas del mismo tema. La entrevista debe advertir y confirmar esa consecuencia antes de guardar.
- El contrato actual devuelve HTML generado por modelo; una interfaz nueva necesitaría un adaptador de lectura o reutilizar el fragmento, sin duplicar reglas.
- El worktree contiene modificaciones concurrentes. No se puede asumir que una rama remota coincida con el estado local.

## Conclusión A2

`LEGACY_DATABASE_COMPATIBILITY: PARTIAL`. Las tablas y claves necesarias para M03 existen y son compatibles con lectura/actualización por pregunta, pero faltan pruebas de compatibilidad completa con registros históricos, reglas condicionales de los temas elegidos y cierre seguro del contrato.
