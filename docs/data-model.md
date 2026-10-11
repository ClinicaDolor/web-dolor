# Modelo de datos y relaciones

## Evidencia disponible

La conexión se define en app/Config/Database.php con PDO/MySQL y utf8mb4. No existen migraciones ni esquema versionado en el repositorio. En la auditoría local sólo se inspeccionaron nombres de tablas y definiciones estructurales; no se leyeron filas de la base productiva.

## Entidades centrales comprobadas

- clinicas: entidad padre de pacientes y doctores.
- doctores: referencia clinica_id hacia clinicas.
- pc_paciente: expediente base; referencia id_clinica hacia clinicas.
- pc_paciente_acceso: acceso del paciente; referencia paciente_id hacia pc_paciente.
- historia_clinica_modulos: catálogo de módulos.
- receta_medica: recetas vinculadas por id_paciente; el esquema observado no declara una foreign key.
- receta_medica_externos: recetas externas observadas en el inventario de tablas.
- nota_subsecuente, laboratorio y cofepris: registros clínicos relacionados con paciente según sus modelos.
- tablas pc_* y pac_*: antecedentes, cuestionarios, medicación, procedimientos, comentarios, finalización y evaluación del dolor.

## Integridad observada

- pc_paciente.id_clinica tiene índice y foreign key hacia clinicas.
- pc_paciente_acceso.paciente_id tiene índice y foreign key hacia pc_paciente.
- La receta interna se filtra por id_paciente en modelos y búsquedas, pero su tabla no mostró foreign key en la definición inspeccionada.
- El conteo del dashboard médico fue corregido para filtrar por id_clinica mediante parámetro PDO.

## Riesgos y pendientes

- No hay migraciones reproducibles, checksum de esquema ni datos de referencia versionados.
- No hay procedimiento de backup/restore probado.
- La ausencia de relaciones declarativas en algunos registros puede permitir huérfanos.
- Se requiere catálogo de retención, cifrado, clasificación y eliminación segura de datos clínicos.
