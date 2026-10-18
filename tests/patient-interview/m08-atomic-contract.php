<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adapter = file_get_contents($root . '/app/Services/ConversationalM08TreatmentAdapter.php');
$model = file_get_contents($root . '/app/Models/ProcedimientosDolorModel.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$begin = strpos($adapter, '$db->beginTransaction()');
$loop = strpos($adapter, 'foreach ($order as $field => $edit)');
$commit = strpos($adapter, '$db->commit()');
$readback = strpos($adapter, '$after = $this->read($patientId);');
$assert($begin !== false && $loop !== false && $commit !== false && $begin < $loop && $loop < $commit, 'M08 transaction surrounds all field writes');
$assert(str_contains($adapter, '$model = new ProcedimientosDolorModel($db);'), 'M08 writes use the transaction PDO');
$assert(str_contains($adapter, 'if ($db->inTransaction()) $db->rollBack();'), 'M08 rollback on failure');
$assert($readback !== false && $commit < $readback, 'M08 readback occurs after commit');
$assert(str_contains($adapter, 'GET_LOCK(:name, 5)') && str_contains($adapter, 'RELEASE_LOCK'), 'M08 concurrency lock lifecycle');
$assert(str_contains($adapter, 'hash_equals((string)$state[\'version\'], $expectedVersion)'), 'M08 optimistic version guard');
$assert(str_contains($adapter, 'M08_FIELD_WRITE_FAILED') && str_contains($adapter, "'resultado' => 500"), 'M08 failure is fail-closed');
$assert(str_contains($adapter, "\$item['status'] = \$complete ? 'complete' : 'pending';"), 'M08 read exposes derived completion status');
$assert(str_contains($adapter, "\$item['fields'] = ["), 'M08 read exposes the same field projection used by the unified contract');
$assert(str_contains($model, 'public function __construct(?PDO $db = null)'), 'M08 model accepts PDO');
$assert(str_contains($model, 'return $this->p10Result(200, \'Sin cambios.\')'), 'M08 idempotent no-change result');

$save = strpos($view, 'const saveM08Proposal = async');
$advance = strpos($view, 'setStatus(\'Respuesta M08 guardada.\');advanceConversation();', $save === false ? 0 : $save);
$responseCheck = strpos($view, 'if(!response.ok||!data.resultado)', $save === false ? 0 : $save);
$assert($save !== false && $responseCheck !== false && $advance !== false && $responseCheck < $advance, 'M08 advances only after successful direct-save response');
$assert(!str_contains($view, 'data-m08-confirm'), 'M08 retains a redundant confirmation button');

echo "PASS M08 atomic, rollback, idempotency and navigation contract\n";
echo "M08_ATOMIC_CONTRACT: PASS\n";
