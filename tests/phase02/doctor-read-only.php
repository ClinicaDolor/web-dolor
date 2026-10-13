<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8123';
$appKey = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, strlen('--app-key='));
}
if ($database !== 'clinica_dolor_demo_20261010_final') exit("D40 READ ONLY: FAIL (base no allowlisted).\n");
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$key = $appKey !== '' ? $appKey : (string) ($_ENV['APP_KEY'] ?? '');
if ($key === '') exit("D40 READ ONLY: FAIL (falta APP_KEY efímera).\n");
$pdo = Database::getInstance();
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) exit("D40 READ ONLY: FAIL (base activa distinta).\n");

$token = static fn(int $clinic): string => JWT::encode([
    'iat' => time(),
    'exp' => time() + 600,
    'id_clinica' => $clinic,
    'id_usuario' => 9991,
    'nombre' => 'Doctor sintético PHASE-02',
    'vista' => 'clinica',
    'rol' => 'Doctor',
], $key, 'HS256');

$request = static function (string $url, string $jwt = ''): array {
    $headers = $jwt === '' ? '' : "Cookie: CLINICAJWT={$jwt}\r\n";
    $body = file_get_contents($url, false, stream_context_create(['http' => [
        'method' => 'GET',
        'ignore_errors' => true,
        'follow_location' => 0,
        'header' => $headers,
    ]]));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
    return [$status, (string) $body];
};

$snapshot = static function (PDO $pdo): string {
    $tables = [
        'pc_paciente_evaluacion_dolor',
        'pac_respuestas_paciente_modulo_3',
        'pac_respuestas_paciente_modulo_5',
        'pac_enfermedades_respuestas_modulo_5',
        'pac_respuestas_paciente_modulo_7',
        'pac_respuestas_paciente_modulo_8',
        'pac_procedimientos_dolor_modulo_8',
        'pac_historia_clinica_finalizar',
    ];
    $values = [];
    foreach ($tables as $table) {
        $values[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE id_paciente IN (9301, 9302)")->fetchColumn();
    }
    return json_encode($values, JSON_THROW_ON_ERROR);
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $before = $snapshot($pdo);
    [$doctorStatus, $doctorBody] = $request($baseUrl . '/clinica/evaluacion-dolor/paciente/9301', $token(9101));
    [$summaryStatus, $summaryBody] = $request($baseUrl . '/clinica/resumen/evaluacion-dolor/paciente/9301', $token(9101));
    $after = $snapshot($pdo);
    $assert($doctorStatus === 200 && $summaryStatus === 200, 'D40 Doctor/resumen no renderizó: ' . $doctorStatus . '/' . $summaryStatus);
    $assert($before === $after, 'D40 Doctor/resumen modificó datos clínicos durante GET.');
    [$anonymousStatus] = $request($baseUrl . '/clinica/evaluacion-dolor/paciente/9301');
    $assert(in_array($anonymousStatus, [302, 401, 403], true), 'GET Doctor sin sesión no fue bloqueado.');
    [$crossStatus] = $request($baseUrl . '/clinica/evaluacion-dolor/paciente/9301', $token(9102));
    $assert($crossStatus === 403, 'GET Doctor cruzado no fue rechazado.');
    echo json_encode([
        'ok' => true,
        'database' => $database,
        'doctor_get' => 'PASS',
        'summary_get' => 'PASS',
        'clinical_delta_after_get' => 'PASS',
        'anonymous' => 'PASS',
        'cross_clinic' => 'PASS',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "D40 READ ONLY: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
}
