<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Services/LocalDictationTicketService.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\LocalDictationTicketService;
use App\Services\ConversationalInterviewService;

$checks = 0;
$assert = static function (bool $condition, string $name) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL {$name}\n");
        exit(1);
    }
};
$rejects = static function (callable $operation): bool {
    try { $operation(); return false; } catch (Throwable) { return true; }
};

$secret = random_bytes(32);
$service = new LocalDictationTicketService($secret);
$now = 1_800_000_000;
$issued = $service->issue(9301, 'synthetic-jwt-not-a-real-credential', 'M02:family:diabetes', 'https://clinica.test:8443', $now);
$claims = $service->verify($issued['ticket'], 'https://clinica.test:8443', $now + 1);
$assert($claims['pid'] === 9301, 'patient claim roundtrip');
$assert($claims['question'] === 'M02:family:diabetes', 'stable question roundtrip');
$assert($issued['expires_at'] === $now + 30, 'short expiry');
$assert($rejects(fn() => $service->verify($issued['ticket'] . 'x', 'https://clinica.test:8443', $now + 1)), 'tampering rejected');
$assert($rejects(fn() => $service->verify($issued['ticket'], 'https://evil.test:8443', $now + 1)), 'wrong origin rejected');
$assert($rejects(fn() => $service->verify($issued['ticket'], 'https://clinica.test:8443', $now + 30)), 'expired ticket rejected');
$assert($rejects(fn() => $service->issue(9301, 'synthetic', 'M07:question:1', 'https://clinica.test:8443', $now)), 'M07 ticket rejected');
$assert($rejects(fn() => $service->issue(9301, 'synthetic', 'M03:alcohol:frequency', 'https://clinica.test', $now)), 'non-M02 clinical ticket rejected');
$assert($rejects(fn() => $service->issue(0, 'synthetic', 'M02:family:diabetes', 'https://clinica.test:8443', $now)), 'invalid patient rejected');
$normalIssued = $service->issue(9302, 'synthetic-jwt-not-a-real-credential', 'M02:family:diabetes-mellitus', 'https://clinica.test', $now);
$normalClaims = $service->verify($normalIssued['ticket'], 'https://clinica.test', $now + 1);
$assert($normalClaims['origin'] === 'https://clinica.test' && $normalClaims['question'] === 'M02:family:diabetes-mellitus', 'normal HTTPS M02 ticket roundtrip');
$assert($rejects(fn() => $service->issue(9302, 'synthetic', 'M02:family:diabetes-mellitus', 'https://clinica.test.evil', $now)), 'lookalike normal origin rejected');
$m03StableId = 'M03:question:17';
$m03Module = [
    'state' => ['write_enabled' => true, 'finalized' => false],
    'conversation_state' => ['state' => 'ANSWERABLE', 'stable_id' => $m03StableId],
];
$m03Pending = [
    'module_id' => 'M03', 'stable_id' => $m03StableId, 'question_id' => 17, 'write_enabled' => true,
    'response' => ['status' => 'pending', 'persisted' => false, 'eligible' => true],
];
$assert(LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $m03Pending, $m03Module), 'active eligible writable M03 question is authorized');
$m03Issued = $service->issue(9301, 'synthetic-jwt-not-a-real-credential', $m03StableId, 'https://clinica.test:8443', $now);
$m03Claims = $service->verify($m03Issued['ticket'], 'https://clinica.test:8443', $now + 1);
$assert($m03Claims['question'] === $m03StableId && $m03Claims['pid'] === 9301, 'M03 ticket is bound to its canonical question and patient');
$assert(!LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, [...$m03Pending, 'response' => ['status' => 'pending', 'persisted' => false, 'eligible' => false]], $m03Module), 'ineligible M03 question is rejected');
$assert(!LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $m03Pending, [...$m03Module, 'state' => ['write_enabled' => false, 'finalized' => false]]), 'read-only M03 module is rejected');
$m03Saved = [...$m03Pending, 'response' => ['status' => 'documented', 'persisted' => true, 'eligible' => true, 'editable' => true]];
$assert(LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $m03Saved, $m03Module), 'persisted eligible M03 answer can use its active authorized edit flow');
$assert(LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $m03Saved, [...$m03Module, 'conversation_state' => ['state' => 'ANSWERABLE', 'stable_id' => 'M03:question:18']]), 'persisted M03 answer is accepted for dictation only when the server marks it editable');
$m03SavedNotEditable = [...$m03Saved, 'response' => [...$m03Saved['response'], 'editable' => false]];
$assert(!LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $m03SavedNotEditable, [...$m03Module, 'conversation_state' => ['state' => 'ANSWERABLE', 'stable_id' => 'M03:question:18']]), 'persisted historical M03 response without explicit edit authorization is rejected');
$m03TobaccoQ1 = [
    'module_id' => 'M03', 'stable_id' => 'M03:question:1', 'question_id' => '1', 'theme_id' => '1', 'write_enabled' => true,
    'response' => ['status' => 'documented', 'persisted' => true, 'eligible' => true, 'editable' => true],
];
$m03TobaccoQ2 = [
    'module_id' => 'M03', 'stable_id' => 'M03:question:2', 'question_id' => 2, 'theme_id' => 1, 'write_enabled' => true,
    'response' => ['status' => 'pending', 'persisted' => false, 'eligible' => true],
];
$m03TobaccoEditModule = [
    'state' => ['write_enabled' => true, 'finalized' => false],
    'conversation_state' => ['state' => 'ANSWERABLE', 'stable_id' => 'M03:question:2'],
    'questions' => [$m03TobaccoQ1, $m03TobaccoQ2],
];
$assert(LocalDictationTicketService::isEligibleM03QuestionTarget('M03:question:1', $m03TobaccoQ1, $m03TobaccoEditModule),
    'the persisted Q1 tobacco answer immediately preceding the active pending Q2 is eligible for a clinical edit ticket');
$m03TobaccoQ3 = [
    'module_id' => 'M03', 'stable_id' => 'M03:question:3', 'question_id' => 3, 'theme_id' => 1, 'write_enabled' => true,
    'response' => ['status' => 'documented', 'persisted' => true, 'eligible' => true, 'editable' => true],
];
$nonAdjacentTobaccoModule = [...$m03TobaccoEditModule, 'questions' => [$m03TobaccoQ1, $m03TobaccoQ2, $m03TobaccoQ3]];
$assert(LocalDictationTicketService::isEligibleM03QuestionTarget('M03:question:3', $m03TobaccoQ3, $nonAdjacentTobaccoModule),
    'an explicitly server-authorized saved M03 history answer may receive an edit dictation ticket');

$contractService = new ConversationalInterviewService();
$baseModuleStates = array_fill_keys(['M01', 'M02', 'M04', 'M05', 'M06', 'M07', 'M08', 'M09', 'M09Map'], []);
$m03QuestionRow = [
    'idPregunta' => 17, 'idTema' => 1, 'tema' => 'Tabaquismo',
    'pregunta' => '¿Desde qué edad empezó a fumar?', 'tipo' => 'input',
    'idRespuesta' => null, 'respuesta' => '', 'semanticState' => 'UNANSWERED',
    'eligible' => true, 'parentQuestionId' => null,
];
$projectM03 = static function (bool $writeEnabled, array $questionRow) use ($contractService, $baseModuleStates): array {
    return $contractService->buildUnifiedContract($baseModuleStates + ['M03' => [
        'questions' => [$questionRow], 'version' => 'synthetic-m03-contract-v1',
        'write_enabled' => $writeEnabled, 'finalized' => false,
    ]]);
};
$writableM03Contract = $projectM03(true, $m03QuestionRow);
$writableM03Question = $writableM03Contract['question_index'][$m03StableId] ?? null;
$writableM03Module = $writableM03Contract['modules']['M03'] ?? null;
$assert(($writableM03Question['write_enabled'] ?? null) === true, 'M03 question projects explicit write_enabled from the authorized module policy');
$assert(LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $writableM03Question, $writableM03Module), 'projected active writable M03 question passes the unchanged ticket guard');
$readOnlyM03Contract = $projectM03(false, $m03QuestionRow);
$readOnlyM03Question = $readOnlyM03Contract['question_index'][$m03StableId] ?? null;
$readOnlyM03Module = $readOnlyM03Contract['modules']['M03'] ?? null;
$assert(($readOnlyM03Question['write_enabled'] ?? null) === false, 'M03 question remains explicitly non-writable when module policy is closed');
$assert(!LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $readOnlyM03Question, $readOnlyM03Module), 'read-only M03 projection remains rejected by the unchanged ticket guard');
$savedReadOnlyRow = [...$m03QuestionRow, 'idRespuesta' => 81, 'respuesta' => 'respuesta sintética', 'semanticState' => 'KNOWN'];
$savedReadOnlyContract = $projectM03(false, $savedReadOnlyRow);
$savedReadOnlyQuestion = $savedReadOnlyContract['question_index'][$m03StableId] ?? null;
$savedReadOnlyModule = $savedReadOnlyContract['modules']['M03'] ?? null;
$assert(($savedReadOnlyQuestion['response']['status'] ?? null) === 'documented'
    && ($savedReadOnlyQuestion['response']['persisted'] ?? false) === true
    && ($savedReadOnlyQuestion['write_enabled'] ?? true) === false
    && !LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $savedReadOnlyQuestion, $savedReadOnlyModule),
    'a persisted M03 response remains non-dictatable when its module does not authorize editing');
$ineligibleM03Contract = $projectM03(true, [...$m03QuestionRow, 'eligible' => false]);
$ineligibleM03Question = $ineligibleM03Contract['question_index'][$m03StableId] ?? null;
$ineligibleM03Module = $ineligibleM03Contract['modules']['M03'] ?? null;
$assert(($ineligibleM03Question['write_enabled'] ?? null) === true && !LocalDictationTicketService::isEligibleM03QuestionTarget($m03StableId, $ineligibleM03Question, $ineligibleM03Module), 'explicit question write flag does not bypass clinical eligibility');
$assert(!$rejects(fn() => $service->issue(9301, 'synthetic', 'M03:question:17', 'https://clinica.test:8443', $now)), 'valid M03 target accepted by ticket format');
$assert($rejects(fn() => $service->issue(9301, 'synthetic', 'M03:question:017', 'https://clinica.test:8443', $now)), 'noncanonical M03 target rejected');
$assert($rejects(fn() => $service->issue(9301, 'synthetic', 'M03:alcohol:frequency', 'https://clinica.test:8443', $now)), 'noncanonical M03 alias rejected');
$assert($rejects(fn() => $service->issue(9301, 'synthetic', 'M04:question:17', 'https://clinica.test:8443', $now)), 'other clinical modules remain excluded');
$diagnostic = $service->issueDiagnostic('synthetic-jwt-diagnostic', 'https://clinica.test:8443', $now);
$diagnosticClaims = $service->verifyDiagnostic($diagnostic['ticket'], 'https://clinica.test:8443', $now + 1);
$assert($diagnosticClaims['purpose'] === LocalDictationTicketService::DIAGNOSTIC_PURPOSE, 'diagnostic purpose explicit');
$assert(!isset($diagnosticClaims['pid']) && !isset($diagnosticClaims['question']), 'diagnostic ticket has no patient or question identifiers');
$assert($diagnostic['expires_at'] === $now + 30, 'diagnostic ticket short expiry');
$assert($rejects(fn() => $service->verify($diagnostic['ticket'], 'https://clinica.test:8443', $now + 1)), 'diagnostic ticket rejected by clinical verifier');
$assert($rejects(fn() => $service->issueDiagnostic('synthetic', 'https://clinica.test', $now)), 'diagnostic ticket cannot target normal application origin');
$assert($rejects(fn() => $service->verifyDiagnostic($diagnostic['ticket'], 'https://evil.test', $now + 1)), 'diagnostic ticket rejects wrong origin');
$assert($rejects(fn() => $service->verifyDiagnostic($diagnostic['ticket'], 'https://clinica.test:8443', $now + 30)), 'diagnostic ticket expires');

fwrite(STDOUT, json_encode(['status' => 'PASS', 'checks' => $checks, 'database' => 'NOT_CONNECTED'], JSON_THROW_ON_ERROR) . PHP_EOL);
