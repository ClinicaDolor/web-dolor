<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = file_get_contents($root . '/app/Config/routes.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$model = file_get_contents($root . '/app/Models/AntecedentesNoPatologicosModel.php');
$traditional = file_get_contents($root . '/app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php');
$dashboard = file_get_contents($root . '/app/views/paciente/index.php');
$chat = file_get_contents($root . '/app/views/paciente/entrevista.php');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert(str_contains($routes, "'/entrevista', ['PatientInterviewController', 'index']"), 'Falta la entrada GET independiente.');
$assert(str_contains($routes, "'/entrevista/capturar', ['PatientInterviewController', 'capture']"), 'Falta el endpoint de captura independiente.');
$assert(str_contains($controller, "new AuthMiddleware('historia-clinica')"), 'La entrevista no exige sesión Paciente.');
$assert(str_contains($controller, 'assertPatientAccess'), 'La entrevista no valida pertenencia del expediente.');
$assert(str_contains($controller, 'validCaptureCsrf'), 'La captura no valida CSRF.');
$assert(str_contains($model, 'obtenerPreguntasEntrevista'), 'Falta lectura compartida del catálogo.');
$assert(str_contains($model, 'capturarRespuestaEntrevista'), 'Falta persistencia compartida de la captura.');
$assert(str_contains($model, 'LEFT JOIN pac_respuestas_paciente_modulo_3'), 'La lectura conversacional no reutiliza respuestas M03.');
$assert(!str_contains($traditional, 'data-p05-mode'), 'El selector conversacional sigue incrustado en M03.');
$assert(!str_contains($traditional, 'p05GuidedMode'), 'M03 conserva ramas visuales de la entrevista.');
$assert(str_contains($dashboard, 'historia-clinica/entrevista'), 'El portal no enlaza la experiencia independiente.');
$assert(str_contains($chat, 'historia-clinica/entrevista/capturar'), 'La interfaz no usa su endpoint de captura.');
$assert(str_contains($chat, 'Piloto determinista'), 'La interfaz no identifica el modo sin proveedor.');
$assert(str_contains($routes, "'/entrevista/interpretar', ['PatientInterviewController', 'interpret']"), 'Falta el endpoint de interpretación conversacional.');
$assert(str_contains($service, 'deterministic'), 'Falta el fallback determinista del motor conversacional.');
$assert(str_contains($service, 'interpretConversation'), 'Falta el adaptador conversacional opcional.');
$assert(!str_contains($chat, 'GEMINI_API_KEY'), 'La vista expone una clave o configuración externa.');

echo "Chat separation contract: PASS (" . 15 . " assertions; no database writes)\n";
