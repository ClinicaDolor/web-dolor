<?php

declare(strict_types=1);

/**
 * Shared synthetic P04 fixture helper.
 *
 * This helper is called only by the controlled PowerShell runners. It keeps
 * the credential mechanism in ClinicaModel::insertPacientePin and never emits
 * PINs or credential hashes.
 */
$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) continue;
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '');
    $options[substr($key, 2)] = $value;
}

$root = (string)($options['root'] ?? '');
$database = (string)($options['database'] ?? '');
$snapshotPath = (string)($options['snapshot'] ?? '');
$action = (string)($options['action'] ?? '');
$patientA = 9301;
$patientB = 9302;

$fail = static function (string $message): never {
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(2);
};

if ($root === '' || $database !== 'clinica_dolor_demo_20261010_final' || $snapshotPath === '') {
    $fail('Parámetros sintéticos incompletos.');
}
if (!in_array($action, ['preflight', 'prepare', 'create', 'restore'], true)) {
    $fail('Acción no permitida.');
}

$readEnv = static function (string $path): array {
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $values;
};

$env = $readEnv($root . DIRECTORY_SEPARATOR . '.env');
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (!array_key_exists($key, $env)) $fail('Falta configuración local de conexión.');
}
if (!in_array(strtolower((string)$env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true) || (string)$env['DB_PORT'] !== '3306') {
    $fail('La conexión no apunta al MySQL local autorizado.');
}

$connect = static function () use ($env, $database): PDO {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database),
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable) {
        throw new RuntimeException('No fue posible conectar con MySQL local.');
    }
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
        throw new RuntimeException('La base activa no coincide con la allowlist.');
    }
    return $pdo;
};

$syntheticPatients = static function (PDO $pdo) use ($patientA, $patientB): array {
    $statement = $pdo->prepare('SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
    $statement->execute([$patientA, $patientB]);
    $patients = $statement->fetchAll(PDO::FETCH_ASSOC);
    $isSynthetic = static function (array $patient): bool {
        $name = strtolower(implode(' ', [
            (string)($patient['nombres'] ?? ''),
            (string)($patient['apellido_paterno'] ?? ''),
            (string)($patient['apellido_materno'] ?? ''),
        ]));
        return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
    };
    $clinicIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id_clinica'], $patients)));
    if (count($patients) !== 2 || count(array_filter($patients, $isSynthetic)) !== 2 || count($clinicIds) !== 2) {
        throw new RuntimeException('Los pacientes no cumplen el fixture sintético independiente.');
    }
    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'id_clinica' => (int)$row['id_clinica'],
    ], $patients);
};

$accessColumns = static function (PDO $pdo): array {
    $columns = $pdo->query('SHOW COLUMNS FROM pc_paciente_acceso')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_map(static fn(array $row): string => (string)$row['Field'], $columns);
    if ($names === [] || count(array_filter($names, static fn(string $name): bool => preg_match('/^[A-Za-z0-9_]+$/', $name) === 1)) !== count($names)) {
        throw new RuntimeException('El catálogo de columnas de acceso no es válido.');
    }
    return $names;
};

$accessRows = static function (PDO $pdo) use ($patientA, $patientB): array {
    $statement = $pdo->prepare('SELECT * FROM pc_paciente_acceso WHERE paciente_id IN (?, ?) ORDER BY id');
    $statement->execute([$patientA, $patientB]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};

$rowsHash = static fn(array $rows): string => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

try {
    $pdo = $connect();
    $patients = $syntheticPatients($pdo);
    $columns = $accessColumns($pdo);
    $rows = $accessRows($pdo);

    if ($action === 'preflight') {
        $engineStatement = $pdo->prepare(
            'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $engineStatement->execute(['pac_respuestas_paciente_modulo_7']);
        $engine = (string)$engineStatement->fetchColumn();
        $duplicate = (int)$pdo->query(
            'SELECT COUNT(*) FROM (
                SELECT id_paciente, id_pregunta, COUNT(*) AS c
                FROM pac_respuestas_paciente_modulo_7
                GROUP BY id_paciente, id_pregunta
                HAVING c > 1
            ) duplicates'
        )->fetchColumn();
        $orphanPatientsStatement = $pdo->query(
            'SELECT COUNT(*)
             FROM pac_respuestas_paciente_modulo_7 r
             LEFT JOIN pc_paciente p ON p.id = r.id_paciente
             WHERE p.id IS NULL'
        );
        $orphanPatients = (int)$orphanPatientsStatement->fetchColumn();
        $orphanQuestionsStatement = $pdo->query(
            'SELECT COUNT(*)
             FROM pac_respuestas_paciente_modulo_7 r
             LEFT JOIN pac_preguntas_modulo_7 q ON q.id = r.id_pregunta
             WHERE q.id IS NULL'
        );
        $orphanQuestions = (int)$orphanQuestionsStatement->fetchColumn();
        $ok = $engine === 'InnoDB' && $duplicate === 0 && $orphanPatients === 0 && $orphanQuestions === 0;
        echo json_encode([
            'ok' => $ok,
            'action' => 'preflight',
            'database_effective' => $database,
            'engine_innodb' => $engine === 'InnoDB',
            'duplicate_groups' => $duplicate,
            'orphan_patient_rows' => $orphanPatients,
            'orphan_question_rows' => $orphanQuestions,
            'read_only' => true,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit($ok ? 0 : 2);
    }

    if ($action === 'prepare') {
        $snapshot = [
            'version' => 1,
            'database' => $database,
            'table' => 'pc_paciente_acceso',
            'patient_ids' => [$patientA, $patientB],
            'patients' => $patients,
            'columns' => $columns,
            'rows' => $rows,
            'rows_hash' => $rowsHash($rows),
        ];
        if (file_put_contents($snapshotPath, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            $fail('No se pudo crear el snapshot focal.');
        }
        echo json_encode([
            'ok' => true,
            'action' => 'prepare',
            'database_effective' => $database,
            'patients_verified' => true,
            'snapshot_created' => true,
            'access_rows_snapshotted' => count($rows),
            'write_scope' => 'pc_paciente_acceso:9301,9302',
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }

    if (!is_file($snapshotPath)) $fail('No existe el snapshot focal.');
    $snapshot = json_decode((string)file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
    if (($snapshot['database'] ?? '') !== $database || ($snapshot['table'] ?? '') !== 'pc_paciente_acceso' || ($snapshot['patient_ids'] ?? []) !== [$patientA, $patientB]) {
        $fail('El snapshot no corresponde al fixture autorizado.');
    }

    if ($action === 'create') {
        require $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $_ENV['DB_HOST'] = $env['DB_HOST'];
        $_ENV['DB_DATABASE'] = $database;
        $_ENV['DB_USERNAME'] = $env['DB_USERNAME'];
        $_ENV['DB_PASSWORD'] = $env['DB_PASSWORD'];
        $_SERVER['DB_HOST'] = $env['DB_HOST'];
        $_SERVER['DB_DATABASE'] = $database;
        $_SERVER['DB_USERNAME'] = $env['DB_USERNAME'];
        $_SERVER['DB_PASSWORD'] = $env['DB_PASSWORD'];
        $model = new App\Models\ClinicaModel();
        foreach ([$patientA, $patientB] as $patientId) {
            $response = $model->insertPacientePin(['idPaciente' => $patientId]);
            if ((int)($response['resultado'] ?? 0) !== 200) $fail('No se pudo preparar una credencial sintética.');
        }
        $active = $pdo->prepare('SELECT COUNT(DISTINCT pin) FROM pc_paciente_acceso WHERE paciente_id IN (?, ?) AND estatus = 0 AND fecha_expiracion > NOW()');
        $active->execute([$patientA, $patientB]);
        $distinctActive = (int)$active->fetchColumn();
        if ($distinctActive < 2) $fail('El fixture no alcanzó dos credenciales activas independientes.');
        echo json_encode([
            'ok' => true,
            'action' => 'create',
            'database_effective' => $database,
            'independent_active_credentials' => true,
            'distinct_active_count' => $distinctActive,
            'secrets_emitted' => false,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }

    $originalRows = $snapshot['rows'] ?? [];
    $originalColumns = $snapshot['columns'] ?? [];
    if ($originalColumns !== $columns) $fail('El esquema de acceso cambió después del snapshot.');
    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM pc_paciente_acceso WHERE paciente_id IN (?, ?)');
        $delete->execute([$patientA, $patientB]);
        if ($originalRows !== []) {
            $quoted = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $originalColumns));
            $placeholders = implode(',', array_fill(0, count($originalColumns), '?'));
            $insert = $pdo->prepare('INSERT INTO pc_paciente_acceso (' . $quoted . ') VALUES (' . $placeholders . ')');
            foreach ($originalRows as $row) {
                $insert->execute(array_map(static fn(string $column): mixed => $row[$column] ?? null, $originalColumns));
            }
        }
        $pdo->commit();
    } catch (Throwable) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $fail('La restauración focal no pudo completarse.');
    }

    $restoredRows = $accessRows($pdo);
    $restored = $rowsHash($restoredRows) === (string)($snapshot['rows_hash'] ?? '');
    echo json_encode([
        'ok' => $restored,
        'action' => 'restore',
        'database_effective' => $database,
        'restoration_hash_verified' => $restored,
        'secrets_emitted' => false,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($restored ? 0 : 2);
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
