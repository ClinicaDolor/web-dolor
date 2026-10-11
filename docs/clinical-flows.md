# Flujos clínicos reconstruidos

## Flujo médico

1. Login en /clinica/acceso y POST /clinica/login.
2. AuthMiddleware valida el JWT y devuelve contexto de clínica, usuario, rol y vista.
3. HomeController muestra el dashboard y el total de pacientes de la clínica.
4. ClinicaController lista pacientes y permite abrir el expediente.
5. Desde el expediente se navega a ficha, nueve módulos, recetas, notas subsecuentes, laboratorio y COFEPRIS.
6. Las acciones clínicas POST reciben datos JSON o multipart y son procesadas por controladores y modelos.
7. Recetas, PDFs y archivos se consultan mediante rutas de búsqueda, PDF o descarga.

Pasos 1 a 5 están comprobados por routes.php, controladores, sidebar y vistas. La validez clínica de los campos y el momento de firma no se puede inferir sólo del código.

## Flujo paciente

1. Login en /historia-clinica/acceso y POST /historia-clinica/login.
2. HomeController muestra el dashboard paciente.
3. El paciente abre módulos mediante /historia-clinica/{modulo}/paciente/{idPaciente}.
4. Las operaciones comunes POST guardan antecedentes, medicación, procedimientos, evaluación y comentarios.
5. El paciente puede finalizar módulos; la semántica de cierre clínico requiere validación del equipo asistencial.

## Seguimiento y continuidad

El código contiene persistencia para recetas, notas, laboratorios, COFEPRIS y módulos, pero no se observa un flujo explícito de firma electrónica, addenda, bitácora de lectura, conciliación de cambios o restauración ante pérdida. Son requisitos pendientes de validación clínica y normativa.
