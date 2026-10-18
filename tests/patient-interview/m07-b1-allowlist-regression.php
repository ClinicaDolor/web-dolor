<?php

declare(strict_types=1);

/**
 * Pure argument/allowlist regression. It never loads .env and never opens a
 * database connection. The runtime validator keeps the same exact comparison.
 */

$parse = static function (array $arguments): array {
    $options = [];
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
        $options[substr($key, 2)] = $value;
    }
    return $options;
};

$isAllowed = static function (array $arguments) use ($parse): bool {
    $options = $parse($arguments);
    return array_key_exists('database', $options)
        && (string)$options['database'] === 'clinica_dolor_demo_20261010_final';
};

$cases = [
    'synthetic_database' => [
        'arguments' => ['--database=clinica_dolor_demo_20261010_final'],
        'expected' => true,
    ],
    'bd_clinica' => [
        'arguments' => ['--database=bd_clinica'],
        'expected' => false,
    ],
    'empty_database' => [
        'arguments' => ['--database='],
        'expected' => false,
    ],
    'wrong_synthetic_database' => [
        'arguments' => ['--database=clinica_dolor_browser_20261012'],
        'expected' => false,
    ],
    'malformed_argument' => [
        'arguments' => ['--database'],
        'expected' => false,
    ],
];

$checks = [];
foreach ($cases as $name => $case) {
    $accepted = $isAllowed($case['arguments']);
    $checks[$name] = $accepted === $case['expected'];
}

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "M07 B1 allowlist regression: FAIL\n" . json_encode($checks, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo json_encode([
    'status' => 'PASS_ALLOWLIST_EXACT',
    'checks' => $checks,
    'mysql_executed' => 'NO',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

