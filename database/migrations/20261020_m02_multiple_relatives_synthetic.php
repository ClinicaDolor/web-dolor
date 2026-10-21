<?php
declare(strict_types=1);

// One-shot, additive migration for the explicitly authorized synthetic DB.
// It intentionally does not use Database::getInstance(), whose .env default
// points at the ordinary local database.
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->load();

$expectedDatabase = 'clinica_dolor_demo_20261010_final';
$host = (string)($_ENV['DB_HOST'] ?? '');
$port = (string)($_ENV['DB_PORT'] ?? '3306');
$username = (string)($_ENV['DB_USERNAME'] ?? '');
$password = (string)($_ENV['DB_PASSWORD'] ?? '');
if ($host === '' || $username === '') {
    fwrite(STDERR, "M02 migration blocked: connection configuration unavailable.\n");
    exit(2);
}

try {
    $db = new PDO(
        "mysql:host={$host};port={$port};dbname={$expectedDatabase};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    if ((string)$db->query('SELECT DATABASE()')->fetchColumn() !== $expectedDatabase) {
        throw new RuntimeException('DATABASE_IDENTITY_MISMATCH');
    }

    $parent = $db->query("SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pc_antecedentes_familiares'")->fetch(PDO::FETCH_ASSOC);
    $id = $db->query("SELECT COLUMN_TYPE,IS_NULLABLE,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pc_antecedentes_familiares' AND COLUMN_NAME='id'")->fetch(PDO::FETCH_ASSOC);
    if (!$parent || ($parent['ENGINE'] ?? '') !== 'InnoDB' || !$id || ($id['COLUMN_TYPE'] ?? '') !== 'int(11)' || ($id['IS_NULLABLE'] ?? 'YES') !== 'NO' || !str_contains((string)($id['EXTRA'] ?? ''), 'auto_increment')) {
        throw new RuntimeException('PARENT_SCHEMA_INCOMPATIBLE');
    }
    $existing = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pc_antecedentes_familiares_parentescos'")->fetchColumn();
    if ($existing !== false) {
        throw new RuntimeException('CHILD_TABLE_ALREADY_EXISTS_REVIEW_REQUIRED');
    }

    $collation = preg_replace('/[^A-Za-z0-9_]/', '', (string)$parent['TABLE_COLLATION']);
    if ($collation === '') throw new RuntimeException('COLLATION_UNAVAILABLE');
    $db->exec("CREATE TABLE pc_antecedentes_familiares_parentescos (
        id_antecedente INT(11) NOT NULL,
        parentesco VARCHAR(24) NOT NULL,
        PRIMARY KEY (id_antecedente, parentesco),
        CONSTRAINT fk_m02_antecedente_parentesco
            FOREIGN KEY (id_antecedente) REFERENCES pc_antecedentes_familiares(id)
            ON UPDATE RESTRICT ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE={$collation}");

    $created = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pc_antecedentes_familiares_parentescos' AND ENGINE='InnoDB'")->fetchColumn();
    if ((int)$created !== 1) throw new RuntimeException('CHILD_TABLE_READBACK_FAILED');
    echo "M02_SYNTHETIC_MIGRATION=CREATED;DATABASE_VERIFIED=YES;TABLE_READBACK=PASS\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'M02_SYNTHETIC_MIGRATION=BLOCKED;REASON=' . preg_replace('/[^A-Z0-9_]/', '', $error->getMessage()) . "\n");
    exit(2);
}
