# Inventario de rutas

Fuente principal: app/Config/routes.php. El inventario distingue rutas observadas en el código de rutas y comportamiento confirmado mediante solicitudes locales.

## Web pública

GET /, /quienes-somos, /cursos, /que-es-dolor, /tipos-dolor, /dolor-agudo, /dolor-cronico, /dolor-perioperatorio, /dolor-cancer, /dolor-cancer-frecuencia, /dolor-cancer-causas, /dolor-cancer-preguntas-medico-decidir-tratamiento, /seguimiento-tratamiento-dolor, /dolor-cancer-cinco-prioridades, /dolor-cancer-endoscopia-paliativa, /neuralgia-postherpetica, /evaluacion-dolor, /cuidadores, /inyeccion-epidural, /neuroestimulacion, /como-se-inicia-un-tratamiento-con-opioides y /estrenimiento.

Controlador observado: WebController. La auditoría local verificó respuesta 200 en las 22 rutas.

## Portal clínico

Prefijo: /clinica.

- Inicio, acceso y login.
- Pacientes: listado, alta, edición, detalle y PIN.
- Recetas internas y externas.
- Notas subsecuentes.
- Laboratorio.
- COFEPRIS y folios.
- Nueve módulos y sus resúmenes.
- Perfil.
- Operaciones POST de módulos clínicos y comentarios.

Controladores observados: HomeController, LoginController, ClinicaController, ModulosController, NotaSubsecuenteController, RecetaController, LaboratorioController y CofeprisController.

## Portal paciente

Prefijo: /historia-clinica.

- Inicio, acceso y login.
- Vista de módulo por paciente.
- Las mismas operaciones comunes POST de módulos que se registran en el grupo compartido de routes.php.

La pertenencia se verifica en AuthMiddleware::assertPatientAccess para las rutas que reciben idPaciente y que fueron revisadas.

## Búsquedas y documentos

- /buscar y /buscar/*: consultas clínicas protegidas por Router; /buscar/web queda público.
- /pdf/receta/{idReceta}, /pdf/receta-externo/{id} y /pdf/cofepris/{id}: rutas POST/GET registradas y protegidas por sesión clínica en Router.
- /descargar/{ruta}/{archivo}: DownloadController::download, protegida por sesión clínica.
- /cerrar-sesion: cierre de sesión.

## Límites documentales

No existe un archivo de migraciones ni un contrato OpenAPI. Los parámetros, respuestas y permisos finos se deducen de controladores, modelos y vistas; requieren pruebas de aceptación por rol para considerarse definitivos.
