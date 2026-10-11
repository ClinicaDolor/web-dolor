# Roles y permisos observados

## Hechos comprobados

- AuthMiddleware acepta las vistas de sesión clinica e historia-clinica mediante el claim vista del JWT.
- El claim rol se conserva y se pasa a vistas y módulos; se observa DOCTOR en la configuración de sidebar y PACIENTE en el portal paciente.
- La sesión clínica se identifica por id_clinica e id_usuario.
- La sesión paciente se vincula al paciente mediante id_usuario igual al id del paciente.
- Las vistas, búsquedas y escrituras con idPaciente revisadas invocan assertPatientAccess.
- El médico sólo puede acceder a un paciente cuya id_clinica coincide con su sesión.
- Las descargas y PDFs sólo exigen sesión clínica en el control central actual; la descarga no recibe idPaciente ni verifica propiedad del archivo.

## Matriz mínima verificada

| Actor | Recurso propio | Recurso de otra clínica/paciente | Evidencia |
|---|---|---|---|
| Médico A | Lectura y escritura de paciente A | 403 para paciente B | Fixture de seguridad Iteración 3 |
| Médico B | Lectura de paciente B | 403 para paciente A | Fixture de seguridad Iteración 3 |
| Paciente A | Lectura de su ficha | 403 para ficha de paciente B | Fixture de seguridad Iteración 3 |
| Médico autenticado | Descarga con ruta conocida | Actualmente 200 si conoce la ruta | DownloadController::download |

## Pendientes

- No hay una matriz completa de permisos por rol, operación y módulo.
- No se demuestra separación granular entre médicos de una misma clínica.
- No se observa autorización por propietario o expediente para DownloadController.
- No se observa auditoría de denegaciones, lecturas o cambios clínicos.
