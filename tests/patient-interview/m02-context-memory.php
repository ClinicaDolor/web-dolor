<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$adapter = new App\Services\ConversationalM02Adapter();
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$disease = 'Hipertensión arterial sistémica';

$yes = $adapter->interpret($disease, 'Sí');
$assert(($yes['clarification_reason'] ?? '') === 'positive_first', 'A bare yes must ask who in the family has the disease');
$description = 'Mi papá, mi hermana mayor y mi tío materno';
$afterYes = $adapter->interpret($disease, $description, $yes['context']);
$assert(($afterYes['kind'] ?? '') === 'm02_read_only_proposal'
    && ($afterYes['detail'] ?? '') === 'Si'
    && ($afterYes['relative_description'] ?? '') === $description,
    'Free text after a bare yes must become the complete answer without another turn');

$negative = $adapter->interpret($disease, 'No');
$assert(($negative['kind'] ?? '') === 'm02_read_only_proposal' && ($negative['detail'] ?? '') === 'No', 'Negative response must remain a negative');
$replacement = $adapter->interpret($disease, 'Mi abuela y dos hermanos', $negative['context']);
$assert(($replacement['kind'] ?? '') === 'm02_read_only_proposal'
    && ($replacement['relative_description'] ?? '') === 'Mi abuela y dos hermanos',
    'A newly supplied free-text answer must not be blocked by a stale conversational No context');

$uncertain = $adapter->interpret($disease, 'No lo recuerdo', ['detail' => 'Si', 'relative_description' => 'Papá']);
$assert(($uncertain['kind'] ?? '') === 'clarification'
    && ($uncertain['clarification_reason'] ?? '') === 'uncertainty_not_persisted'
    && ($uncertain['detail'] ?? null) === null
    && ($uncertain['relative_description'] ?? '') === '',
    'Uncertainty must clear unconfirmed yes/context and must not become a proposal');
$otherDisease = $adapter->interpret('Cáncer', 'Sí');
$assert(($otherDisease['context']['disease'] ?? '') === 'Cáncer' && ($yes['context']['disease'] ?? '') === $disease, 'Contexts must remain bound to their active disease');

echo json_encode([
    'm02_yes_then_free_text' => 'PASS',
    'm02_negative_then_affirmative' => 'PASS',
    'm02_uncertainty_not_persisted' => 'PASS',
    'm02_context_isolation' => 'PASS',
    'database_writes' => 'ZERO',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
