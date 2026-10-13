<?php

declare(strict_types=1);

$baseUrl = 'http://clinica.test';
$host = 'clinica.test';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--base-url=')) {
        $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    }
    if (str_starts_with($argument, '--host=')) {
        $host = substr($argument, strlen('--host='));
    }
}

$root = dirname(__DIR__, 2);
$fixtures = [
    [
        'source' => $root . '/public/assets/images/evaluacion-dolor/frente/Frente_M.png',
        'target' => $root . '/public/assets/images/evaluacion-dolor/frente/Frente_M_9301.png',
        'urls' => [
            '/assets/images/evaluacion-dolor/frente/Frente_M_9301.png',
            '/public/assets/images/evaluacion-dolor/frente/Frente_M_9301.png',
        ],
    ],
    [
        'source' => $root . '/public/assets/images/evaluacion-dolor/espalda/Espalda_M.png',
        'target' => $root . '/public/assets/images/evaluacion-dolor/espalda/Espalda_M_9301.png',
        'urls' => [
            '/assets/images/evaluacion-dolor/espalda/Espalda_M_9301.png',
            '/public/assets/images/evaluacion-dolor/espalda/Espalda_M_9301.png',
        ],
    ],
];

$result = ['ok' => false, 'checks' => [], 'cleanup' => 'pending'];
$check = static function (array &$result, string $name, bool $passed, string $detail = ''): void {
    $result['checks'][$name] = ['status' => $passed ? 'PASS' : 'FAIL'];
    if ($detail !== '') {
        $result['checks'][$name]['detail'] = $detail;
    }
};
$request = static function (string $url) use ($host): int {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "Host: {$host}\r\n",
            'timeout' => 8,
        ],
    ]);
    @file_get_contents($url, false, $context);
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            return (int) $matches[1];
        }
    }
    return 0;
};
$created = [];

try {
    foreach ($fixtures as $fixture) {
        if (is_file($fixture['target'])) {
            throw new RuntimeException('Fixture sintético ya existente; no se sobrescribe.');
        }
        if (!is_file($fixture['source']) || !copy($fixture['source'], $fixture['target'])) {
            throw new RuntimeException('No se pudo preparar el fixture sintético.');
        }
        $created[] = $fixture['target'];
    }

    foreach ($fixtures as $index => $fixture) {
        foreach ($fixture['urls'] as $variant => $path) {
            $status = $request($baseUrl . $path);
            $name = 'direct_' . ($index === 0 ? 'frente' : 'espalda') . '_' . $variant;
            $check($result, $name, $status === 403, 'HTTP ' . $status);
        }
    }

    $genericStatus = $request($baseUrl . '/assets/images/evaluacion-dolor/frente/Frente_M.png');
    $check($result, 'base_anatomica_publica', $genericStatus === 200, 'HTTP ' . $genericStatus);
    $result['ok'] = !array_filter($result['checks'], static fn(array $item): bool => $item['status'] === 'FAIL');
} catch (Throwable $exception) {
    $result['error'] = 'La prueba de acceso estático no pudo completarse.';
} finally {
    $cleanupOk = true;
    foreach ($created as $path) {
        if (is_file($path) && !unlink($path)) {
            $cleanupOk = false;
        }
    }
    $result['cleanup'] = $cleanupOk ? 'PASS' : 'FAIL';
}

$result['ok'] = ($result['ok'] ?? false) && $result['cleanup'] === 'PASS';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
