<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Models/RecetaModel.php';

use App\Models\RecetaModel;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Controllers/ClinicaController.php');
$view = file_get_contents($root . '/app/views/clinica/pacientes-recetas.php');
$model = file_get_contents($root . '/app/Models/RecetaModel.php');

$assert(is_string($controller) && is_string($view) && is_string($model), 'target sources must be readable');

$createStart = is_string($controller) ? strpos($controller, 'public function pacienteInsertReceta') : false;
$createEnd = $createStart !== false ? strpos($controller, 'public function pacienteNotaSubsecuente', $createStart) : false;
$createSection = ($createStart !== false && $createEnd !== false)
    ? substr($controller, $createStart, $createEnd - $createStart)
    : '';

$writePosition = strpos($createSection, 'insertPacienteReceta');
$csrfPosition = strpos($createSection, 'validCaptureCsrf');
$authorizationPosition = strpos($createSection, 'assertPatientAccess');

$assert($csrfPosition !== false, 'create endpoint must validate CSRF');
$assert(str_contains($createSection, "HTTP_X_M03_CSRF"), 'create endpoint must accept the CSRF header');
$assert($csrfPosition !== false && $writePosition !== false && $csrfPosition < $writePosition, 'CSRF validation must precede the model write');
$assert($authorizationPosition !== false && $authorizationPosition < $writePosition, 'patient authorization must precede the model write');
$assert(is_string($view) && str_contains($view, 'data-receta-csrf='), 'view must expose a scoped CSRF token');
$assert(is_string($view) && str_contains($view, "'X-M03-CSRF': csrfToken"), 'view must send the CSRF header');
$assert(is_string($view) && str_contains($view, 'let recetaGuardando = false'), 'view must guard an in-flight submission');
$assert(is_string($view) && str_contains($view, 'button.disabled = isSaving'), 'submit button must be disabled while saving');
$assert(is_string($view) && str_contains($view, '.finally(() =>'), 'submit state must be restored after success or failure');

$xssPayload = '<p>Texto <strong>clínico</strong><script>alert(1)</script><img src="x" onerror="alert(2)"></p>';
$safeHtml = RecetaModel::sanitizeMedicationHtml($xssPayload);
$assert(str_contains($safeHtml, '<strong>clínico</strong>'), 'allowed clinical formatting must remain visible');
$assert(!str_contains(strtolower($safeHtml), '<script'), 'script elements must be removed');
$assert(!str_contains(strtolower($safeHtml), 'onerror'), 'event attributes must be removed');
$assert(!str_contains(strtolower($safeHtml), '<img'), 'unapproved media elements must be removed');
$assert(is_string($model) && str_contains($model, 'self::sanitizeMedicationHtml'), 'detail rendering must use the allowlist sanitizer');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Prescription security contract: PASS" . PHP_EOL;
