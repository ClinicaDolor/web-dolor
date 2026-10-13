<?php

namespace App\Services;

use App\Config\Database;
use App\Models\AntecedentesNoPatologicosModel;

/**
 * Orchestrates the independent conversational M03 pilot.
 * It never writes: persistence remains behind the existing confirmation endpoint.
 */
final class ConversationalInterviewService
{
    private ?AntecedentesNoPatologicosModel $model;
    private ?ConversationalProvider $gemini;
    private $trace;
    private string $traceId;

    /**
     * The trace callback is intentionally opt-in and receives only bounded
     * stage/category metadata. It never receives utterances, prompts or
     * clinical records.
     */
    public function __construct(
        ?AntecedentesNoPatologicosModel $model = null,
        ?ConversationalProvider $gemini = null,
        ?callable $trace = null,
        ?string $traceId = null
    ) {
        $this->model = $model;
        $this->gemini = $gemini;
        $this->trace = $trace;
        $this->traceId = preg_replace('/[^A-Za-z0-9_-]/', '', $traceId ?: bin2hex(random_bytes(8))) ?: 'conversation';
    }

    public function interpret(int $patientId, int $questionId, string $utterance): array
    {
        $utterance = trim($utterance);
        if ($patientId < 1 || $questionId < 1 || $utterance === '') {
            throw new \InvalidArgumentException('La respuesta no puede estar vacía.');
        }

        $questions = ($this->model ?: new AntecedentesNoPatologicosModel())->obtenerPreguntasEntrevista($patientId, 1);
        $question = null;
        foreach ($questions as $candidate) {
            if ((int)$candidate['idPregunta'] === $questionId) {
                $question = $candidate;
                break;
            }
        }
        if (!$question) {
            throw new \DomainException('La pregunta no pertenece al tema autorizado.');
        }

        $started = microtime(true);
        $eligibility = $this->syntheticGeminiEligibility();
        if ($eligibility['allowed']) {
            $this->emitTrace('M03', 'provider_selection', 'gemini_selected', false, $started);
            $rejectionStage = null;
            try {
                $this->emitTrace('M03', 'provider_invocation', 'request_started', false, $started);
                $proposal = ($this->gemini ?: new GeminiProvider())->interpretConversation([
                    'module' => 'M03',
                    'question_code' => 'M03:' . $questionId,
                    'question' => (string)$question['pregunta'],
                    'question_type' => (string)$question['tipo'],
                    'utterance' => $utterance,
                    'allowed_values' => (string)$question['tipo'] === 'select' ? ['Si', 'No'] : [],
                ]);
                $this->emitTrace('M03', 'provider_response', 'response_received', false, $started);
                try {
                    $this->validateProviderProposal($proposal, 'M03');
                } catch (\Throwable $error) {
                    $rejectionStage = 'structured_json_validation';
                    $this->emitTrace('M03', 'structured_json_validation', 'rejected', true, $started, $this->classifyProviderFailure($error));
                    throw $error;
                }
                try {
                    $normalized = $this->normalizeProposal($proposal, $question, 'gemini', $utterance);
                } catch (\Throwable $error) {
                    $rejectionStage = 'schema_to_contract_mapping';
                    $this->emitTrace('M03', 'schema_to_contract_mapping', 'rejected', true, $started, $this->classifyProviderFailure($error));
                    throw $error;
                }
                $this->emitTrace('M03', 'orchestrator_acceptance', ($normalized['needs_clarification'] ?? false) ? 'clarification_accepted' : 'proposal_accepted', false, $started);
                return $normalized;
            } catch (\Throwable $error) {
                if ($rejectionStage === null) $this->emitTrace('M03', 'provider_invocation', 'rejected', true, $started, $this->classifyProviderFailure($error));
                $this->emitTrace('M03', 'fallback', 'provider_rejected', true, $started, $this->classifyProviderFailure($error));
                // A provider failure is deliberately non-fatal; deterministic interpretation remains available.
            }
        } else {
            $this->emitTrace('M03', 'provider_selection', 'gemini_not_selected', true, $started, $eligibility['reason']);
        }

        return $this->normalizeProposal($this->deterministic($question, $utterance), $question, 'deterministic', $utterance);
    }

    /**
     * Interpret M02 through the existing provider only in the isolated synthetic
     * database. The returned proposal never writes; captureM02 remains the sole
     * persistence path and still requires explicit confirmation.
     */
    public function interpretM02(string $disease, string $utterance, array $context = []): array
    {
        $disease = trim($disease);
        $utterance = trim($utterance);
        if ($disease === '' || $utterance === '') {
            throw new \InvalidArgumentException('Antecedente incompleto.');
        }

        $adapter = new ConversationalM02Adapter();
        $fallback = static fn (): array => $adapter->interpret($disease, $utterance, $context);
        $started = microtime(true);
        $eligibility = $this->syntheticGeminiEligibility();
        if (!$eligibility['allowed']) {
            $this->emitTrace('M02', 'provider_selection', 'gemini_not_selected', true, $started, $eligibility['reason']);
            return $fallback();
        }

        try {
            $this->emitTrace('M02', 'provider_selection', 'gemini_selected', false, $started);
            $this->emitTrace('M02', 'provider_invocation', 'request_started', false, $started);
            $proposal = ($this->gemini ?: new GeminiProvider())->interpretConversation([
                'module' => 'M02',
                'question_code' => 'M02:' . $disease,
                'question' => '¿Algún familiar tiene ' . $disease . '?',
                'question_type' => 'family_history',
                'utterance' => $utterance,
                'context' => $context,
                'allowed_values' => [
                    'Si', 'No', 'madre', 'padre', 'hermana', 'hermano', 'abuela', 'abuelo',
                    'hija', 'hijo', 'familia', 'Tipo 1', 'Tipo 2', 'Gestacional',
                ],
            ]);
            $this->emitTrace('M02', 'provider_response', 'response_received', false, $started);
            $this->validateProviderProposal($proposal, 'M02');
            $normalized = $this->normalizeM02Proposal($proposal, $disease, $utterance, $context);
            $this->emitTrace('M02', 'orchestrator_acceptance', ($normalized['kind'] ?? '') === 'clarification' ? 'clarification_accepted' : 'proposal_accepted', false, $started);
            return $normalized;
        } catch (\Throwable $error) {
            $this->emitTrace('M02', 'fallback', 'provider_rejected', true, $started, $this->classifyProviderFailure($error));
            // External interpretation must fail closed to the existing deterministic adapter.
            return $fallback();
        }
    }

    /**
     * Interprets M06 through the same provider boundary without writing.
     * The existing M06 adapter remains the deterministic fallback and the
     * existing captureM06 endpoint remains the only persistence path.
     */
    public function interpretM06(string $utterance, array $context = []): array
    {
        $utterance = trim($utterance);
        if ($utterance === '') {
            throw new \InvalidArgumentException('Indica los datos de la medicación.');
        }

        $context = $this->safeM06Context($context);
        $adapter = new ConversationalM06Adapter();
        $fallback = function () use ($adapter, $utterance, $context): array {
            $proposal = $adapter->interpret($utterance);
            return $this->normalizeM06Proposal(
                is_array($proposal['fields'] ?? null) ? $proposal['fields'] : [],
                $proposal,
                $utterance,
                $context,
                'deterministic'
            );
        };

        $started = microtime(true);
        $eligibility = $this->syntheticGeminiEligibility();
        if (!$eligibility['allowed']) {
            $this->emitTrace('M06', 'provider_selection', 'gemini_not_selected', true, $started, $eligibility['reason']);
            return $fallback();
        }

        try {
            $this->emitTrace('M06', 'provider_selection', 'gemini_selected', false, $started);
            $rejectionStage = null;
            $this->emitTrace('M06', 'provider_invocation', 'request_started', false, $started);
            $proposal = ($this->gemini ?: new GeminiProvider())->interpretConversation([
                'module' => 'M06',
                'question_code' => 'M06:current-medication',
                'question' => 'Indica medicamento, motivo de uso, duración, dosis y médico que lo indicó.',
                'question_type' => 'current_medication',
                'utterance' => $utterance,
                'context' => $context,
                'allowed_values' => [],
            ]);
            $this->emitTrace('M06', 'provider_response', 'response_received', false, $started);
            try {
                $this->validateProviderProposal($proposal, 'M06');
            } catch (\Throwable $error) {
                $rejectionStage = 'structured_json_validation';
                $this->emitTrace('M06', 'structured_json_validation', 'rejected', true, $started, $this->classifyProviderFailure($error));
                throw $error;
            }
            try {
                $normalized = $this->normalizeM06Proposal(
                    $proposal['fields'],
                    $proposal,
                    $utterance,
                    $context,
                    'gemini'
                );
            } catch (\Throwable $error) {
                $rejectionStage = 'schema_to_contract_mapping';
                $this->emitTrace('M06', 'schema_to_contract_mapping', 'rejected', true, $started, $this->classifyProviderFailure($error));
                throw $error;
            }
            $this->emitTrace('M06', 'orchestrator_acceptance', ($normalized['kind'] ?? '') === 'clarification' ? 'clarification_accepted' : 'proposal_accepted', false, $started);
            return $normalized;
        } catch (\Throwable $error) {
            if ($rejectionStage === null) $this->emitTrace('M06', 'provider_invocation', 'rejected', true, $started, $this->classifyProviderFailure($error));
            $this->emitTrace('M06', 'fallback', 'provider_rejected', true, $started, $this->classifyProviderFailure($error));
            // M06 remains usable locally when external interpretation fails.
            return $fallback();
        }
    }

    private function deterministic(array $question, string $utterance): array
    {
        $normalized = mb_strtolower($utterance, 'UTF-8');
        $type = (string)$question['tipo'];
        if (preg_match('/\b(qu[eé]|que) significa|explica|no entiendo/u', $normalized)) {
            return [
                'kind' => 'explanation',
                'explanation' => 'La pregunta busca registrar únicamente la respuesta indicada en el formulario clínico: ' . (string)$question['pregunta'],
            ];
        }
        if ($type === 'select') {
            $hasYes = preg_match('/\b(s[ií]|fumo|fum[oó]|ocasional|actualmente)\b/u', $normalized) === 1;
            $hasNo = preg_match('/\b(no|nunca|dej[eé]|suspend[ií])\b/u', $normalized) === 1;
            if ($hasYes && !$hasNo) return ['structured_value' => 'Si', 'interpretation' => 'Sí'];
            if ($hasNo && !$hasYes) return ['structured_value' => 'No', 'interpretation' => 'No'];
            return ['structured_value' => null, 'interpretation' => null, 'clarification' => '¿Podrías responder claramente sí o no?'];
        }

        if (preg_match('/\b(no recuerdo|no sé|no se|tal vez|quizá|quizas|aproximadamente)\b/u', $normalized)) {
            return ['structured_value' => null, 'interpretation' => null, 'clarification' => '¿Puedes indicar un dato más preciso o decir que no lo recuerdas?'];
        }

        // Free-text M03 answers are preserved as entered; no clinical fact is inferred.
        return ['structured_value' => $utterance, 'interpretation' => $utterance];
    }

    private function normalizeProposal(array $proposal, array $question, string $mode, string $utterance): array
    {
        $value = $proposal['structured_value'] ?? null;
        $type = (string)$question['tipo'];
        if ($type === 'select' && $value !== null && !in_array($value, ['Si', 'No'], true)) {
            $value = null;
        }
        if ($type !== 'select' && is_string($value) && trim($value) !== '' && $value !== $utterance) {
            // Free text is clinical source data; Gemini may classify it but may not rewrite it.
            $value = $utterance;
        }
        $needsClarification = !is_string($value) || trim($value) === '';
        $normalized = [
            'mode' => $mode,
            'kind' => $needsClarification ? 'clarification' : 'm03_proposal',
            'question_id' => (int)$question['idPregunta'],
            'theme_id' => (int)$question['idTema'],
            'question' => (string)$question['pregunta'],
            'explanation' => isset($proposal['explanation']) ? (string)$proposal['explanation'] : null,
            'structured_value' => $needsClarification ? null : $value,
            'interpretation' => $needsClarification ? null : (string)($proposal['interpretation'] ?? $value),
            'needs_clarification' => $needsClarification,
            'clarification' => $needsClarification ? (string)($proposal['clarification'] ?? 'Necesito una respuesta más precisa antes de continuar.') : null,
            'can_confirm' => !$needsClarification,
        ];
        if (isset($proposal['_gemini_meta']) && is_array($proposal['_gemini_meta'])) {
            $normalized['provider_meta'] = $proposal['_gemini_meta'];
        }
        return $normalized;
    }

    private function validateProviderProposal(array $proposal, string $module): void
    {
        if ($module === 'M06') {
            if (!is_array($proposal['fields'] ?? null)) {
                throw new \RuntimeException('GEMINI_INVALID_JSON');
            }
            $fields = ['descripcion', 'medicamento', 'tiempo_uso', 'dosis', 'medico'];
            foreach ($fields as $field) {
                if (!array_key_exists($field, $proposal['fields']) || !is_string($proposal['fields'][$field])) {
                    throw new \RuntimeException('GEMINI_INVALID_JSON');
                }
            }
            foreach (array_keys($proposal['fields']) as $field) {
                if (!in_array($field, $fields, true)) {
                    throw new \RuntimeException('GEMINI_INVALID_JSON');
                }
            }
        }
        $required = $module === 'M02'
            ? ['structured_value', 'interpretation', 'clarification', 'relative', 'diabetes_type', 'explanation']
            : ['structured_value', 'interpretation', 'clarification', 'explanation'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $proposal) || !is_string($proposal[$field])) {
                throw new \RuntimeException('GEMINI_INVALID_JSON');
            }
        }
    }

    private function safeM06Context(array $context): array
    {
        $safe = [];
        foreach (['descripcion', 'medicamento', 'tiempo_uso', 'dosis', 'medico'] as $field) {
            if (is_scalar($context[$field] ?? null)) {
                $value = trim((string)$context[$field]);
                if ($value !== '') $safe[$field] = mb_substr($value, 0, 500, 'UTF-8');
            }
        }
        return $safe;
    }

    private function normalizeM06Proposal(array $proposalFields, array $proposal, string $utterance, array $context, string $mode): array
    {
        $fields = [];
        foreach (['descripcion', 'medicamento', 'tiempo_uso', 'dosis', 'medico'] as $field) {
            $value = $proposalFields[$field] ?? ($context[$field] ?? '');
            $fields[$field] = is_scalar($value) ? mb_substr(trim((string)$value), 0, 500, 'UTF-8') : '';
        }

        $missing = array_values(array_filter(array_keys($fields), static fn(string $field): bool => $fields[$field] === ''));
        $normalized = [
            'mode' => $mode,
            'kind' => $missing === [] ? 'm06_proposal' : 'clarification',
            'target' => 'm06',
            'fields' => $fields,
            'context' => $fields,
            'structured_value' => $missing === [] ? $fields : null,
            'interpretation' => $missing === [] ? (string)($proposal['interpretation'] ?? 'Propuesta M06 preparada con los cinco campos expresados.') : null,
            'missing' => $missing,
            'clarification' => $missing === [] ? null : (string)($proposal['clarification'] ?? 'Para continuar, indica los campos faltantes sin inventar datos.'),
            'requires_confirmation' => $missing === [],
            'can_confirm' => false,
        ];
        if (isset($proposal['_gemini_meta']) && is_array($proposal['_gemini_meta'])) {
            $normalized['provider_meta'] = $proposal['_gemini_meta'];
        }
        return $normalized;
    }

    private function normalizeM02Proposal(array $proposal, string $disease, string $utterance, array $context): array
    {
        $allowedRelatives = ['madre', 'padre', 'hermana', 'hermano', 'abuela', 'abuelo', 'hija', 'hijo', 'familia'];
        $allowedTypes = ['Tipo 1', 'Tipo 2', 'Gestacional'];
        $safeContext = [
            'detail' => in_array($context['detail'] ?? null, ['Si', 'No'], true) ? $context['detail'] : null,
            'relative' => is_string($context['relative'] ?? null) && in_array(mb_strtolower(trim($context['relative']), 'UTF-8'), $allowedRelatives, true) ? mb_strtolower(trim($context['relative']), 'UTF-8') : null,
            'diabetes_type' => $disease === 'Diabetes Mellitus' && in_array($context['diabetes_type'] ?? null, $allowedTypes, true) ? $context['diabetes_type'] : null,
        ];
        $detail = trim((string)($proposal['structured_value'] ?? ''));
        $relative = mb_strtolower(trim((string)($proposal['relative'] ?? '')), 'UTF-8');
        $diabetesType = trim((string)($proposal['diabetes_type'] ?? ''));
        if ($detail !== '' && !in_array($detail, ['Si', 'No'], true)) throw new \RuntimeException('GEMINI_PROPOSAL_INVALID');
        if ($relative !== '' && !in_array($relative, $allowedRelatives, true)) throw new \RuntimeException('GEMINI_PROPOSAL_INVALID');
        if ($diabetesType !== '' && !in_array($diabetesType, $allowedTypes, true)) throw new \RuntimeException('GEMINI_PROPOSAL_INVALID');

        if ($detail !== '') $safeContext['detail'] = $detail;
        if ($relative !== '') $safeContext['relative'] = $relative;
        if ($diabetesType !== '') $safeContext['diabetes_type'] = $diabetesType;
        $normalizedUtterance = mb_strtolower($utterance, 'UTF-8');
        // A bare relative (for example, "Mi padre") identifies who the
        // patient is referring to, but does not answer the clinical yes/no
        // question. Do not let a provider over-infer a positive answer.
        $relativeOnly = preg_match('/^(?:(?:mi|el|la|un|una)\s+)?(?:madre|padre|hermana|hermano|abuela|abuelo|hija|hijo|familia)$/u', trim($normalizedUtterance)) === 1;
        if ($relativeOnly) {
            $safeContext['detail'] = null;
            $safeContext['relative'] = $safeContext['relative'] ?? null;
        }
        $uncertain = preg_match('/\bno\s+(recuerdo|sé|se)|no estoy seguro|no tengo claro|no lo sé\b/u', $normalizedUtterance) === 1;
        $asksExplanation = preg_match('/\b(qu[eé]|que)\s+significa|\bexplica|no entiendo/u', $normalizedUtterance) === 1;
        $negative = !$uncertain && preg_match('/\b(no|ninguno|nunca|sin antecedentes)\b/u', $normalizedUtterance) === 1;
        $hasRelative = $safeContext['relative'] !== null;
        $meta = isset($proposal['_gemini_meta']) && is_array($proposal['_gemini_meta']) ? $proposal['_gemini_meta'] : null;

        if ($uncertain || $asksExplanation || ($negative && $hasRelative)) {
            $message = $asksExplanation
                ? 'La pregunta registra si existe el antecedente y, si respondes Sí, qué familiar lo presenta.'
                : ($negative && $hasRelative
                    ? 'Primero mencionaste a ' . $safeContext['relative'] . ' y ahora indicas No. Confirma cuál respuesta deseas conservar.'
                    : 'No convertiré la duda en “No”. Puedes indicar Sí, No o responder después.');
            $result = [
                'mode' => 'gemini', 'kind' => 'clarification', 'disease' => $disease,
                'context' => $safeContext, 'detail' => $safeContext['detail'],
                'relative' => $safeContext['relative'], 'diabetes_type' => $safeContext['diabetes_type'],
                'clarification' => $message, 'clarification_reason' => $asksExplanation ? 'explanation' : ($negative && $hasRelative ? 'contradiction' : 'ambiguous'),
                'explanation' => $asksExplanation ? $message : null, 'can_confirm' => false, 'write_enabled' => false,
            ];
            if ($meta !== null) $result['provider_meta'] = $meta;
            return $result;
        }

        if ($safeContext['detail'] === null) {
            $result = [
                'mode' => 'gemini', 'kind' => 'clarification', 'disease' => $disease,
                'context' => $safeContext, 'detail' => null, 'relative' => $safeContext['relative'],
                'diabetes_type' => $safeContext['diabetes_type'],
                'clarification' => $safeContext['relative'] !== null
                    ? '¿' . ucfirst($safeContext['relative']) . ' tiene ' . mb_strtolower($disease, 'UTF-8') . '? Responde Sí o No.'
                    : '¿Hay antecedentes de ' . mb_strtolower($disease, 'UTF-8') . '? Indica Sí o No.',
                'clarification_reason' => $safeContext['relative'] !== null ? 'relative_first' : 'answer_required',
                'can_confirm' => false, 'write_enabled' => false,
            ];
            if ($meta !== null) $result['provider_meta'] = $meta;
            return $result;
        }
        if ($safeContext['detail'] === 'Si' && $safeContext['relative'] === null) {
            $result = [
                'mode' => 'gemini', 'kind' => 'clarification', 'disease' => $disease,
                'context' => $safeContext, 'detail' => 'Si', 'relative' => null,
                'diabetes_type' => $safeContext['diabetes_type'],
                'clarification' => '¿Qué familiar tiene ' . mb_strtolower($disease, 'UTF-8') . '?',
                'clarification_reason' => 'positive_first', 'can_confirm' => false, 'write_enabled' => false,
            ];
            if ($meta !== null) $result['provider_meta'] = $meta;
            return $result;
        }
        if ($disease === 'Diabetes Mellitus' && $safeContext['detail'] === 'Si' && $safeContext['diabetes_type'] === null) {
            $result = [
                'mode' => 'gemini', 'kind' => 'clarification', 'disease' => $disease,
                'context' => $safeContext, 'detail' => 'Si', 'relative' => $safeContext['relative'],
                'diabetes_type' => null, 'clarification' => '¿Es diabetes Tipo 1, Tipo 2 o gestacional?',
                'clarification_reason' => 'diabetes_type', 'can_confirm' => false, 'write_enabled' => false,
            ];
            if ($meta !== null) $result['provider_meta'] = $meta;
            return $result;
        }

        $result = [
            'mode' => 'gemini', 'kind' => 'm02_read_only_proposal', 'disease' => $disease,
            'context' => $safeContext, 'detail' => $safeContext['detail'],
            'relative' => $safeContext['relative'], 'diabetes_type' => $safeContext['diabetes_type'],
            'can_confirm' => false, 'write_enabled' => false,
            'message' => 'Propuesta preparada. La respuesta sólo se guarda después de una confirmación explícita.',
        ];
        if ($meta !== null) $result['provider_meta'] = $meta;
        return $result;
    }

    private function canUseSyntheticGemini(): bool
    {
        return $this->syntheticGeminiEligibility()['allowed'];
    }

    /** @return array{allowed: bool, reason: string} */
    private function syntheticGeminiEligibility(): array
    {
        if (!ClinicalSummarySecurity::externalEnabled()) return ['allowed' => false, 'reason' => 'external_disabled'];
        if (!ClinicalSummarySecurity::syntheticMode()) return ['allowed' => false, 'reason' => 'synthetic_mode_disabled'];
        $configured = (string)($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');
        if ($configured !== 'clinica_dolor_browser_20261012') return ['allowed' => false, 'reason' => 'database_allowlist_mismatch'];
        try {
            $effective = (string)Database::getInstance()->query('SELECT DATABASE()')->fetchColumn();
            return $effective === 'clinica_dolor_browser_20261012'
                ? ['allowed' => true, 'reason' => 'synthetic_database_verified']
                : ['allowed' => false, 'reason' => 'effective_database_mismatch'];
        } catch (\Throwable) {
            return ['allowed' => false, 'reason' => 'database_verification_failed'];
        }
    }

    private function emitTrace(string $module, string $stage, string $category, bool $fallback, float $started, ?string $reason = null): void
    {
        if ($this->trace === null) return;
        $event = [
            'execution_id' => $this->traceId,
            'module' => $module,
            'stage' => $stage,
            'category' => $category,
            'fallback' => $fallback,
            'duration_ms' => round((microtime(true) - $started) * 1000, 1),
        ];
        if ($reason !== null) $event['reason'] = $reason;
        try {
            ($this->trace)($event);
        } catch (\Throwable) {
            // Diagnostics must never change clinical behavior.
        }
    }

    private function classifyProviderFailure(\Throwable $error): string
    {
        $message = strtoupper($error->getMessage());
        if (preg_match('/^GEMINI_TRANSPORT_CURL_(\d+)$/', $message, $matches) === 1) {
            return match ((int)$matches[1]) {
                3 => 'malformed_url',
                5, 49 => 'proxy_error',
                6 => 'dns_resolution_error',
                7 => 'connection_error',
                28 => 'timeout_error',
                35, 51, 53, 60, 64 => 'tls_error',
                43, 45 => 'curl_configuration_error',
                default => 'transport_error',
            };
        }
        foreach ([
            'GEMINI_HTTP_' => 'http_error',
            'GEMINI_INVALID_JSON' => 'invalid_json',
            'GEMINI_RESPONSE_TOO_LARGE' => 'response_too_large',
            'GEMINI_PROPOSAL_INVALID' => 'clinical_value_invalid',
            'GEMINI_NOT_CONFIGURED' => 'provider_not_configured',
            'GEMINI_TRANSPORT_UNAVAILABLE' => 'transport_unavailable',
            'GEMINI_ENDPOINT_NOT_ALLOWED' => 'endpoint_not_allowed',
            'GEMINI_TRANSPORT' => 'transport_error',
        ] as $prefix => $category) {
            if (str_starts_with($message, $prefix)) return $category;
        }
        return 'internal_exception';
    }
}
