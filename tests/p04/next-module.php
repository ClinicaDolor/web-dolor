<?php

declare(strict_types=1);

use App\Config\Database;
use App\Models\PacienteModulosModelo;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$databaseName = getenv('P04_TEST_DATABASE') ?: '';

if ($databaseName !== $allowedDatabase) {
    fwrite(STDERR, "FAIL: P04_TEST_DATABASE no coincide con la base sintética allowlisted.\n");
    exit(2);
}

require $projectRoot . '/vendor/autoload.php';
Dotenv::createImmutable($projectRoot)->load();
$_ENV['DB_DATABASE'] = $databaseName;
$_SERVER['DB_DATABASE'] = $databaseName;

$pdo = Database::getInstance();
$model = new PacienteModulosModelo();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM pac_historia_clinica_finalizar WHERE id_paciente = 9301');
    $insert = $pdo->prepare(
        'INSERT INTO pac_historia_clinica_finalizar (id_modulo, id_paciente) VALUES (?, 9301)'
    );
    $insert->execute([1]);
    $insert->execute([2]);

    $assert(
        $model->siguienteModuloPendiente(9301, 2) === '/historia-clinica/antecedentes-personales-no-patologicos/paciente/9301',
        'P05 pendiente no fue resuelto desde P04.'
    );

    $insert->execute([3]);
    $assert(
        $model->siguienteModuloPendiente(9301, 2) === '/historia-clinica/antecedentes-personales-quirurgicos/paciente/9301',
        'No se saltó P05 ya finalizado.'
    );

    foreach (range(4, 9) as $moduleId) $insert->execute([$moduleId]);
    $assert(
        $model->siguienteModuloPendiente(9301, 2) === null,
        'Con todos los módulos posteriores finalizados no devolvió dashboard fallback.'
    );

    $pdo->exec('DELETE FROM pac_historia_clinica_finalizar WHERE id_paciente = 9301 AND id_modulo > 2');
    $pdo->prepare('UPDATE historia_clinica_modulos SET url = ? WHERE id = 3')
        ->execute(['https://example.invalid/open-redirect']);
    $assert(
        $model->siguienteModuloPendiente(9301, 2) === null,
        'Un slug de catálogo no permitido no activó el fallback seguro.'
    );

    $pdo->rollBack();
    echo "P04 next-module resolver: PASS\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "P04 next-module resolver: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
}
