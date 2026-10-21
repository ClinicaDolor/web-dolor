<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class M02GateProbeStatement extends PDOStatement
{
    private function __construct(private mixed $value) {}

    public static function withValue(mixed $value): self
    {
        return new self($value);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->value;
    }
}

final class M02GateProbePdo extends PDO
{
    public function __construct(private string $database, private int $port) {}

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return match ($query) {
            'SELECT DATABASE()' => M02GateProbeStatement::withValue($this->database),
            'SELECT @@port' => M02GateProbeStatement::withValue($this->port),
            default => false,
        };
    }
}

$adapter = new App\Services\ConversationalM02Adapter();
$method = new ReflectionMethod($adapter, 'isAuthorizedStructuredWriteDatabase');
$method->setAccessible(true);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$oldEnv = [$_ENV['DB_HOST'] ?? null, $_ENV['DB_PORT'] ?? null];
try {
    $_ENV['DB_HOST'] = 'localhost';
    $_ENV['DB_PORT'] = '3306';
    $assert($method->invoke($adapter, new M02GateProbePdo('bd_clinica', 3306)) === true, 'Exact local bd_clinica should be accepted');
    $assert($method->invoke($adapter, new M02GateProbePdo('clinica_dolor_demo_20261010_final', 3306)) === true, 'Exact synthetic DB should remain accepted');
    $assert($method->invoke($adapter, new M02GateProbePdo('bd_clinica_backup', 3306)) === false, 'Similar database names must be rejected');
    $assert($method->invoke($adapter, new M02GateProbePdo('bd_clinica', 3307)) === false, 'Unexpected MySQL port must be rejected');
    $_ENV['DB_HOST'] = 'db.example.invalid';
    $assert($method->invoke($adapter, new M02GateProbePdo('bd_clinica', 3306)) === false, 'Non-local configured host must be rejected');
    echo "M02_LOCAL_STRUCTURED_WRITE_GATE=PASS\n";
} finally {
    if ($oldEnv[0] === null) unset($_ENV['DB_HOST']); else $_ENV['DB_HOST'] = $oldEnv[0];
    if ($oldEnv[1] === null) unset($_ENV['DB_PORT']); else $_ENV['DB_PORT'] = $oldEnv[1];
}
