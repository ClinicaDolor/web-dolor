<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$adapterFile = file_get_contents($root . '/app/Services/ConversationalM04Adapter.php');
$m05File = file_get_contents($root . '/app/Services/ConversationalM05Adapter.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$routes = file_get_contents($root . '/app/Config/routes.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$migration = file_get_contents($root . '/database/migrations/20261018_m04_negative_declaration.sql');
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$assert(str_contains($adapterFile, 'pc_antecedentes_quirurgicos'), 'M04 no usa su tabla original.');
$assert(str_contains($adapterFile, 'public function interpret'), 'Falta interpretación M04.');
$assert(str_contains($adapterFile, 'ClinicalWriteEligibilityPolicy') && str_contains($adapterFile, 'write_enabled'), 'Falta la política de elegibilidad M04.');
$assert(str_contains($m05File, 'pac_respuestas_paciente_modulo_5'), 'M05 no usa sus respuestas originales.');
$assert(str_contains($controller, 'ConversationalM04Adapter'), 'El controlador no integra M04.');
$assert(str_contains($routes, "'/entrevista/capturar-m04', ['PatientInterviewController', 'captureM04']"), 'Falta endpoint exclusivo M04.');
$assert(str_contains($view, 'data-m04-interpret'), 'La interfaz no ofrece revisión M04.');
$m04 = new App\Services\ConversationalM04Adapter();
$positive = $m04->interpret('Me operaron de la vesícula hace cinco años');
$negative = $m04->interpret('Nunca he tenido cirugías');
$clarification = $m04->interpret('Sí');
$assert(($positive['kind'] ?? '') === 'm04_proposal' && ($positive['surgery'] ?? '') !== '', 'No se preparó propuesta M04 positiva.');
$assert(($negative['kind'] ?? '') === 'm04_negative_proposal', 'No se reconoció negación M04.');
$assert(($negative['direct_save'] ?? false) === true, 'La negación explícita no usa el flujo de guardado único.');
$assert(($negative['semantic_state'] ?? '') === 'NEGATIVE_DECLARED', 'Falta el estado negativo canónico.');
$assert(($clarification['kind'] ?? '') === 'clarification', 'No se solicitó aclaración M04.');
$assert(str_contains($adapterFile, 'pc_antecedentes_quirurgicos_estado'), 'Falta la representación durable de la declaración negativa.');
$assert(str_contains($adapterFile, 'M04_NEGATIVE_STATE_SCHEMA_REQUIRED'), 'El esquema no migrado no puede simular persistencia.');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS pc_antecedentes_quirurgicos_estado'), 'Falta la migración aditiva M04.');
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname=clinica_dolor_browser_20261012;charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$before = (int)$pdo->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos')->fetchColumn();
$readM04 = (new App\Services\ConversationalM04Adapter($pdo))->read(9302);
$readM05 = (new App\Services\ConversationalM05Adapter($pdo))->read(9302);
$after = (int)$pdo->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos')->fetchColumn();
$assert(($readM04['key'] ?? '') === 'M04' && is_array($readM04['records'] ?? null), 'Lectura M04 sintética inválida.');
$assert(($readM05['key'] ?? '') === 'M05' && is_array($readM05['questions'] ?? null), 'Lectura M05 sintética inválida.');
$assert($before === $after, 'La lectura M04 modificó registros.');
echo "M04 conversational contract: PASS (read, interpretation, clarification, negative-no-write)\n";
