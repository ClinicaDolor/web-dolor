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

    /**
     * Classifies uncertainty expressed by a patient without assigning a
     * clinical value. This is intentionally a pure, transient classification:
     * the current M03 contract supports either the additive semantic columns
     * or the explicitly canonical textual fallback documented in v1.1.
     */
    public static function classifyM03Unknown(string $utterance): ?string
    {
        $normalized = mb_strtolower(trim($utterance), 'UTF-8');
        if ($normalized === '') return null;

        if (preg_match('/\bno\s+recuerdo\b|\bno\s+me\s+acuerdo\b|\bno\s+tengo\s+memoria\b/u', $normalized) === 1) {
            return 'NOT_REMEMBERED';
        }
        if (preg_match('/\bno\s+(?:lo\s+)?s[ée]\b|\bdesconozco\b|\bno\s+estoy\s+segur[oa]?\b|\bno\s+tengo\s+claro\b|\bno\s+conozco(?:\s+mi)?\b/u', $normalized) === 1) {
            return 'UNKNOWN';
        }
        return null;
    }

    /**
     * Blood group and Rh are factual inputs, not binary questions. Resolve
     * them from the official question context rather than hard-coding row IDs
     * in the interpretation path.
     */
    public static function isM03BloodRhQuestion(array $question): bool
    {
        $themeId = (int)($question['idTema'] ?? $question['theme_id'] ?? 0);
        $label = mb_strtolower(trim((string)($question['pregunta'] ?? $question['question'] ?? '')), 'UTF-8');
        if ($themeId !== 7 || $label === '') return false;
        return preg_match('/\b(?:grupo\s+sangu[ií]neo|factor\s*rh)\b/u', $label) === 1;
    }

    /**
     * Normalizes the already-read module states into the single contract used
     * by the future conversation coordinator. This is a projection only: it
     * never queries, interprets or persists clinical data by itself.
     */
    public function buildUnifiedContract(array $moduleStates): array
    {
        $moduleOrder = ['M01', 'M02', 'M03', 'M04', 'M05', 'M06', 'M07', 'M08', 'M09'];
        $modules = [];
        $questionIndex = [];

        foreach ($moduleOrder as $moduleId) {
            $state = is_array($moduleStates[$moduleId] ?? null) ? $moduleStates[$moduleId] : [];
            $questions = match ($moduleId) {
                'M01' => $this->contractM01Questions($state),
                'M02' => $this->contractM02Questions($state),
                'M03' => $this->contractM03Questions($state),
                'M04' => $this->contractM04Questions($state),
                'M05' => $this->contractM05Questions($state),
                'M06' => $this->contractM06Questions($state),
                'M07' => $this->contractM07Questions($state),
                'M08' => $this->contractM08Questions($state),
                'M09' => $this->contractM09Questions($state, $moduleStates['M09Map'] ?? []),
                default => [],
            };
            $module = $this->contractModule($moduleId, $state, $questions);
            $modules[$moduleId] = $module;
            foreach ($questions as $question) {
                if (isset($question['stable_id'])) $questionIndex[$question['stable_id']] = $question;
            }
        }

        $welcomeText = 'Bienvenido a tu entrevista de historia clínica. Te realizaré algunas preguntas para conocer tus antecedentes y registrar tu información médica. Responde con tus propias palabras. Después podrás revisar y confirmar tus respuestas antes de guardarlas. Si necesitas una explicación, puedes utilizar el botón Ayuda. Comencemos.';
        $payload = [
            'schema' => 'conversation-contract-v1',
            'module_order' => $moduleOrder,
            'modules' => $modules,
            'question_index' => $questionIndex,
            'interview_progress' => $this->contractInterviewProgress($modules, $moduleOrder),
            'voice' => [
                'FIRST_START' => $welcomeText,
                'WELCOME' => $welcomeText,
                'INSTRUCTION' => 'Responde con tus propias palabras. Puedes pedir ayuda o repetir la instrucción.',
                'ERROR' => 'No fue posible completar esta operación. La pregunta permanece pendiente y puedes revisar el mensaje escrito para continuar.',
                'SESSION_EXPIRED' => 'Tu sesión expiró. Vuelve a iniciar sesión para continuar. No se consideran guardadas las respuestas que no fueron confirmadas.',
            ],
        ];
        $payload['version'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $payload;
    }

    /**
     * Progress used for the initial narration is derived only from the
     * server-side conversational projection. Read-only M01 is deliberately
     * excluded so historical data cannot turn a new
     * interview into a resume flow. A module that has answers but still
     * requires an explicit authorized finalization is also actionable
     * progress, even though it has no pending question stable_id.
     */
    private function contractInterviewProgress(array $modules, array $moduleOrder): array
    {
        $progressModuleIds = array_values(array_filter(
            $moduleOrder,
            static fn(string $moduleId): bool => $moduleId !== 'M01'
        ));
        $hasConfirmedResponses = false;
        $firstPendingModuleId = null;
        $firstPendingStableId = null;

        foreach ($progressModuleIds as $moduleId) {
            $module = is_array($modules[$moduleId] ?? null) ? $modules[$moduleId] : [];
            foreach (($module['questions'] ?? []) as $question) {
                if (!is_array($question)) continue;
                $response = is_array($question['response'] ?? null) ? $question['response'] : [];
                if (($response['persisted'] ?? false) === true
                    && in_array((string)($response['status'] ?? ''), ['documented', 'complete'], true)) {
                    $hasConfirmedResponses = true;
                }
            }
            $pendingIds = $module['completion']['pending_question_ids'] ?? [];
            $resolution = (string)($module['completion']['resolution'] ?? '');
            $resolverState = (string)($module['conversation_state']['state'] ?? '');
            $requiresAuthorizedAction = in_array($resolverState, [
                'READY_TO_FINALIZE',
                'AWAITING_PROFESSIONAL_REVIEW',
                'INCONSISTENT',
            ], true) || $resolution === 'AUTHORIZED_FINALIZATION_REQUIRED';
            $hasAnswerableQuestion = $resolverState === 'ANSWERABLE'
                && is_array($pendingIds)
                && $pendingIds !== [];
            if ($firstPendingModuleId === null
                && ($hasAnswerableQuestion || $requiresAuthorizedAction)) {
                $firstPendingModuleId = $moduleId;
                $firstPendingStableId = is_array($pendingIds) && $pendingIds !== []
                    ? (string)$pendingIds[0]
                    : null;
            }
        }

        return [
            'source' => 'server_confirmed_clinical_progress',
            'has_confirmed_responses' => $hasConfirmedResponses,
            'first_pending_module_id' => $firstPendingModuleId,
            'first_pending_stable_id' => $firstPendingStableId,
        ];
    }

    private function contractModule(string $moduleId, array $state, array $questions): array
    {
        $required = array_values(array_filter($questions, static fn(array $question): bool => ($question['required'] ?? true) !== false));
        $pending = array_values(array_filter($required, static fn(array $question): bool => !in_array(($question['response']['status'] ?? 'pending'), ['documented', 'read_only', 'not_applicable'], true)));
        $isReadOnly = $moduleId === 'M01';
        $writeEnabled = (bool)($state['write_enabled'] ?? false);
        $finalized = (bool)($state['finalized'] ?? false);
        $canonicalBlocked = $moduleId === 'M07' && !$writeEnabled && !$finalized;
        $writeBlocker = $state['write_blocker'] ?? null;
        // A finalized module is complete but no longer ordinarily editable;
        // the edit lock must not make a successfully closed module appear
        // blocked to navigation or progress consumers.
        $blocked = $canonicalBlocked || (!$isReadOnly && !$finalized && !$writeEnabled && $writeBlocker !== null);
        $complete = $required !== [] && $pending === []
            && ($isReadOnly || $canonicalBlocked || $finalized);
        $requiresAuthorizedFinalization = !$complete && $pending === [] && !$isReadOnly && !$canonicalBlocked;
        $status = $blocked ? 'blocked' : ($complete ? 'complete' : 'pending');
        $conversationState = $this->resolveConversationState(
            $moduleId,
            $state,
            $required,
            $pending,
            $isReadOnly,
            $canonicalBlocked,
            $writeEnabled,
            $finalized,
            $writeBlocker,
            $complete,
            $blocked,
            $requiresAuthorizedFinalization
        );

        return [
            'module_id' => $moduleId,
            'title' => (string)($state['title'] ?? $moduleId),
            'questions' => $questions,
            'version' => (string)($state['version'] ?? hash('sha256', json_encode($questions, JSON_UNESCAPED_UNICODE))),
            'state' => [
                'finalized' => $finalized,
                'write_enabled' => $writeEnabled,
                'write_blocker' => $writeBlocker,
                'blocked' => $blocked,
                'canonical_read' => (bool)($state['canonical_read'] ?? false),
                'finalization_enabled' => (bool)($state['finalization_enabled'] ?? ($moduleId !== 'M07')),
                'policy_source' => $state['policy_source'] ?? null,
            ],
            'completion' => [
                'status' => $status,
                'complete' => $complete && !$blocked,
                'can_advance' => $complete && !$blocked,
                'pending_question_ids' => array_values(array_map(static fn(array $question): string => (string)$question['stable_id'], $pending)),
                'resolution' => $requiresAuthorizedFinalization
                    ? 'AUTHORIZED_FINALIZATION_REQUIRED'
                    : (($conversationState['state'] ?? null) === 'AWAITING_PROFESSIONAL_REVIEW'
                        ? 'PROFESSIONAL_REVIEW_REQUIRED'
                        : (($conversationState['state'] ?? null) === 'INCONSISTENT' ? 'CONTRACT_INCONSISTENT' : null)),
                'message' => $blocked
                    ? ($moduleId === 'M07' ? 'La lectura M07 está disponible; la escritura canónica permanece bloqueada.' : 'Este módulo no está disponible para nuevas respuestas.')
                    : ($complete
                        ? 'Módulo revisado.'
                        : ($requiresAuthorizedFinalization
                            ? 'Las respuestas están documentadas, pero el servidor no confirmó la finalización. Usa el procedimiento autorizado del módulo.'
                            : 'Aún existen respuestas pendientes.')),
            ],
            // The resolver is a projection of the same adapter state and
            // question list above. It deliberately does not infer clinical
            // answers or create a second persistence model.
            'conversation_state' => $conversationState,
            'resume' => $blocked
                ? 'Al retomar, conserva el estado pendiente y no conviertas datos desconocidos en respuestas negativas.'
                : 'Al retomar, continúa desde la primera pregunta pendiente y conserva las respuestas ya persistidas.',
            'voice' => [
                'MODULE_COMPLETED' => $complete && !$blocked ? 'Este módulo quedó completo. Puedes continuar con el siguiente paso.' : 'Este módulo todavía tiene información pendiente.',
                'MODULE_BLOCKED' => $blocked
                    ? ($moduleId === 'M07' ? 'La lectura de medicamentos para el dolor está disponible, pero su guardado conversacional permanece bloqueado.' : 'Este módulo no puede completarse todavía.')
                    : 'Este módulo está disponible para continuar.',
                'RESUME' => $blocked
                    ? 'Al retomar, conserva la información pendiente. Los datos desconocidos no se registran como respuestas negativas.'
                    : 'Al retomar, continuarás desde la primera pregunta pendiente y conservarás las respuestas ya guardadas.',
                'SESSION_EXPIRED' => 'Tu sesión expiró. Vuelve a iniciar sesión para continuar.',
            ],
        ];
    }

    /**
     * Resolve the next safe conversational action from the server-confirmed
     * module projection. Only explicit review flags are accepted; a missing
     * flag is never interpreted as professional review.
     */
    private function resolveConversationState(
        string $moduleId,
        array $state,
        array $required,
        array $pending,
        bool $isReadOnly,
        bool $canonicalBlocked,
        bool $writeEnabled,
        bool $finalized,
        mixed $writeBlocker,
        bool $complete,
        bool $blocked,
        bool $requiresAuthorizedFinalization
    ): array {
        $pendingQuestion = $pending[0] ?? null;
        $pendingStableId = is_array($pendingQuestion) ? (string)($pendingQuestion['stable_id'] ?? '') : '';
        $reviewPending = ($state['professional_review_pending'] ?? false) === true
            || ($state['requires_professional_review'] ?? false) === true
            || ($state['review_required'] ?? false) === true;
        $blockerCode = is_scalar($writeBlocker) && trim((string)$writeBlocker) !== ''
            ? preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string)$writeBlocker))
            : null;

        if ($canonicalBlocked) {
            return [
                'state' => 'BLOCKED_PREREQUISITE',
                'action' => 'BLOCKED',
                'reason' => 'CANONICAL_MODULE_DISABLED',
                'question' => null,
                'stable_id' => null,
                'write_enabled' => false,
                'next_action' => 'WAIT_FOR_AUTHORIZED_MODULE_ENABLEMENT',
            ];
        }

        if ($finalized && $pending !== []) {
            return [
                'state' => 'INCONSISTENT',
                'action' => 'REPAIR_STATE',
                'reason' => 'FINALIZED_WITH_PENDING_QUESTIONS',
                'question' => null,
                'stable_id' => null,
                'write_enabled' => false,
                'next_action' => 'RELOAD_AND_VERIFY_PERSISTED_STATE',
            ];
        }

        if ($reviewPending) {
            return [
                'state' => 'AWAITING_PROFESSIONAL_REVIEW',
                'action' => 'AWAIT_REVIEW',
                'reason' => 'PROFESSIONAL_REVIEW_REQUIRED',
                'question' => $pendingQuestion,
                'stable_id' => $pendingStableId !== '' ? $pendingStableId : null,
                'write_enabled' => false,
                'next_action' => 'AWAIT_AUTHORIZED_REVIEW',
            ];
        }

        if ($pending !== []) {
            if (!$writeEnabled) {
                return [
                    'state' => 'BLOCKED_PREREQUISITE',
                    'action' => 'BLOCKED',
                    'reason' => $blockerCode ?: 'WRITE_UNAVAILABLE',
                    'question' => $pendingQuestion,
                    'stable_id' => $pendingStableId !== '' ? $pendingStableId : null,
                    'write_enabled' => false,
                    'next_action' => 'RESOLVE_WRITE_AUTHORIZATION',
                ];
            }

            return [
                'state' => 'ANSWERABLE',
                'action' => 'ANSWER',
                'reason' => 'PENDING_QUESTION',
                'question' => $pendingQuestion,
                'stable_id' => $pendingStableId !== '' ? $pendingStableId : null,
                'write_enabled' => true,
                'next_action' => 'CAPTURE_AND_CONFIRM_RESPONSE',
            ];
        }

        if ($isReadOnly) {
            return [
                'state' => 'FINALIZED',
                'action' => 'CONTINUE',
                'reason' => 'READ_ONLY_MODULE',
                'question' => null,
                'stable_id' => null,
                'write_enabled' => false,
                'next_action' => 'CONTINUE_TO_NEXT_AUTHORIZED_MODULE',
            ];
        }

        if ($requiresAuthorizedFinalization) {
            return [
                'state' => 'READY_TO_FINALIZE',
                'action' => 'FINALIZE',
                'reason' => 'AUTHORIZED_FINALIZATION_REQUIRED',
                'question' => null,
                'stable_id' => null,
                // This is an executable conversational action, not a
                // clinical question. Keep its identity separate from the
                // question index so clients cannot invent a question target.
                'action_id' => $moduleId . ':action:finalize',
                'voice_text' => 'Tus antecedentes familiares están registrados. Puedes finalizar esta sección y continuar.',
                'write_enabled' => $writeEnabled,
                'next_action' => 'EXPLICIT_FINALIZATION_AND_READBACK',
            ];
        }

        if ($complete) {
            return [
                'state' => 'FINALIZED',
                'action' => 'CONTINUE',
                'reason' => $isReadOnly ? 'READ_ONLY_COMPLETE' : 'PERSISTED_FINALIZATION',
                'question' => null,
                'stable_id' => null,
                'write_enabled' => false,
                'next_action' => 'CONTINUE_TO_NEXT_AUTHORIZED_MODULE',
            ];
        }

        if ($blocked || !$writeEnabled) {
            return [
                'state' => 'BLOCKED_PREREQUISITE',
                'action' => 'BLOCKED',
                'reason' => $blockerCode ?: 'WRITE_UNAVAILABLE',
                'question' => null,
                'stable_id' => null,
                'write_enabled' => false,
                'next_action' => 'RESOLVE_MODULE_PREREQUISITE',
            ];
        }

        return [
            'state' => 'INCONSISTENT',
            'action' => 'REPAIR_STATE',
            'reason' => 'REQUIRED_STATE_UNRESOLVED',
            'question' => null,
            'stable_id' => null,
            'write_enabled' => $writeEnabled,
            'next_action' => 'RELOAD_AND_VERIFY_PERSISTED_STATE',
        ];
    }

    private function contractQuestion(string $moduleId, string $stableId, string $original, array $data = []): array
    {
        $response = $data['response'] ?? $this->contractResponse(null, 'pending', false);
        $writeEnabled = (bool)($data['write_enabled'] ?? false);
        $visibleText = $data['visible_text'] ?? $original;
        $instruction = $data['instruction'] ?? 'Responde con tus propias palabras. Si no recuerdas el dato, indícalo para mantenerlo pendiente.';
        $help = $data['help'] ?? $this->contextualQuestionHelp($visibleText, $instruction);
        $clarification = $data['clarification'] ?? 'Necesito una respuesta más precisa antes de continuar.';
        $confirmation = $data['confirmation'] ?? [
            'required' => true,
            'can_confirm' => false,
            'backend_required' => true,
            'write_enabled' => $writeEnabled,
        ];

        $question = [
            'module_id' => $moduleId,
            'stable_id' => $stableId,
            'question_id' => $data['question_id'] ?? null,
            'theme_id' => $data['theme_id'] ?? null,
            'theme' => $data['theme'] ?? null,
            'variant' => $data['variant'] ?? null,
            'original_question' => $original,
            'visible_text' => $visibleText,
            'instruction' => $instruction,
            'help' => $help,
            'voice' => $data['voice'] ?? [
                'QUESTION' => $visibleText,
                'INSTRUCTION' => $instruction,
                'HELP' => $help,
                'OPTIONS' => 'Estas son las opciones disponibles para esta pregunta. Puedes revisarlas también en pantalla.',
                'CLARIFICATION' => $clarification,
                'CONFIRMATION' => 'Se necesita una confirmación específica antes de guardar este dato.',
                'SAVING' => 'La respuesta se está guardando. Espera la confirmación del servidor antes de continuar.',
                'SUCCESS' => 'La respuesta se guardó correctamente.',
                'ERROR' => 'No pudo completarse la operación. La pregunta permanece pendiente.',
                'RESUME' => 'Continuemos con tu entrevista.',
                'COMPLETION' => 'Este módulo quedó completo. Puedes continuar con el siguiente paso.',
                'MODULE_COMPLETED' => 'Este módulo quedó completo. Puedes continuar con el siguiente paso.',
                'MODULE_BLOCKED' => 'Esta sección no puede completarse todavía. Puedes continuar con las opciones disponibles en pantalla.',
                'SESSION_EXPIRED' => 'Tu sesión expiró. Vuelve a iniciar sesión para continuar.',
            ],
            'options' => $data['options'] ?? [],
            'dependencies' => $data['dependencies'] ?? [],
            'clarification_conditions' => $data['clarification_conditions'] ?? ['incomplete', 'ambiguous', 'contradictory'],
            'unknown_response' => $data['unknown_response'] ?? [
                'status' => 'pending',
                'persist' => false,
                'never_infer_negative' => true,
                'message' => 'El dato queda pendiente; no se registra como No.',
            ],
            'confirmation' => $confirmation,
            'advance' => $data['advance'] ?? [
                'after' => 'confirmed_backend_persistence',
                'on_failure' => 'stay_on_question',
                'enabled' => $writeEnabled,
            ],
            'response' => $response,
            'navigation' => $data['navigation'] ?? [
                'back' => true,
                'correction' => 'server_authorized',
                'allow_edit_is_intent_only' => true,
            ],
            'completion' => $data['completion'] ?? [
                'required' => true,
                'status' => in_array(($response['status'] ?? 'pending'), ['documented', 'read_only', 'not_applicable'], true) ? 'complete' : 'pending',
            ],
            'eligibility' => $data['eligibility'] ?? ['eligible' => true, 'state' => 'not_applicable'],
            'required' => ($data['required'] ?? true) !== false,
            'source' => $data['source'] ?? 'conversational_adapter',
        ];
        if (array_key_exists('active', $data)) $question['active'] = $data['active'] === true;
        if (array_key_exists('active_field', $data)) $question['active_field'] = $data['active_field'];
        return $question;
    }

    private function contractResponse(mixed $value, ?string $status = null, bool $persisted = false, array $extra = []): array
    {
        $normalizedStatus = $status;
        if ($normalizedStatus === null) {
            $normalizedStatus = ($value === null || $value === '') ? 'pending' : 'documented';
        }
        return array_merge([
            'status' => $normalizedStatus,
            'persisted' => $persisted,
            'value' => $value,
            'source' => $persisted ? 'clinical_record' : 'not_persisted',
        ], $extra);
    }

    private function contractConfirmation(array $state, bool $required = true): array
    {
        $writeEnabled = (bool)($state['write_enabled'] ?? false);
        return [
            'required' => $required,
            'can_confirm' => false,
            'backend_required' => true,
            'write_enabled' => $writeEnabled,
        ];
    }

    private function contextualQuestionHelp(string $visibleText, string $instruction): string
    {
        $question = trim($visibleText);
        $guidance = trim($instruction);
        if ($question === '' && $guidance === '') return 'No hay una explicación adicional disponible para esta pregunta. Puedes responder por escrito o pedir que se repita.';
        if ($question === '') return $guidance;
        return 'Esta ayuda se refiere a la pregunta: ' . $question . ' ' . $guidance;
    }

    private function m03QuestionHelp(array $row, string $instruction, array $options): string
    {
        $question = trim((string)($row['pregunta'] ?? ''));
        $theme = trim((string)($row['tema'] ?? ''));
        $parts = [];
        if ($theme !== '') $parts[] = 'Tema: ' . $theme . '.';
        if ($question !== '') $parts[] = 'Esta pregunta busca conocer: ' . $question;
        if ($options !== []) {
            $labels = array_values(array_filter(array_map(static fn($option): string => is_scalar($option) ? trim((string)$option) : '', $options)));
            if ($labels !== []) $parts[] = 'Elige una de las opciones visibles: ' . implode(', ', $labels) . '.';
        } elseif ($instruction !== '') {
            $parts[] = $instruction;
        }
        $parts[] = 'Si no recuerdas el dato o no estás seguro, dilo expresamente; no se interpretará como No.';
        return trim(implode(' ', $parts));
    }

    private function contractM01Questions(array $state): array
    {
        $questions = [];
        foreach (($state['fields'] ?? []) as $field) {
            $key = (string)($field['key'] ?? 'field');
            $value = (string)($field['value'] ?? '');
            $available = $value !== '' && $value !== 'No registrado';
            $questions[] = $this->contractQuestion('M01', 'M01:' . $key, (string)($field['label'] ?? $key), [
                'question_id' => $key,
                'visible_text' => (string)($field['label'] ?? $key),
                'instruction' => 'Esta información se muestra para orientarte; M01 es de solo lectura en la entrevista.',
                'help' => 'Puedes pedir que se repita el dato, pero esta interfaz no lo modifica.',
                'response' => $this->contractResponse($available ? $value : null, 'read_only', $available, ['read_only' => true]),
                'confirmation' => ['required' => false, 'can_confirm' => false, 'backend_required' => false, 'write_enabled' => false],
                'advance' => ['after' => 'reviewed', 'on_failure' => 'stay_on_question', 'enabled' => true],
                'navigation' => ['back' => true, 'correction' => 'traditional_authorized_flow', 'allow_edit_is_intent_only' => true],
                'completion' => ['required' => false, 'status' => 'read_only'],
                'required' => false,
            ]);
        }
        return $questions;
    }

    private function contractM02Questions(array $state): array
    {
        $questions = [];
        foreach (($state['records'] ?? []) as $record) {
            $disease = (string)($record['disease'] ?? 'antecedente familiar');
            $status = (string)($record['status'] ?? 'pending');
            $help = $disease === 'Diabetes Mellitus'
                ? 'Escribe qué familiar o familiares tienen diabetes. Puedes mencionar uno o varios, por ejemplo: “mi papá” o “mis padres y mi hermana”. Si conoces el tipo de diabetes, también puedes incluirlo, pero no es obligatorio.'
                : 'Escribe qué familiar o familiares tienen esta enfermedad. Puedes responder con tus propias palabras, por ejemplo: “mi papá y mi mamá” o “mis padres y mi hermana mayor”. Si ninguno tiene esta enfermedad, selecciona “No, guardar y continuar”. Si no lo recuerdas, puedes indicarlo; no se registrará como Sí ni como No y la respuesta quedará sin guardar.';
            $questions[] = $this->contractQuestion('M02', 'M02:family:' . $this->stableToken($disease), '¿Algún familiar tiene ' . mb_strtolower($disease, 'UTF-8') . '?', [
                'question_id' => $disease,
                'visible_text' => '¿Algún familiar tiene ' . mb_strtolower($disease, 'UTF-8') . '?',
                'instruction' => $disease === 'Diabetes Mellitus'
                    ? 'Describe con tus propias palabras qué familiar o familiares tienen diabetes. El tipo puede incluirse si lo conoces, pero es opcional.'
                    : 'Describe con tus propias palabras qué familiar o familiares tienen la enfermedad.',
                'help' => $help,
                'options' => ['detail' => ['Si', 'No'], 'relative_description' => 'free_text', 'diabetes_type' => $disease === 'Diabetes Mellitus' ? ['Tipo 1', 'Tipo 2', 'Gestacional'] : []],
                'dependencies' => [
                    ['when' => 'detail=Si', 'requires' => ['relative_description']],
                ],
                'response' => $this->contractResponse($record['detail'] ?? null, $status === 'documented' ? 'documented' : 'pending', $status === 'documented', [
                    'relative' => $record['relative'] ?? null,
                    'relatives' => $record['relatives'] ?? [],
                    'relative_description' => $record['relative_description'] ?? $record['legacy_relative_text'] ?? null,
                    'legacy_relative_text' => $record['legacy_relative_text'] ?? null,
                    'diabetes_type' => $record['diabetes_type'] ?? null,
                    'version' => $record['version'] ?? null,
                ]),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
            ]);
        }
        return $questions;
    }

    private function contractM03Questions(array $state): array
    {
        $questions = [];
        foreach (($state['questions'] ?? []) as $row) {
            $questionId = (int)($row['idPregunta'] ?? 0);
            $value = trim((string)($row['respuesta'] ?? ''));
            $storedState = strtoupper(trim((string)($row['semanticState'] ?? $row['estadoRespuesta'] ?? '')));
            $semanticState = in_array($storedState, ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'], true)
                ? $storedState
                : ($value === '' ? 'UNANSWERED' : 'LEGACY_UNCLASSIFIED');
            $persisted = (int)($row['idRespuesta'] ?? 0) > 0 && ($value !== '' || in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true));
            $instruction = (string)($row['tipo'] ?? '') === 'select' ? 'Responde Sí o No de forma clara.' : 'Escribe o dicta la respuesta completa antes de enviarla.';
            $options = (string)($row['tipo'] ?? '') === 'select' ? ['Si', 'No'] : [];
            $questions[] = $this->contractQuestion('M03', 'M03:question:' . $questionId, (string)($row['pregunta'] ?? ''), [
                'question_id' => $questionId,
                'theme_id' => (int)($row['idTema'] ?? 0),
                'theme' => (string)($row['tema'] ?? ''),
                'visible_text' => (string)($row['pregunta'] ?? ''),
                'instruction' => $instruction,
                'help' => $this->m03QuestionHelp($row, $instruction, $options),
                'options' => $options,
                'dependencies' => !empty($row['parentQuestionId']) ? [['requires_question' => 'M03:question:' . (int)$row['parentQuestionId'], 'when' => 'respuesta=Si']] : [],
                'clarification_conditions' => ['ambiguous', 'incomplete', 'unknown', 'dependency_pending'],
                'response' => $this->contractResponse($value !== '' ? $value : null, $persisted ? 'documented' : 'pending', $persisted, [
                    'semantic_state' => $semanticState,
                    'origin' => $row['origenRespuesta'] ?? null,
                    'state_at' => $row['estadoRespuestaAt'] ?? null,
                    'eligible' => (bool)($row['eligible'] ?? true),
                    'version' => $state['version'] ?? null,
                ]),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
                'completion' => ['required' => true, 'status' => $persisted ? 'complete' : 'pending'],
            ]);
        }
        return $questions;
    }

    private function contractM04Questions(array $state): array
    {
        $records = $state['records'] ?? [];
        $procedureRecords = array_values(array_filter(
            $records,
            static fn(array $record): bool => trim((string)($record['cirugia'] ?? '')) !== ''
        ));
        $negativeDeclaration = is_array($state['negative_declaration'] ?? null)
            ? $state['negative_declaration']
            : null;
        $negativeConfirmed = ($negativeDeclaration['estado'] ?? null) === 'NEGATIVE_DECLARED'
            && $procedureRecords === [];
        if ($negativeConfirmed) {
            $question = '¿Te realizaron alguna cirugía?';
            $instruction = 'Declaración negativa confirmada. Puedes corregirla mediante el flujo autorizado si necesitas registrar una cirugía.';
            $help = 'La respuesta «No» se conserva como declaración del paciente y no crea una cirugía ficticia.';
            return [$this->contractQuestion('M04', 'M04:record:new', $question, [
                'visible_text' => $question,
                'instruction' => $instruction,
                'help' => $help,
                'options' => ['No, continuar'],
                'clarification_conditions' => ['correction_requires_surgery_details'],
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state, false),
                'response' => $this->contractResponse('No', 'documented', true, [
                    'semantic_state' => 'NEGATIVE_DECLARED',
                    'origin' => (string)($negativeDeclaration['origen'] ?? 'PATIENT_DECLARATION'),
                    'negative_declaration' => true,
                    'version' => $state['version'] ?? null,
                ]),
                'advance' => [
                    'after' => 'confirmed_backend_persistence',
                    'on_failure' => 'stay_on_question',
                    'enabled' => true,
                ],
                'completion' => ['required' => true, 'status' => 'complete'],
                'source' => 'conversational_m04_negative_declaration',
                'voice' => $this->m04QuestionVoice($question, $instruction, $help),
            ])];
        }
        if ($procedureRecords === []) {
            $question = '¿Te realizaron alguna cirugía?';
            $instruction = 'Describe una cirugía con tus propias palabras o pulsa «No, continuar». Después de cada cirugía podrás agregar otra.';
            $help = 'Un año, mes/año o tiempo aproximado no se transforma en una fecha exacta.';
            return [$this->contractQuestion('M04', 'M04:record:new', '¿Te realizaron alguna cirugía?', [
                'instruction' => $instruction,
                'help' => $help,
                'options' => ['No, continuar'],
                'clarification_conditions' => ['missing_surgery', 'invalid_date', 'unknown_date'],
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
                'response' => $this->contractResponse(null, 'pending', false),
                'voice' => $this->m04QuestionVoice($question, $instruction, $help),
            ])];
        }

        $questions = [];
        foreach ($procedureRecords as $record) {
            $surgery = trim((string)($record['cirugia'] ?? ''));
            $date = trim((string)($record['fecha'] ?? ''));
            $observations = trim((string)($record['observaciones'] ?? ''));
            $precision = $date !== '' ? 'exact' : (preg_match('/\b(?:Año|Mes y año|Tiempo referido)/u', $observations) ? 'partial_or_relative' : 'unknown');
            $question = '¿Qué cirugía te realizaron y cuándo?';
            $instruction = 'Indica el nombre de la cirugía y la fecha sólo con la precisión que recuerdes.';
            $help = 'No se inventa día ni mes. Los datos parciales o relativos permanecen en observaciones.';
            $questions[] = $this->contractQuestion('M04', 'M04:record:' . (int)($record['id'] ?? 0), '¿Qué cirugía te realizaron y cuándo?', [
                'question_id' => (int)($record['id'] ?? 0),
                'instruction' => $instruction,
                'help' => $help,
                'clarification_conditions' => ['invalid_date', 'unknown_date', 'missing_surgery'],
                'response' => $this->contractResponse(['cirugia' => $surgery, 'fecha' => $date !== '' ? $date : null, 'observaciones' => $observations !== '' ? $observations : null], 'documented', true, ['temporal_precision' => $precision]),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
                'advance' => [
                    'after' => 'explicit_finish_additional_surgeries',
                    'on_failure' => 'stay_on_question',
                    'enabled' => true,
                ],
                'voice' => $this->m04QuestionVoice($question, $instruction, $help),
            ]);
        }
        if ((bool)($state['finalized'] ?? false)) {
            return $questions;
        }
        $continuationQuestion = '¿Quieres agregar otra cirugía?';
        $continuationInstruction = 'Puedes escribir otra cirugía o seleccionar «No, continuar» para pasar al siguiente módulo.';
        $continuationHelp = 'Puedes agregar otra cirugía o continuar cuando hayas terminado de registrar tus antecedentes.';
        $questions[] = $this->contractQuestion('M04', 'M04:record:new', $continuationQuestion, [
            'instruction' => $continuationInstruction,
            'help' => $continuationHelp,
            'options' => ['No, continuar'],
            'clarification_conditions' => ['missing_surgery', 'invalid_date', 'unknown_date'],
            'write_enabled' => (bool)($state['write_enabled'] ?? false),
            'confirmation' => $this->contractConfirmation($state),
            'response' => $this->contractResponse(null, 'pending', false),
            'advance' => [
                'after' => 'explicit_finish_additional_surgeries',
                'on_failure' => 'stay_on_question',
                'enabled' => true,
            ],
            'voice' => $this->m04QuestionVoice($continuationQuestion, $continuationInstruction, $continuationHelp),
        ]);
        return $questions;
    }

    private function m04QuestionVoice(string $question, string $instruction, string $help): array
    {
        return [
            'QUESTION' => $question,
            'INSTRUCTION' => $instruction,
            'HELP' => $help,
            'OPTIONS' => 'Puedes describir una cirugía o pulsar «No, continuar» cuando esa opción esté visible.',
            'CLARIFICATION' => 'Necesito una respuesta más precisa sobre tus antecedentes quirúrgicos.',
            'CONFIRMATION' => 'La respuesta está lista para validarse con el servidor.',
            'SAVING' => 'La respuesta se está guardando. Espera la confirmación del servidor antes de continuar.',
            'SUCCESS' => 'La respuesta se guardó correctamente.',
            'ERROR' => 'No pudo completarse la operación. La pregunta permanece pendiente.',
            'FIRST_START' => 'Ahora revisaremos tus antecedentes quirúrgicos.',
            'RESUME' => 'Continuemos con tus antecedentes quirúrgicos.',
            'COMPLETION' => 'Este módulo quedó completo. Puedes continuar con el siguiente paso.',
            'MODULE_COMPLETED' => 'Este módulo quedó completo. Puedes continuar con el siguiente paso.',
            'MODULE_BLOCKED' => 'Este módulo no puede completarse todavía. Puedes continuar con las opciones disponibles en pantalla.',
            'SESSION_EXPIRED' => 'Tu sesión expiró. Vuelve a iniciar sesión para continuar.',
        ];
    }

    private function contractM05Questions(array $state): array
    {
        $questions = [];
        foreach (($state['questions'] ?? []) as $row) {
            $questionId = (int)($row['question_id'] ?? 0);
            $value = trim((string)($row['respuesta'] ?? ''));
            $eligible = ($row['eligible'] ?? true) === true;
            $dependencyState = (string)($row['dependency_state'] ?? 'not_applicable');
            $parentQuestionId = (int)($row['dependency_parent_question_id'] ?? 0);
            $historicalPreserved = ($row['historical_preserved'] ?? false) === true;
            $notApplicable = !$eligible && $value === '';
            $responseStatus = $notApplicable ? 'not_applicable' : ($value !== '' ? 'documented' : 'pending');
            $questions[] = $this->contractQuestion('M05', 'M05:v1:' . $questionId, (string)($row['pregunta'] ?? ''), [
                'question_id' => $questionId,
                'theme_id' => (int)($row['theme_id'] ?? 0),
                'theme' => (string)($row['tema'] ?? ''),
                'variant' => 'v1',
                'instruction' => (string)($row['tipo'] ?? '') === 'select' ? 'Responde Sí o No.' : 'Expresa la respuesta completa y pulsa Enviar para validarla.',
                'options' => (string)($row['tipo'] ?? '') === 'select' ? ['Si', 'No'] : [],
                'dependencies' => $parentQuestionId > 0 ? [['when' => 'M05:v1:' . $parentQuestionId . '=Si', 'requires' => ['response']]] : [],
                'eligibility' => [
                    'eligible' => $eligible,
                    'state' => $dependencyState,
                    'parent_question_id' => $parentQuestionId > 0 ? $parentQuestionId : null,
                    'historical_preserved' => $historicalPreserved,
                ],
                'required' => !$notApplicable,
                'response' => $this->contractResponse($value !== '' ? $value : null, $responseStatus, (int)($row['response_id'] ?? 0) > 0 && $value !== '', [
                    'eligibility' => [
                        'eligible' => $eligible,
                        'state' => $dependencyState,
                        'historical_preserved' => $historicalPreserved,
                    ],
                ]),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
            ]);
        }
        foreach (($state['diseases'] ?? []) as $disease) {
            $diseaseId = (int)($disease['disease_id'] ?? 0);
            $value = trim((string)($disease['respuesta'] ?? ''));
            $name = (string)($disease['enfermedad'] ?? 'enfermedad');
            $questions[] = $this->contractQuestion('M05', 'M05:v2:' . $diseaseId, '¿Has sido diagnosticado con ' . $name . '?', [
                'question_id' => $diseaseId,
                'variant' => 'v2',
                'visible_text' => '¿Has sido diagnosticado con ' . $name . '?',
                'instruction' => 'Responde Sí o No. Algunas enfermedades requieren tipo y año de diagnóstico.',
                'options' => ['Si', 'No'],
                'dependencies' => in_array($name, ['Diabetes Mellitus', 'Enfermedad pulmonar'], true) ? [['when' => 'respuesta=Si', 'requires' => ['tipo', 'year_diagnostico']]] : [],
                'response' => $this->contractResponse($value !== '' ? $value : null, $value !== '' ? 'documented' : 'pending', (int)($disease['response_id'] ?? 0) > 0 && $value !== '', ['year_diagnostico' => $disease['year_diagnostico'] ?? null]),
                'write_enabled' => (bool)($state['disease_write_enabled'] ?? $state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation(['write_enabled' => (bool)($state['disease_write_enabled'] ?? $state['write_enabled'] ?? false)]),
            ]);
        }
        return $questions;
    }

    private function contractM06Questions(array $state): array
    {
        $records = $state['records'] ?? [];
        if (($state['finalized'] ?? false) === true && $records === []) {
            return [$this->contractQuestion('M06', 'M06:current-medication:new', '¿Tomas actualmente algún medicamento?', [
                'visible_text' => '¿Tomas actualmente algún medicamento?',
                'instruction' => 'No se registró medicación actual. Esta declaración quedó confirmada por el servidor.',
                'response' => $this->contractResponse(null, 'not_applicable', true, [
                    'source' => 'clinical_finalization',
                    'semantic_state' => 'NEGATIVE_DECLARED',
                ]),
                'write_enabled' => false,
                'confirmation' => $this->contractConfirmation(['write_enabled' => false], false),
            ])];
        }
        if ($records === []) {
            return [$this->contractQuestion('M06', 'M06:current-medication:new', '¿Tomas actualmente algún medicamento?', [
                'visible_text' => '¿Tomas actualmente algún medicamento?',
                'instruction' => 'Responde Sí o No. Si respondes Sí, la entrevista solicitará nombre, motivo de uso, duración, dosis y médico, uno por uno.',
                'help' => 'Si falta un campo, la entrevista solicitará sólo esa aclaración.',
                'clarification_conditions' => ['missing_field', 'ambiguous'],
                'options' => ['Sí', 'No, continuar'],
                'response' => $this->contractResponse(null, 'pending', false),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
            ])];
        }
        $questions = [];
        foreach ($records as $record) {
            $fields = [];
            foreach (['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'] as $field) $fields[$field] = trim((string)($record[$field] ?? ''));
            $pendingFields = is_array($record['pending_fields'] ?? null)
                ? array_values(array_intersect(['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'], $record['pending_fields']))
                : array_values(array_filter(array_keys($fields), static fn(string $field): bool => $fields[$field] === ''));
            $fieldStates = is_array($record['field_states'] ?? null) ? $record['field_states'] : [];
            $complete = $pendingFields === [] && $fields['medicamento'] !== '';
            $fieldLabels = [
                'medicamento' => '¿Qué medicamento tomas?',
                'descripcion' => '¿Para qué lo utilizas?',
                'tiempo_uso' => '¿Desde cuándo lo utilizas?',
                'dosis' => '¿Qué dosis utilizas?',
                'medico' => '¿Qué médico te lo indicó?',
            ];
            $missingField = $pendingFields[0] ?? null;
            $questionText = $missingField !== null ? $fieldLabels[$missingField] : '¿Quieres agregar otro medicamento?';
            $questions[] = $this->contractQuestion('M06', 'M06:current-medication:' . (int)($record['id'] ?? 0), $questionText, [
                'visible_text' => $questionText,
                'question_id' => (int)($record['id'] ?? 0),
                'instruction' => 'Conserva medicamento, motivo de uso, duración, dosis y médico que lo indicó.',
                'clarification_conditions' => ['missing_field', 'ambiguous'],
                'response' => $this->contractResponse($fields, $complete ? 'documented' : 'pending', $complete && (int)($record['id'] ?? 0) > 0, ['missing' => $pendingFields, 'field_states' => $fieldStates, 'omissions' => $record['omissions'] ?? []]),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
            ]);
        }
        if (($state['finalized'] ?? false) !== true && !array_filter($records, static function (array $record): bool {
            if (is_array($record['pending_fields'] ?? null)) return $record['pending_fields'] !== [];
            foreach (['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'] as $field) {
                if (trim((string)($record[$field] ?? '')) === '') return true;
            }
            return false;
        })) {
            $questions[] = $this->contractQuestion('M06', 'M06:current-medication:new', '¿Quieres agregar otro medicamento?', [
                'visible_text' => '¿Quieres agregar otro medicamento?',
                'variant' => 'add_another',
                'instruction' => 'Puedes agregar otro medicamento o elegir No, continuar para finalizar M06.',
                'options' => ['Sí, agregar otro', 'No, continuar'],
                'response' => $this->contractResponse(null, 'pending', false),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
            ]);
        }
        return $questions;
    }

    private function contractM07Questions(array $state): array
    {
        $questions = [];
        $active = is_array($state['active_question'] ?? null) ? $state['active_question'] : null;
        $writeEnabled = (bool)($state['write_enabled'] ?? false);
        foreach (($state['categories'] ?? []) as $category) {
            foreach (($category['items'] ?? []) as $item) {
                $questionId = (int)($item['question_id'] ?? 0);
                $status = (string)($item['status'] ?? 'pending');
                $stableId = 'M07:question:' . $questionId;
                $isActive = $writeEnabled && $active !== null
                    && ($active['stable_id'] ?? null) === $stableId
                    && $status === 'pending';
                $otherFlow = !empty($item['other_flow']);
                $questions[] = $this->contractQuestion('M07', 'M07:question:' . $questionId, (string)($item['question'] ?? ''), [
                    'question_id' => $questionId,
                    'theme_id' => (int)($category['theme_id'] ?? 0),
                    'theme' => (string)($category['theme'] ?? ''),
                    'variant' => $otherFlow ? 'other_medication' : 'catalog_medication',
                    'instruction' => $otherFlow
                        ? 'Responde Sí o No. Si respondes Sí, registra el nombre y después documenta cada dato solicitado.'
                        : 'Responde Sí o No. Si respondes Sí, completa los datos solicitados para este medicamento antes de guardar.',
                    'help' => $otherFlow
                        ? 'Los medicamentos adicionales se registran en su estructura canónica vinculada a esta pregunta.'
                        : 'Un dato desconocido permanece pendiente; nunca se interpreta como una respuesta negativa.',
                    'options' => $active['options'] ?? ['respuesta' => ['Si', 'No'], 'resultados' => ['Funciono', 'No funciono', 'Tuve efectos adversos'], 'consumo' => ['Si', 'No']],
                    'dependencies' => [['when' => 'respuesta=Si', 'requires' => ['dosis', 'resultados', 'consumo']], ['when' => 'resultados=Tuve efectos adversos', 'requires' => ['descripcion']]],
                    'response' => $this->contractResponse($item['fields'] ?? [], $status, (int)($item['response_id'] ?? 0) > 0 && $status === 'complete', [
                        'pending_reason' => $item['pending_reason'] ?? null,
                        'canonical_states' => $item['canonical_states'] ?? [],
                        'efficacy' => $item['efficacy'] ?? [],
                        'tolerability' => $item['tolerability'] ?? [],
                        'source' => $item['source'] ?? 'CATALOG',
                        'version' => $item['version'] ?? null,
                    ]),
                    'active' => $isActive,
                    'active_field' => $isActive ? ($active['field'] ?? 'respuesta') : null,
                    'visible_text' => $isActive ? (string)($active['prompt'] ?? $item['question'] ?? '') : (string)($item['question'] ?? ''),
                    'write_enabled' => $isActive,
                    'confirmation' => ['required' => true, 'can_confirm' => $isActive, 'backend_required' => true, 'write_enabled' => $isActive, 'blocked_reason' => $isActive ? null : ($state['write_blocker'] ?? 'M07_CANONICAL_DISABLED')],
                    'advance' => ['after' => 'canonical_write_and_independent_readback', 'on_failure' => 'stay_on_question', 'enabled' => $isActive],
                ]);
            }
        }
        return $questions;
    }

    private function contractM08Questions(array $state): array
    {
        $questions = [];
        foreach (($state['items'] ?? []) as $item) {
            $treatmentId = (int)($item['id_tratamiento'] ?? 0);
            $fields = is_array($item['fields'] ?? null) ? $item['fields'] : $item;
            $status = (string)($item['status'] ?? 'pending');
            $questions[] = $this->contractQuestion('M08', 'M08:treatment:' . $treatmentId, '¿Utilizaste ' . (string)($item['tratamiento'] ?? 'este tratamiento') . '?', [
                'question_id' => $treatmentId,
                'instruction' => 'Responde No, o Sí indicando resultado; describe efectos adversos cuando corresponda.',
                'options' => ['utilizo' => ['Si', 'No'], 'resultado' => ['Funciono', 'No funciono', 'Tuve efectos adversos']],
                'dependencies' => [['when' => 'utilizo=Si', 'requires' => ['resultado']], ['when' => 'resultado=Tuve efectos adversos', 'requires' => ['descripcion']]],
                'response' => $this->contractResponse($fields, $status, $status === 'complete' || $status === 'documented'),
                'write_enabled' => (bool)($state['write_enabled'] ?? false),
                'confirmation' => $this->contractConfirmation($state),
            ]);
        }
        return $questions;
    }

    private function contractM09Questions(array $state, array $mapState): array
    {
        $evaluation = is_array($state['evaluation'] ?? null) ? $state['evaluation'] : null;
        $evaluationComplete = $evaluation !== null && trim((string)($evaluation['dolor'] ?? '')) !== '';
        $questions = [$this->contractQuestion('M09', 'M09:evaluation', '¿Cómo describirías tu evaluación actual del dolor?', [
            'instruction' => 'Indica si tienes dolor y, si respondes Sí, completa tiempo, descripción e incremento.',
            'options' => ['dolor' => ['Si', 'No']],
            'dependencies' => [['when' => 'dolor=Si', 'requires' => ['tiempo_dolor', 'descripcion', 'incremento']]],
            'response' => $this->contractResponse($evaluation, $evaluationComplete ? 'documented' : 'pending', $evaluationComplete, ['version' => $state['version'] ?? null]),
            'write_enabled' => (bool)($state['write_enabled'] ?? false),
            'confirmation' => $this->contractConfirmation($state),
        ])];
        $images = is_array($mapState['images'] ?? null) ? $mapState['images'] : [];
        $mapAvailable = !empty($images['frente']['available']) || !empty($images['espalda']['available']);
        $questions[] = $this->contractQuestion('M09', 'M09:body-map', '¿Deseas señalar en el mapa dónde sientes el dolor?', [
            'instruction' => 'Utiliza el editor corporal existente para marcar, corregir y revisar la vista previa antes de confirmar.',
            'help' => 'El mapa permanece como componente visual PNG; no se convierten palabras en regiones anatómicas.',
            'response' => $this->contractResponse($images, $mapAvailable ? 'documented' : 'pending', $mapAvailable, ['visual_component' => 'existing_body_map_png']),
            'confirmation' => $this->contractConfirmation($state),
            'required' => false,
            'completion' => ['required' => false, 'status' => $mapAvailable ? 'complete' : 'optional_pending'],
            'source' => 'existing_visual_component',
        ]);
        return $questions;
    }

    private function stableToken(string $value): string
    {
        $token = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
        return $token !== '' ? $token : 'item';
    }

    public function interpret(int $patientId, int $questionId, string $utterance): array
    {
        $utterance = trim($utterance);
        if ($patientId < 1 || $questionId < 1 || $utterance === '') {
            throw new \InvalidArgumentException('La respuesta no puede estar vacía.');
        }

        // Interpretation must resolve the same complete M03 catalog that the
        // conversational view displays. Restricting this read to theme 1 made
        // earlier questions from later themes visible but impossible to answer.
        $m03Model = $this->model ?: new AntecedentesNoPatologicosModel();
        $questions = $m03Model->obtenerPreguntasEntrevista($patientId, 0);
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
        $unknownState = self::classifyM03Unknown($utterance);
        if ($unknownState !== null) {
            $this->emitTrace('M03', 'semantic_classification', 'unknown_or_not_remembered', false, $started, $unknownState);
            if ($m03Model->m03SemanticStateAvailable()) {
                return [
                    'mode' => 'deterministic',
                    'kind' => 'm03_proposal',
                    'target' => 'm03',
                    'question_id' => $questionId,
                    'theme_id' => (int)$question['idTema'],
                    'question' => (string)$question['pregunta'],
                    'structured_value' => null,
                    'semantic_state' => $unknownState,
                    'interpretation' => $unknownState === 'NOT_REMEMBERED' ? 'Dato no recordado' : 'Dato desconocido',
                    'needs_clarification' => false,
                    'requires_confirmation' => false,
                    'can_confirm' => true,
                    'direct_save' => true,
                    'unknown_response' => ['status' => 'ready', 'persist' => true, 'never_infer_negative' => true],
                ];
            }
            $canonicalValue = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($unknownState);
            return [
                'mode' => 'deterministic',
                'kind' => 'm03_proposal',
                'target' => 'm03',
                'question_id' => $questionId,
                'theme_id' => (int)$question['idTema'],
                'question' => (string)$question['pregunta'],
                'structured_value' => $canonicalValue,
                'semantic_state' => $unknownState,
                'interpretation' => $canonicalValue,
                'needs_clarification' => false,
                'requires_confirmation' => false,
                'can_confirm' => true,
                'direct_save' => true,
                'unknown_response' => [
                    'status' => 'ready',
                    'persist' => true,
                    'storage' => 'respuesta_text',
                    'canonical_value' => $canonicalValue,
                    'never_infer_negative' => true,
                ],
            ];
        }
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
                    $this->validateProviderEvidence($proposal, 'M03', $utterance);
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

    /** M02 uses deterministic free-text safeguards; captureM02 remains the sole writer. */
    public function interpretM02(string $disease, string $utterance, array $context = []): array
    {
        $disease = trim($disease);
        $utterance = trim($utterance);
        if ($disease === '' || $utterance === '') {
            throw new \InvalidArgumentException('Antecedente incompleto.');
        }
        return (new ConversationalM02Adapter())->interpret($disease, $utterance, $context);
    }

    /**
     * Interprets M06 through the same provider boundary without writing.
     * The existing M06 adapter remains the deterministic fallback and the
     * existing captureM06 endpoint remains the only persistence path.
     */
    public function interpretM06(string $utterance, array $context = [], ?string $activeField = null): array
    {
        $utterance = trim($utterance);
        if ($utterance === '') {
            throw new \InvalidArgumentException('Indica los datos de la medicación.');
        }

        $context = $this->safeM06Context($context);
        $adapter = new ConversationalM06Adapter();
        $fallback = function () use ($adapter, $utterance, $context, $activeField): array {
            $proposal = $adapter->interpret($utterance, $activeField);
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
                'active_field' => $activeField,
                'allowed_values' => [],
            ]);
            $this->emitTrace('M06', 'provider_response', 'response_received', false, $started);
            try {
                $this->validateProviderProposal($proposal, 'M06');
                $this->validateProviderEvidence($proposal, 'M06', $utterance, $context);
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
        $questionId = (int)($question['idPregunta'] ?? $question['id'] ?? 0);
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

        if (self::isM03BloodRhQuestion($question) && preg_match('/^\s*(?:no|nunca)\s*[.!?]*\s*$/u', $normalized) === 1) {
            return [
                'structured_value' => null,
                'interpretation' => null,
                'clarification' => '“No” no representa un grupo sanguíneo ni un factor Rh. Indica el valor si lo conoces o di que no lo sabes o no lo recuerdas.',
            ];
        }

        $unknownState = self::classifyM03Unknown($utterance);
        if ($unknownState !== null || preg_match('/\b(?:tal vez|quizá|quizas|aproximadamente)\b/u', $normalized)) {
            if ($unknownState !== null) {
                $canonicalValue = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($unknownState);
                return [
                    'structured_value' => $canonicalValue,
                    'semantic_state' => $unknownState,
                    'interpretation' => $canonicalValue,
                    'unknown_response' => [
                        'status' => 'ready',
                        'persist' => true,
                        'storage' => 'respuesta_text',
                        'canonical_value' => $canonicalValue,
                        'never_infer_negative' => true,
                    ],
                ];
            }
            return [
                'structured_value' => null,
                'interpretation' => null,
                'clarification' => 'No se guardó este dato porque el almacenamiento de respuestas desconocidas no está habilitado en esta instalación. No se interpretó como “No”. Indica un valor concreto o continúa cuando el esquema esté actualizado.',
                'unknown_state' => $unknownState ?: 'UNCERTAIN',
                'unknown_response' => [
                    'status' => 'pending',
                    'persist' => false,
                    'never_infer_negative' => true,
                    'code' => 'M03_SEMANTIC_STATE_SCHEMA_REQUIRED',
                ],
            ];
        }

        // Free-text M03 answers are preserved as entered; no clinical fact is inferred.
        return ['structured_value' => $utterance, 'interpretation' => $utterance];
    }

    private function normalizeProposal(array $proposal, array $question, string $mode, string $utterance): array
    {
        $value = $proposal['structured_value'] ?? null;
        $type = (string)$question['tipo'];
        $questionId = (int)($question['idPregunta'] ?? $question['id'] ?? 0);
        if (self::isM03BloodRhQuestion($question) && is_string($value)
            && preg_match('/^\s*(?:no|nunca)\s*[.!?]*\s*$/iu', $value) === 1) {
            $value = null;
            $proposal['clarification'] = '“No” no representa un grupo sanguíneo ni un factor Rh. Indica el valor si lo conoces o di que no lo sabes o no lo recuerdas.';
        }
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
            'semantic_state' => $proposal['semantic_state'] ?? null,
            'unknown_response' => is_array($proposal['unknown_response'] ?? null) ? $proposal['unknown_response'] : null,
            'needs_clarification' => $needsClarification,
            'clarification' => $needsClarification ? (string)($proposal['clarification'] ?? 'Necesito una respuesta más precisa antes de continuar.') : null,
            'can_confirm' => !$needsClarification,
            // Deterministic values already validated against the official
            // question contract can use the single-submit capture flow. A
            // provider result remains review-ineligible and is handled as a
            // clarification by the caller.
            'direct_save' => !$needsClarification
                && $mode === 'deterministic'
                && $value !== null
                && trim((string)$value) !== '',
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
            $fields = ['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'];
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

    /**
     * A valid JSON response is not enough: the provider must not introduce
     * clinical facts that are absent from the current utterance or the
     * already-confirmed conversational context.
     */
    private function validateProviderEvidence(array $proposal, string $module, string $utterance, array $context = []): void
    {
        $text = $this->normalizeEvidenceText($utterance);
        $contextText = $this->normalizeEvidenceText(implode(' ', array_map(
            static fn($value): string => is_scalar($value) ? (string)$value : '',
            $context
        )));

        if ($module === 'M03') {
            $value = $proposal['structured_value'] ?? null;
            if ($value === null || $value === '') return;
            $uncertain = preg_match('/\b(no\s+recuerdo|no\s+s[eé]|no\s+se|tal\s+vez|quiz[aá]s?|aproximadamente)\b/u', $text) === 1;
            $hasYes = preg_match('/\b(s[ií]|fumo|fum[oó]|ocasional|actualmente)\b/u', $text) === 1;
            $hasNo = preg_match('/\b(no|nunca|dej[eé]|suspend[ií])\b/u', $text) === 1;
            if ($uncertain || ($hasYes && $hasNo) || ($hasYes && $value === 'No') || ($hasNo && !$hasYes && $value === 'Si')) {
                throw new \RuntimeException('GEMINI_EVIDENCE_NOT_FOUND');
            }
            return;
        }

        if ($module === 'M02') {
            $detail = (string)($proposal['structured_value'] ?? '');
            $uncertain = preg_match('/\bno\s+(recuerdo|s[eé]|se)|no\s+estoy\s+seguro|no\s+tengo\s+claro|no\s+lo\s+s[eé]\b/u', $text) === 1;
            $hasYes = preg_match('/\b(s[ií]|tiene|padece|diagnosticad[oa])\b/u', $text) === 1;
            $hasNo = preg_match('/\b(no|ninguno|nunca|sin\s+antecedentes)\b/u', $text) === 1;
            if ($uncertain || ($hasYes && !$hasNo && $detail === 'No') || ($hasNo && !$hasYes && $detail === 'Si')) {
                throw new \RuntimeException('GEMINI_EVIDENCE_NOT_FOUND');
            }
            foreach (['relative', 'diabetes_type'] as $field) {
                $value = trim((string)($proposal[$field] ?? ''));
                if ($value !== '' && !$this->evidenceContains($value, $text, $contextText)) {
                    throw new \RuntimeException('GEMINI_EVIDENCE_NOT_FOUND');
                }
            }
            return;
        }

        if ($module === 'M06') {
            foreach (['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'] as $field) {
                $value = trim((string)($proposal['fields'][$field] ?? ''));
                if ($value !== '' && !$this->evidenceContains($value, $text, $contextText)) {
                    throw new \RuntimeException('GEMINI_EVIDENCE_NOT_FOUND');
                }
            }
        }
    }

    private function evidenceContains(string $value, string $utterance, string $context): bool
    {
        $needle = $this->normalizeEvidenceText($value);
        if ($needle === '') return true;
        foreach ([$utterance, $context] as $source) {
            if ($source !== '' && str_contains($source, $needle)) return true;
            $tokens = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tokens = array_values(array_filter($tokens, static fn(string $token): bool => mb_strlen($token, 'UTF-8') > 2 || preg_match('/\d/u', $token) === 1));
            if ($source !== '' && $tokens !== [] && count(array_filter($tokens, static fn(string $token): bool => str_contains($source, $token))) === count($tokens)) return true;
        }
        return false;
    }

    private function normalizeEvidenceText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        return trim((string)preg_replace('/\s+/u', ' ', $value));
    }

    private function safeM06Context(array $context): array
    {
        $safe = [];
        foreach (['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'] as $field) {
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
        foreach (['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'] as $field) {
            $candidate = $proposalFields[$field] ?? '';
            $fallback = $context[$field] ?? '';
            // A clarification carries empty placeholders for fields that were
            // not present in the current utterance. Preserve the confirmed
            // conversational context instead of replacing it with an empty
            // value on every turn.
            $value = is_scalar($candidate) && trim((string)$candidate) !== '' ? $candidate : $fallback;
            $fields[$field] = is_scalar($value) ? mb_substr(trim((string)$value), 0, 500, 'UTF-8') : '';
        }

        $missing = array_values(array_filter(array_keys($fields), static fn(string $field): bool => $fields[$field] === ''));
        $hasMedicationName = $fields['medicamento'] !== '';
        $normalized = [
            'mode' => $mode,
            'kind' => $hasMedicationName ? 'm06_proposal' : 'clarification',
            'target' => 'm06',
            'fields' => $fields,
            'context' => $fields,
            'structured_value' => $hasMedicationName ? $fields : null,
            'interpretation' => $hasMedicationName ? (string)($proposal['interpretation'] ?? 'Nombre de medicamento reconocido; los demás datos pueden completarse después.') : null,
            'missing' => $missing,
            'clarification' => $hasMedicationName ? null : (string)($proposal['clarification'] ?? 'Indica al menos el nombre del medicamento.'),
            'requires_confirmation' => false,
            'can_confirm' => $hasMedicationName,
            'direct_save' => $hasMedicationName,
            'partial' => $hasMedicationName && $missing !== [],
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
        $result = [
            'mode' => 'gemini', 'kind' => 'm02_read_only_proposal', 'disease' => $disease,
            'context' => $safeContext, 'detail' => $safeContext['detail'],
            'relative' => $safeContext['relative'], 'diabetes_type' => $safeContext['diabetes_type'],
            'can_confirm' => false, 'direct_save' => true, 'write_enabled' => false,
            'message' => 'Respuesta M02 validada; se guardará mediante el flujo autorizado del servidor.',
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
        $allowedDatabases = ['clinica_dolor_browser_20261012', 'clinica_dolor_demo_20261010_final'];
        $configured = (string)($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');
        if (!in_array($configured, $allowedDatabases, true)) return ['allowed' => false, 'reason' => 'database_allowlist_mismatch'];
        try {
            $effective = (string)Database::getInstance()->query('SELECT DATABASE()')->fetchColumn();
            return $effective === $configured && in_array($effective, $allowedDatabases, true)
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
            'GEMINI_EVIDENCE_NOT_FOUND' => 'unsupported_evidence',
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
