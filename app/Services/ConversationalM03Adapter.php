<?php

namespace App\Services;

use App\Config\Database;
use App\Models\AntecedentesNoPatologicosModel;
use App\Services\M03TobaccoResponsePolicy;
use PDO;

/** M03 adapter for the existing seven-theme catalog and response table. */
final class ConversationalM03Adapter
{
    /**
     * The first question of each conditional theme gates the remaining
     * questions in that theme. Tabaquismo is theme 1 in the official catalog;
     * omitting it here made questions 2-5 eligible even after question 1 was
     * answered "No".
     */
    private const PARENT_BY_THEME = [1 => 1, 2 => 6, 3 => 12, 4 => 16, 5 => 18, 6 => 21];
    /**
     * Explicit contextual-action allowlist from the official 25-question
     * M03 catalog. Question 5 has an explicitly approved declaration for a
     * patient who has not stopped smoking; Q11 accepts only UNKNOWN, which
     * records no cessation duration and does not assert that alcohol was stopped.
     * Assignments are semantic and server-defined, never based on browser text.
     */
    private const CONTEXTUAL_ACTIONS = [
        1 => ['theme_id' => 1, 'question_type' => 'select', 'action_type' => 'NEGATIVE', 'key' => 'M03_TOBACCO_NEGATIVE', 'label' => 'No, guardar y continuar', 'confirmation_label' => 'No'],
        2 => ['theme_id' => 1, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_TOBACCO_AGE_NOT_REMEMBERED', 'label' => 'No recuerdo, guardar y continuar', 'confirmation_label' => 'Dato no recordado'],
        3 => ['theme_id' => 1, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q3_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        4 => ['theme_id' => 1, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q4_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        5 => ['theme_id' => 1, 'question_type' => 'input', 'action_type' => 'NEGATIVE', 'key' => 'M03_Q5_NOT_STOPPED', 'label' => 'No, guardar y continuar', 'confirmation_label' => 'No he dejado de fumar'],
        6 => ['theme_id' => 2, 'question_type' => 'select', 'action_type' => 'NEGATIVE', 'key' => 'M03_Q6_NEGATIVE', 'label' => 'No, guardar y continuar', 'confirmation_label' => 'No'],
        8 => ['theme_id' => 2, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q8_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        9 => ['theme_id' => 2, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q9_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        10 => ['theme_id' => 2, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q10_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
        11 => ['theme_id' => 2, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q11_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
        12 => ['theme_id' => 3, 'question_type' => 'select', 'action_type' => 'NEGATIVE', 'key' => 'M03_Q12_NEGATIVE', 'label' => 'No, guardar y continuar', 'confirmation_label' => 'No'],
        13 => ['theme_id' => 3, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q13_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        14 => ['theme_id' => 3, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q14_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        15 => ['theme_id' => 3, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q15_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
        16 => ['theme_id' => 4, 'question_type' => 'select', 'action_type' => 'NEGATIVE', 'key' => 'M03_Q16_NEGATIVE', 'label' => 'No, guardar y continuar', 'confirmation_label' => 'No'],
        17 => ['theme_id' => 4, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q17_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
        18 => ['theme_id' => 5, 'question_type' => 'select', 'action_type' => 'NEGATIVE', 'key' => 'M03_Q18_NEGATIVE', 'label' => 'No, guardar y continuar', 'confirmation_label' => 'No'],
        19 => ['theme_id' => 5, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q19_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        20 => ['theme_id' => 5, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q20_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
        21 => ['theme_id' => 6, 'question_type' => 'select', 'action_type' => 'NEGATIVE', 'key' => 'M03_Q21_NEGATIVE', 'label' => 'No, guardar y continuar', 'confirmation_label' => 'No'],
        23 => ['theme_id' => 6, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q23_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        24 => ['theme_id' => 6, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q24_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
        26 => ['theme_id' => 6, 'question_type' => 'input', 'action_type' => 'NOT_REMEMBERED', 'key' => 'M03_Q26_NOT_REMEMBERED', 'label' => 'No recuerdo', 'confirmation_label' => 'Dato no recordado'],
        27 => ['theme_id' => 7, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q27_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
        28 => ['theme_id' => 7, 'question_type' => 'input', 'action_type' => 'UNKNOWN', 'key' => 'M03_Q28_UNKNOWN', 'label' => 'No lo sé', 'confirmation_label' => 'Dato desconocido'],
    ];

    private ?PDO $db;
    private ?\Closure $diagnostic;
    private bool $diagnosticFailure = false;

    public function __construct(?PDO $db = null, ?callable $diagnostic = null)
    {
        $this->db = $db;
        $this->diagnostic = $diagnostic === null ? null : \Closure::fromCallable($diagnostic);
    }

    /** Optional, non-clinical stage tracing for the guarded synthetic runner. */
    private function trace(string $event): void
    {
        if ($this->diagnostic === null) return;
        try {
            ($this->diagnostic)($event);
        } catch (\Throwable) {
            // Diagnostic output must never change the clinical service result.
            $this->diagnosticFailure = true;
        }
    }

    private function connection(): PDO { return $this->db ?: Database::getInstance(); }

    private function semanticStateAvailable(): bool
    {
        try {
            $stmt = $this->connection()->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pac_respuestas_paciente_modulo_3'");
            $columns = array_map(static fn(array $row): string => (string)$row['column_name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
            return count(array_intersect(['estado_respuesta', 'origen_respuesta', 'estado_respuesta_at'], $columns)) === 3;
        } catch (\Throwable) {
            try {
                $stmt = $this->connection()->query('PRAGMA table_info(pac_respuestas_paciente_modulo_3)');
                $columns = array_map(static fn(array $row): string => (string)($row['name'] ?? ''), $stmt->fetchAll(PDO::FETCH_ASSOC));
                return count(array_intersect(['estado_respuesta', 'origen_respuesta', 'estado_respuesta_at'], $columns)) === 3;
            } catch (\Throwable) {
                return false;
            }
        }
    }

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $this->trace('adapter.read.begin');
        $db = $this->connection();
        $this->trace('adapter.read.semantic_schema.begin');
        $semanticStateAvailable = $this->semanticStateAvailable();
        $this->trace('adapter.read.semantic_schema.end');
        $stateSelect = $semanticStateAvailable ? 'r.estado_respuesta' : 'NULL';
        $originSelect = $semanticStateAvailable ? 'r.origen_respuesta' : 'NULL';
        $stateAtSelect = $semanticStateAvailable ? 'r.estado_respuesta_at' : 'NULL';
        $this->trace('adapter.read.catalog_query.begin');
        $stmt = $db->prepare("SELECT p.id AS idPregunta,p.id_tema AS idTema,t.tema,p.pregunta,p.tipo,r.id AS idRespuesta,COALESCE(r.respuesta,'') AS respuesta,{$stateSelect} AS estadoRespuesta,{$originSelect} AS origenRespuesta,{$stateAtSelect} AS estadoRespuestaAt FROM pac_preguntas_modulo_3 p INNER JOIN pac_temas_modulo_3 t ON t.id=p.id_tema LEFT JOIN pac_respuestas_paciente_modulo_3 r ON r.id_pregunta=p.id AND r.id_paciente=:patient ORDER BY p.id ASC");
        $stmt->execute([':patient' => $patientId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->trace('adapter.read.catalog_query.end');
        $responseCounts = [];
        foreach ($questions as $row) {
            $questionId = (int)($row['idPregunta'] ?? 0);
            if ((int)($row['idRespuesta'] ?? 0) > 0 && $questionId > 0) $responseCounts[$questionId] = ($responseCounts[$questionId] ?? 0) + 1;
        }
        foreach ($questions as &$question) {
            $question['responseRowCount'] = $responseCounts[(int)($question['idPregunta'] ?? 0)] ?? 0;
            $storedState = strtoupper(trim((string)($question['estadoRespuesta'] ?? '')));
            $value = trim((string)($question['respuesta'] ?? ''));
            $question['semanticState'] = in_array($storedState, ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'], true)
                ? $storedState
                : (AntecedentesNoPatologicosModel::canonicalM03UnknownState($value)
                    ?? ($value === '' ? 'UNANSWERED' : 'LEGACY_UNCLASSIFIED'));
        }
        unset($question);
        $byId = [];
        foreach ($questions as $question) $byId[(int)$question['idPregunta']] = $question;
        foreach ($questions as &$question) {
            $theme = (int)$question['idTema'];
            $questionId = (int)$question['idPregunta'];
            $parent = self::PARENT_BY_THEME[$theme] ?? null;
            $question['parentQuestionId'] = $parent;
            $parentAnswer = (string)($byId[$parent]['respuesta'] ?? '');
            $parentIsAffirmative = $parent === M03TobaccoResponsePolicy::QUESTION_ID
                ? M03TobaccoResponsePolicy::isAffirmative($parentAnswer)
                : $parentAnswer === 'Si';
            $question['eligible'] = $parent === null || (int)$question['idPregunta'] === $parent || $parentIsAffirmative;
            if ((int)$question['idPregunta'] === M03TobaccoResponsePolicy::QUESTION_ID && $theme === M03TobaccoResponsePolicy::THEME_ID) {
                $storedSemanticState = strtoupper(trim((string)($question['semanticState'] ?? '')));
                $question['navigationDecision'] = in_array($storedSemanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true)
                    ? $storedSemanticState
                    : M03TobaccoResponsePolicy::classify((string)$question['respuesta']);
            }
            $question['contextualAction'] = $this->contextualActionFor($question, $semanticStateAvailable);
        }
        unset($question);
        $this->trace('adapter.read.write_policy.begin');
        $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 3);
        $this->trace('adapter.read.write_policy.end');
        $editableQuestionIds = self::historicalEditableQuestionIds(
            $questions,
            (bool)($policy['editable'] ?? false),
            (bool)($policy['finalized'] ?? true),
        );
        foreach ($questions as &$question) {
            $question['historicalEditable'] = in_array((int)$question['idPregunta'], $editableQuestionIds, true);
        }
        unset($question);
        $this->trace('adapter.read.end');
        return ['key' => 'M03', 'questions' => $questions, 'version' => hash('sha256', json_encode($questions, JSON_UNESCAPED_UNICODE)), 'write_enabled' => (bool)($policy['editable'] ?? false), 'finalized' => (bool)($policy['finalized'] ?? true), 'write_blocker' => $policy['reason'] ?? null, 'semantic_state_available' => $semanticStateAvailable];
    }

    public static function isThemeParentQuestion(int $themeId, int $questionId): bool
    {
        return (self::PARENT_BY_THEME[$themeId] ?? null) === $questionId;
    }

    /** A separate, update-only policy for the previous-responses screen. */
    public function previousResponseEditBlockerCode(array $state, array $question): ?string
    {
        if (!$this->previousEditDatabaseAuthorized()) return 'M03_PREVIOUS_DATABASE_NOT_AUTHORIZED';
        if ((int)($question['responseRowCount'] ?? 0) > 1) return 'M03_PREVIOUS_DUPLICATE_ROWS';
        $responseId = (int)($question['idRespuesta'] ?? 0);
        $semantic = strtoupper(trim((string)($question['semanticState'] ?? '')));
        $answer = trim((string)($question['respuesta'] ?? ''));
        $persisted = $responseId > 0 && ($answer !== '' || in_array($semantic, ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'], true));
        if (!$persisted) return 'M03_PREVIOUS_NOT_PERSISTED';
        if (empty($question['eligible'])) return 'M03_PREVIOUS_DEPENDENCY_INELIGIBLE';
        if (!in_array((string)($question['tipo'] ?? ''), ['select', 'input'], true)) return 'M03_PREVIOUS_QUESTION_TYPE_UNSUPPORTED';
        if (!empty($state['semantic_state_available'])) {
            if (!in_array($semantic, ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'], true)) return 'M03_PREVIOUS_STATE_UNVERIFIED';
            if (in_array($semantic, ['UNKNOWN', 'NOT_REMEMBERED'], true) && $answer !== '') return 'M03_PREVIOUS_STATE_INCONSISTENT';
            if ($semantic === 'KNOWN' && $answer === '') return 'M03_PREVIOUS_STATE_INCONSISTENT';
            if ($semantic === 'NEGATIVE' && !($answer === 'No' && (string)$question['tipo'] === 'select')
                && !((int)$question['idPregunta'] === 5 && (int)$question['idTema'] === 1 && $answer === AntecedentesNoPatologicosModel::M03_Q5_NOT_STOPPED_DETAIL)) {
                return 'M03_PREVIOUS_STATE_INCONSISTENT';
            }
        }
        $detailedTobaccoAffirmative = M03TobaccoResponsePolicy::isQuestion($question)
            && $semantic === 'KNOWN'
            && M03TobaccoResponsePolicy::classify($answer) === M03TobaccoResponsePolicy::AFFIRMATIVE;
        if ((string)$question['tipo'] === 'select' && $semantic === 'KNOWN'
            && !in_array($answer, ['Si', 'Sí'], true) && !$detailedTobaccoAffirmative) {
            return 'M03_PREVIOUS_SELECT_INCONSISTENT';
        }
        return null;
    }

    /** Optimistic version for exactly one saved answer, independent of module progress. */
    public static function previousResponseVersion(int $patientId, array $question): string
    {
        $payload = [
            'patient_id' => $patientId,
            'question_id' => (int)($question['idPregunta'] ?? 0),
            'theme_id' => (int)($question['idTema'] ?? 0),
            'question_type' => (string)($question['tipo'] ?? ''),
            'response_id' => (int)($question['idRespuesta'] ?? 0),
            'answer' => (string)($question['respuesta'] ?? ''),
            'semantic_state' => strtoupper(trim((string)($question['estadoRespuesta'] ?? ''))),
            'origin' => $question['origenRespuesta'] ?? null,
            'state_at' => $question['estadoRespuestaAt'] ?? null,
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function previousEditDatabaseAuthorized(): bool
    {
        try {
            $db = $this->connection();
            if ((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                return $this->db !== null;
            }
            $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
            $serverPort = (int)$db->query('SELECT @@port')->fetchColumn();
            $configuredHost = strtolower(trim((string)($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '')));
            $configuredPort = (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
            return in_array($database, ['bd_clinica', 'clinica_dolor_demo_20261010_final'], true)
                && in_array($configuredHost, ['localhost', '127.0.0.1', '::1'], true)
                && $configuredPort === 3306 && $serverPort === 3306;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Validates, updates and independently reads back one existing M03 row. */
    public function editExistingResponse(int $patientId, int $questionId, int $responseId, string $expectedVersion, string $utterance, ?string $requestedSemanticState = null): array
    {
        if ($patientId < 1 || $questionId < 1 || $responseId < 1 || !preg_match('/^[a-f0-9]{64}$/', $expectedVersion)) {
            return ['resultado' => 422, 'mensaje' => 'La solicitud de edición M03 no es válida.', 'code' => 'M03_PREVIOUS_EDIT_INVALID', 'readback' => false];
        }
        $state = $this->read($patientId);
        $matches = array_values(array_filter($state['questions'], static fn(array $row): bool => (int)($row['idPregunta'] ?? 0) === $questionId));
        if (count($matches) !== 1) return ['resultado' => 409, 'mensaje' => 'La pregunta tiene respuestas duplicadas o dejó de estar disponible. No se modificó.', 'code' => 'M03_PREVIOUS_DUPLICATE_ROWS', 'readback' => false];
        $question = $matches[0];
        if ((int)($question['idRespuesta'] ?? 0) !== $responseId
            || !hash_equals(self::previousResponseVersion($patientId, $question), $expectedVersion)) {
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió desde que abriste la edición. Recarga antes de guardar.', 'code' => 'M03_PREVIOUS_VERSION_CONFLICT', 'readback' => false];
        }
        $blocker = $this->previousResponseEditBlockerCode($state, $question);
        if ($blocker !== null) {
            return ['resultado' => 409, 'mensaje' => 'Esta respuesta no cumple las condiciones de edición M03. No se modificó.', 'code' => $blocker, 'readback' => false];
        }
        $utterance = trim($utterance);
        if ($utterance === '' || mb_strlen($utterance, 'UTF-8') > 2000) {
            return ['resultado' => 422, 'mensaje' => 'Completa una respuesta de hasta 2000 caracteres.', 'code' => 'M03_PREVIOUS_ANSWER_REQUIRED', 'readback' => false];
        }
        try {
            $proposal = $this->interpret($patientId, $questionId, $utterance);
        } catch (\Throwable) {
            return ['resultado' => 422, 'mensaje' => 'La respuesta no cumple el formato de esta pregunta M03.', 'code' => 'M03_PREVIOUS_INTERPRETATION_FAILED', 'readback' => false];
        }
        if (($proposal['kind'] ?? null) !== 'm03_proposal'
            || (int)($proposal['question_id'] ?? 0) !== $questionId
            || (int)($proposal['theme_id'] ?? 0) !== (int)$question['idTema']) {
            return ['resultado' => 422, 'mensaje' => (string)($proposal['clarification'] ?? 'Aclara la respuesta antes de guardar.'), 'code' => 'M03_PREVIOUS_ANSWER_INVALID', 'readback' => false];
        }
        $value = is_string($proposal['structured_value'] ?? null) ? trim($proposal['structured_value']) : '';
        $semanticState = strtoupper(trim((string)($proposal['semantic_state'] ?? '')));
        if ($semanticState === '') {
            $semanticState = ConversationalInterviewService::classifyM03Unknown($value)
                ?? (((string)$question['tipo'] === 'select' && $value === 'No') || ($questionId === 5 && $value === AntecedentesNoPatologicosModel::M03_Q5_NOT_STOPPED_DETAIL) ? 'NEGATIVE' : 'KNOWN');
        }
        if (!in_array($semanticState, ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'], true)) {
            return ['resultado' => 422, 'mensaje' => 'El estado de la respuesta no es válido.', 'code' => 'M03_PREVIOUS_STATE_INVALID', 'readback' => false];
        }
        if ($requestedSemanticState !== null
            && !hash_equals(strtoupper(trim($requestedSemanticState)), $semanticState)) {
            return ['resultado' => 422, 'mensaje' => 'La respuesta no coincide con el estado seleccionado. No se guardó.', 'code' => 'M03_PREVIOUS_STATE_MISMATCH', 'readback' => false];
        }
        if (in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true)) {
            $value = !empty($state['semantic_state_available']) ? '' : (AntecedentesNoPatologicosModel::canonicalM03UnknownValue($semanticState) ?? '');
        }
        $model = new AntecedentesNoPatologicosModel($this->connection(), $this->diagnostic);
        $result = $model->actualizarRespuestaM03Existente([
            'idPaciente' => $patientId,
            'idPregunta' => $questionId,
            'idTema' => (int)$question['idTema'],
            'idRespuesta' => $responseId,
            'detalle' => $value,
            'estadoRespuesta' => $semanticState,
            'origenRespuesta' => 'PATIENT_DECLARATION',
            'expectedRespuesta' => (string)($question['respuesta'] ?? ''),
            'expectedEstadoRespuesta' => (string)($question['semanticState'] ?? ''),
            'expectedOrigenRespuesta' => is_string($question['origenRespuesta'] ?? null) ? $question['origenRespuesta'] : null,
            'expectedEstadoRespuestaAt' => is_string($question['estadoRespuestaAt'] ?? null) ? $question['estadoRespuestaAt'] : null,
        ]);
        if ((int)($result['resultado'] ?? 0) !== 200 || ($result['readback'] ?? false) !== true) return $result + ['readback' => false];

        $after = $this->read($patientId);
        $savedMatches = array_values(array_filter($after['questions'], static fn(array $row): bool => (int)($row['idPregunta'] ?? 0) === $questionId));
        if (count($savedMatches) !== 1 || (int)($savedMatches[0]['idRespuesta'] ?? 0) !== $responseId) {
            return ['resultado' => 500, 'mensaje' => 'La lectura posterior no confirmó la misma respuesta.', 'code' => 'M03_PREVIOUS_READBACK_FAILED', 'readback' => false];
        }
        $saved = $savedMatches[0];
        $savedState = strtoupper(trim((string)($saved['estadoRespuesta'] ?? '')));
        $actualSemantic = !empty($after['semantic_state_available'])
            ? $savedState
            : (AntecedentesNoPatologicosModel::canonicalM03UnknownState((string)$saved['respuesta'])
                ?? ($semanticState === 'NEGATIVE' ? 'NEGATIVE' : 'KNOWN'));
        if ((string)$saved['respuesta'] !== $value || (!empty($after['semantic_state_available']) && $actualSemantic !== $semanticState)) {
            return ['resultado' => 500, 'mensaje' => 'La lectura posterior no confirmó el valor y estado de la respuesta.', 'code' => 'M03_PREVIOUS_READBACK_FAILED', 'readback' => false];
        }
        return [
            'resultado' => 200,
            'mensaje' => (string)($result['mensaje'] ?? 'Respuesta M03 actualizada.'),
            'readback' => true,
            'readback_value' => (string)$saved['respuesta'],
            'answer' => $this->formatPreviousResponse($saved),
            'semantic_state' => $actualSemantic,
            'version' => self::previousResponseVersion($patientId, $saved),
            'response_id' => $responseId,
        ];
    }

    public static function formatPreviousResponse(array $question): string
    {
        $state = strtoupper(trim((string)($question['semanticState'] ?? $question['estadoRespuesta'] ?? '')));
        if ($state === 'UNKNOWN') return 'No lo sé';
        if ($state === 'NOT_REMEMBERED') return 'No recuerdo';
        $value = trim((string)($question['respuesta'] ?? ''));
        if ($state === 'NEGATIVE' && $value === 'No') return 'No';
        if (in_array($state, ['KNOWN', 'LEGACY_UNCLASSIFIED'], true) && (string)($question['tipo'] ?? '') === 'select' && $value === 'Si') return 'Sí';
        return $value;
    }

    /** A contiguous, eligible, persisted prefix before the first pending question is the editable history. */
    private static function historicalEditableQuestionIds(array $questions, bool $writeEnabled, bool $finalized): array
    {
        if (!$writeEnabled || $finalized) return [];
        $eligible = array_values(array_filter($questions, static fn(array $question): bool => !empty($question['eligible'])));
        $firstPending = null;
        foreach ($eligible as $index => $question) {
            $semanticState = strtoupper(trim((string)($question['semanticState'] ?? $question['estadoRespuesta'] ?? '')));
            $resolvedWithoutText = in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true);
            $persisted = (int)($question['idRespuesta'] ?? 0) > 0
                && (trim((string)($question['respuesta'] ?? '')) !== '' || $resolvedWithoutText);
            if (!$persisted) {
                $firstPending = $index;
                break;
            }
        }
        if ($firstPending === null || $firstPending < 1) return [];
        $editable = [];
        foreach (array_slice($eligible, 0, $firstPending) as $question) {
            $editable[] = (int)($question['idPregunta'] ?? 0);
        }
        return array_values(array_filter($editable, static fn(int $id): bool => $id > 0));
    }

    public function interpret(int $patientId, int $questionId, string $utterance): array
    {
        $text = trim($utterance);
        if ($text === '') throw new \InvalidArgumentException('La respuesta no puede estar vacía.');
        $state = $this->read($patientId); $question = null;
        foreach ($state['questions'] as $candidate) if ((int)$candidate['idPregunta'] === $questionId) { $question = $candidate; break; }
        if (!$question) throw new \DomainException('La pregunta no pertenece al catálogo M03.');
        if (!$question['eligible'] && trim((string)$question['respuesta']) === '') return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>'Primero responde la pregunta principal de este tema.','needs_clarification'=>true,'can_confirm'=>false];
        if (M03TobaccoResponsePolicy::isQuestion($question)) {
            $decision = M03TobaccoResponsePolicy::classify($text);
            if ($decision === M03TobaccoResponsePolicy::AMBIGUOUS) {
                return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>'No pude determinar si actualmente fumas. Responde sí, no, no lo sé o no lo recuerdo. No se guardó ningún cambio.','needs_clarification'=>true,'can_confirm'=>false,'direct_save'=>false];
            }
            $stateValue = M03TobaccoResponsePolicy::semanticState($decision);
            if ($stateValue === null) return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>'Necesito una respuesta más clara antes de guardar.','needs_clarification'=>true,'can_confirm'=>false,'direct_save'=>false];
            $unknown = in_array($decision, [M03TobaccoResponsePolicy::UNKNOWN, M03TobaccoResponsePolicy::NOT_REMEMBERED], true);
            $value = $unknown && ($state['semantic_state_available'] ?? false) ? null : M03TobaccoResponsePolicy::storedValue($text);
            return [
                'mode' => 'deterministic',
                'kind' => 'm03_proposal',
                'target' => 'm03',
                'question_id' => $questionId,
                'theme_id' => (int)$question['idTema'],
                'question' => (string)$question['pregunta'],
                'structured_value' => $value,
                'semantic_state' => $stateValue,
                'interpretation' => $decision === M03TobaccoResponsePolicy::AFFIRMATIVE ? (string)($value ?? 'Sí') : (string)($value ?? 'Dato pendiente'),
                'navigation_decision' => $decision,
                'needs_clarification' => false,
                'requires_confirmation' => false,
                'can_confirm' => true,
                'direct_save' => true,
            ];
        }
        $unknownState = ConversationalInterviewService::classifyM03Unknown($text);
        if ($unknownState !== null) {
            if ($state['semantic_state_available'] ?? false) {
                return [
                    'mode' => 'deterministic',
                    'kind' => 'm03_proposal',
                    'target' => 'm03',
                    'question_id' => $questionId,
                    'theme_id' => (int)$question['idTema'],
                    'question' => $question['pregunta'],
                    'structured_value' => null,
                    'semantic_state' => $unknownState,
                    'interpretation' => $unknownState === 'NOT_REMEMBERED' ? 'Dato no recordado' : 'Dato desconocido',
                    'needs_clarification' => false,
                    'requires_confirmation' => false,
                    'can_confirm' => true,
                    'direct_save' => true,
                ];
            }
            $canonicalValue = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($unknownState);
            return [
                'mode' => 'deterministic',
                'kind' => 'm03_proposal',
                'target' => 'm03',
                'question_id' => $questionId,
                'theme_id' => (int)$question['idTema'],
                'question' => $question['pregunta'],
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
        $normalized = mb_strtolower($text, 'UTF-8');
        if ((string)$question['tipo'] === 'select') {
            $yes = preg_match('/\b(s[ií]|sí|actualmente|consumo|practico|tengo)\b/u', $normalized) === 1;
            $no = preg_match('/\b(no|nunca)\b/u', $normalized) === 1;
            if ($yes === $no) return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>'Responde claramente sí o no.','needs_clarification'=>true,'can_confirm'=>false];
            $value = $yes ? 'Si' : 'No';
        } else {
            if (ConversationalInterviewService::isM03BloodRhQuestion($question)
                && preg_match('/^\s*(?:no|nunca)\s*[.!?]*\s*$/iu', $normalized) === 1) {
                return [
                    'mode' => 'deterministic',
                    'kind' => 'clarification',
                    'question_id' => $questionId,
                    'clarification' => '“No” no representa un grupo sanguíneo ni un factor Rh. Indica el valor si lo conoces o di que no lo sabes o no lo recuerdas.',
                    'needs_clarification' => true,
                    'can_confirm' => false,
                ];
            }
            $value = $text;
        }
        // Deterministic answers are eligible for the same single-submit
        // server-authorized capture regardless of whether the catalog field
        // is binary or literal. The capture method remains authoritative for
        // the question type, semantic state, and clinical value validation.
        $directSave = (bool)($state['write_enabled'] ?? false)
            && $value !== ''
            && (
                (string)$question['tipo'] !== 'select'
                || in_array($value, ['Si', 'No'], true)
            );
        return [
            'mode' => 'deterministic',
            'kind' => 'm03_proposal',
            'target' => 'm03',
            'question_id' => $questionId,
            'theme_id' => (int)$question['idTema'],
            'question' => $question['pregunta'],
            'structured_value' => $value,
            'interpretation' => $value,
            'requires_confirmation' => !$directSave,
            'can_confirm' => $directSave,
            'direct_save' => $directSave,
        ];
    }

    public function capture(int $patientId, array $proposal, ?string $expectedVersion, ?int $expectedResponseId = null): array
    {
        $this->trace('adapter.capture.begin');
        if ($patientId < 1 || ($proposal['kind'] ?? '') !== 'm03_proposal' || !isset($proposal['question_id'], $proposal['theme_id'])) return ['resultado'=>422,'mensaje'=>'La propuesta M03 no es válida.'];
        $this->trace('adapter.capture.prewrite_read.begin');
        $state = $this->read($patientId);
        $this->trace('adapter.capture.prewrite_read.end');
        $question = null;
        foreach ($state['questions'] as $candidate) if ((int)$candidate['idPregunta'] === (int)$proposal['question_id']) { $question = $candidate; break; }
        if (!$question || (int)$question['idTema'] !== (int)$proposal['theme_id']) return ['resultado'=>404,'mensaje'=>'La pregunta M03 no pertenece al tema autorizado.'];
        if (!$state['write_enabled']) return ['resultado'=>403,'mensaje'=>'El módulo M03 no admite nuevas respuestas.'];
        if ($expectedVersion !== null && $expectedVersion !== '' && !hash_equals((string)$state['version'], $expectedVersion)) return ['resultado'=>409,'mensaje'=>'M03 cambió mientras se revisaba. Recarga antes de continuar.'];
        $savedValue = trim((string)($question['respuesta'] ?? ''));
        $savedSemanticState = strtoupper(trim((string)($question['semanticState'] ?? $question['estadoRespuesta'] ?? '')));
        $hasPersistedAnswer = (int)($question['idRespuesta'] ?? 0) > 0
            && ($savedValue !== '' || in_array($savedSemanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true));
        if ($expectedResponseId !== null && $expectedResponseId > 0
            && (int)($question['idRespuesta'] ?? 0) !== $expectedResponseId) {
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. Recarga la pregunta antes de continuar.', 'code' => 'M03_RESPONSE_ID_CONFLICT', 'persist' => false];
        }
        if ($hasPersistedAnswer) {
            if (empty($question['eligible'])
                || !$this->serverAuthorizesHistoricalEdit($state['questions'], (int)$question['idPregunta'])
                || $expectedVersion === null
                || trim($expectedVersion) === ''
                || !hash_equals((string)$state['version'], $expectedVersion)
                || $expectedResponseId === null
                || $expectedResponseId < 1
                || $expectedResponseId !== (int)$question['idRespuesta']) {
                return ['resultado' => 409, 'mensaje' => 'Esta respuesta no está disponible para edición en este momento. Recarga la entrevista.', 'code' => 'M03_PREVIOUS_EDIT_NOT_AUTHORIZED', 'persist' => false];
            }
        }
        $value = trim((string)($proposal['structured_value'] ?? ''));
        $semanticState = strtoupper(trim((string)($proposal['semantic_state'] ?? '')));
        if ($semanticState === '') $semanticState = '';
        if ($semanticState !== '' && !in_array($semanticState, ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'], true)) return ['resultado'=>422,'mensaje'=>'El estado semántico M03 no es válido.'];
        $tobaccoDecision = null;
        if (M03TobaccoResponsePolicy::isQuestion($question)) {
            $tobaccoDecision = $value === '' && in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true)
                ? $semanticState
                : M03TobaccoResponsePolicy::classify($value);
            $expectedSemanticState = M03TobaccoResponsePolicy::semanticState($tobaccoDecision);
            if ($expectedSemanticState === null) return ['resultado'=>422,'mensaje'=>'La respuesta de tabaquismo es ambigua. No se guardó ningún cambio.','code'=>'M03_TOBACCO_CLARIFICATION_REQUIRED','persist'=>false];
            if ($semanticState !== '' && $semanticState !== $expectedSemanticState) return ['resultado'=>422,'mensaje'=>'La respuesta no coincide con la clasificación de tabaquismo. No se guardó ningún cambio.','code'=>'M03_TOBACCO_STATE_MISMATCH','persist'=>false];
            $semanticState = $expectedSemanticState;
            if (in_array($tobaccoDecision, [M03TobaccoResponsePolicy::UNKNOWN, M03TobaccoResponsePolicy::NOT_REMEMBERED], true)) {
                $value = ($state['semantic_state_available'] ?? false) ? '' : M03TobaccoResponsePolicy::storedValue($value);
            } else {
                $value = M03TobaccoResponsePolicy::storedValue($value);
            }
        }
        if (in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) && $value !== '') {
            $canonicalValue = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($semanticState);
            $textualFallback = !($state['semantic_state_available'] ?? false)
                && $canonicalValue !== null
                && $value === $canonicalValue
                && ConversationalInterviewService::classifyM03Unknown($value) === $semanticState;
            if (!$textualFallback) return ['resultado'=>422,'mensaje'=>'El estado M03 no puede contener un valor clínico inventado.'];
        }
        if ($semanticState === '' && $value === '') return ['resultado'=>422,'mensaje'=>'La respuesta M03 no puede estar vacía.'];
        $unknownState = $tobaccoDecision === null ? ConversationalInterviewService::classifyM03Unknown($value) : null;
        if ($unknownState !== null && $semanticState === '') {
            $semanticState = $unknownState;
            $value = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($unknownState) ?? $value;
        }
        if ($semanticState === '') $semanticState = ((string)$question['tipo'] === 'select' && $value === 'No') ? 'NEGATIVE' : 'KNOWN';
        if (in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) && !$state['semantic_state_available']) {
            $canonicalValue = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($semanticState);
            if ($canonicalValue === null || $value !== $canonicalValue) {
                return ['resultado'=>422,'mensaje'=>'La respuesta desconocida no coincide con el valor textual canónico.','code'=>'M03_TEXTUAL_UNKNOWN_VALUE_INVALID','persist'=>false,'never_infer_negative'=>true];
            }
        }
        $q5NotStopped = (int)$question['idPregunta'] === 5
            && (int)$question['idTema'] === 1
            && (string)$question['tipo'] === 'input'
            && $value === AntecedentesNoPatologicosModel::M03_Q5_NOT_STOPPED_DETAIL;
        if ($semanticState === 'NEGATIVE'
            && !$q5NotStopped
            && ((string)$question['tipo'] !== 'select' || $value !== 'No')) {
            return ['resultado'=>422,'mensaje'=>'La respuesta negativa no corresponde a esta pregunta.','code'=>'M03_NEGATIVE_STATE_MISMATCH','persist'=>false];
        }
        if ($q5NotStopped && !$this->earlierEligibleQuestionsResolved($state['questions'], (int)$question['idPregunta'])) {
            return ['resultado' => 422, 'mensaje' => 'Primero completa las preguntas anteriores de tabaquismo.', 'code' => 'M03_CONTEXTUAL_PREVIOUS_QUESTION_PENDING', 'persist' => false];
        }
        if (ConversationalInterviewService::isM03BloodRhQuestion($question)
            && preg_match('/^\s*(?:no|nunca)\s*[.!?]*\s*$/iu', $value) === 1) {
            return [
                'resultado' => 422,
                'mensaje' => '“No” no es un valor válido para grupo sanguíneo o factor Rh.',
                'code' => 'M03_BLOOD_RH_NEGATIVE_INVALID',
                'persist' => false,
            ];
        }

        if ((string)$question['tipo'] === 'select' && $value === 'No') {
            $conflicts = $this->dependentResponses($patientId, (int)$question['idTema'], (int)$question['idPregunta']);
            if ($conflicts !== []) {
                return ['resultado'=>409,'mensaje'=>'No se puede cambiar esta respuesta porque existen respuestas dependientes. La corrección requiere un procedimiento clínico autorizado.','conflicts'=>$conflicts];
            }
        }

        $model = new AntecedentesNoPatologicosModel($this->connection(), $this->diagnostic);
        $persistedResponseId = $expectedResponseId !== null && $expectedResponseId > 0
            ? $expectedResponseId
            : null;
        $this->trace('adapter.capture.model_call.begin');
        if ((string)$question['tipo'] === 'select' && $value === 'No') {
            // Reuse the existing confirmed-capture contract for both cases:
            // it updates an existing row or creates the empty response row
            // only after this explicit confirmation. The dependency check
            // above must remain before the model call so a conflicting No
            // cannot create a collateral empty row.
        $result = $model->capturarRespuestaEntrevista([
                'idPaciente' => $patientId,
                'idPregunta' => (int)$question['idPregunta'],
                'idTema' => (int)$question['idTema'],
                'detalle' => 'No',
            'estadoRespuesta' => 'NEGATIVE',
            'origenRespuesta' => 'PATIENT_DECLARATION',
                'expectedRespuesta' => (string)$question['respuesta'],
                'expectedResponseId' => $persistedResponseId,
            ]);
        } else {
            $result = $model->capturarRespuestaEntrevista(['idPaciente'=>$patientId,'idPregunta'=>(int)$question['idPregunta'],'idTema'=>(int)$question['idTema'],'detalle'=>$value,'estadoRespuesta'=>$semanticState,'origenRespuesta'=>'PATIENT_DECLARATION','expectedRespuesta'=>(string)$question['respuesta'],'expectedResponseId'=>$persistedResponseId]);
        }
        $this->trace('adapter.capture.model_call.end');
        if ((int)($result['resultado'] ?? 0) !== 200) return $result + ['version' => $this->read($patientId)['version']];
        $this->trace('adapter.capture.postwrite_read.begin');
        $after = $this->read($patientId);
        $this->trace('adapter.capture.postwrite_read.end');
        $readback = null;
        $readbackState = null;
        $readbackResponseId = 0;
        foreach ($after['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] === (int)$question['idPregunta']) {
                $readback = trim((string)$candidate['respuesta']);
                $readbackState = strtoupper(trim((string)($candidate['estadoRespuesta'] ?? '')));
                $readbackResponseId = (int)($candidate['idRespuesta'] ?? 0);
                break;
            }
        }
        if ($readback !== $value || ($state['semantic_state_available'] && $readbackState !== $semanticState)) return ['resultado' => 500, 'mensaje' => 'No fue posible verificar la respuesta guardada.', 'code' => 'M03_READBACK_FAILED', 'readback' => false];
        $readbackValue = '';
        foreach ($after['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] === (int)$question['idPregunta']) {
                $readbackValue = trim((string)$candidate['respuesta']);
                break;
            }
        }
        $eligibleIds = array_values(array_map(static fn(array $row): int => (int)$row['idPregunta'], array_filter($after['questions'], static fn(array $row): bool => !empty($row['eligible']))));
        $editableIds = array_values(array_map(static fn(array $row): int => (int)$row['idPregunta'], array_filter($after['questions'], static fn(array $row): bool => !empty($row['historicalEditable']))));
        $this->trace('adapter.capture.response_build.begin');
        $response = [
            'version' => $after['version'],
            'readback' => true,
            'readback_value' => $readbackValue,
            'response_id' => $readbackResponseId,
            'semantic_state' => $semanticState,
            'eligible_question_ids' => $eligibleIds,
            'editable_question_ids' => $editableIds,
            'next_question_id' => $this->nextPendingQuestionId($after['questions'], (int)$question['idPregunta']),
        ];
        if ($tobaccoDecision !== null) {
            $response['navigation_decision'] = $tobaccoDecision;
        }
        $this->trace('adapter.capture.end');
        return array_merge($result, $response);
    }

    /**
     * Performs one server-defined contextual action from the M03 question contract.
     * The client submits only the action key; its label or clinical value is never trusted.
     */
    public function captureContextualAction(int $patientId, int $questionId, int $themeId, string $actionKey, ?string $expectedVersion): array
    {
        if ($patientId < 1 || $questionId < 1 || $themeId < 1 || trim($actionKey) === '') {
            return ['resultado' => 422, 'mensaje' => 'La acción contextual M03 no es válida.', 'code' => 'M03_CONTEXTUAL_ACTION_INVALID'];
        }
        $state = $this->read($patientId);
        if ($expectedVersion === null || trim($expectedVersion) === '') {
            return ['resultado' => 409, 'mensaje' => 'Recarga M03 antes de aplicar esta acción.', 'code' => 'M03_CONTEXTUAL_VERSION_REQUIRED'];
        }
        if (!hash_equals((string)$state['version'], $expectedVersion)) {
            return ['resultado' => 409, 'mensaje' => 'M03 cambió antes de aplicar esta acción. Recarga antes de continuar.', 'code' => 'M03_CONTEXTUAL_VERSION_STALE'];
        }
        $question = null;
        foreach ($state['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] === $questionId) { $question = $candidate; break; }
        }
        if (!$question || (int)$question['idTema'] !== $themeId) {
            return ['resultado' => 404, 'mensaje' => 'La pregunta M03 no pertenece al tema autorizado.', 'code' => 'M03_CONTEXTUAL_QUESTION_MISMATCH'];
        }
        if (empty($state['write_enabled'])) return ['resultado' => 403, 'mensaje' => 'El módulo M03 no admite nuevas respuestas.', 'code' => 'M03_CONTEXTUAL_WRITE_DISABLED'];
        if (empty($question['eligible'])) return ['resultado' => 422, 'mensaje' => 'La pregunta aún no está habilitada por su dependencia clínica.', 'code' => 'M03_CONTEXTUAL_QUESTION_INELIGIBLE'];
        $action = $question['contextualAction'] ?? null;
        if (!is_array($action) || !hash_equals((string)($action['key'] ?? ''), $actionKey)) {
            return ['resultado' => 422, 'mensaje' => 'Esta acción no está disponible para la pregunta actual.', 'code' => 'M03_CONTEXTUAL_ACTION_NOT_ALLOWED'];
        }

        return $this->capture($patientId, [
            'kind' => 'm03_proposal',
            'question_id' => $questionId,
            'theme_id' => $themeId,
            'structured_value' => $action['structured_value'],
            'semantic_state' => $action['semantic_state'],
        ], $expectedVersion);
    }

    private function contextualActionFor(array $question, bool $semanticStateAvailable): ?array
    {
        $questionId = (int)($question['idPregunta'] ?? 0);
        $themeId = (int)($question['idTema'] ?? 0);
        $type = (string)($question['tipo'] ?? '');
        $definition = self::CONTEXTUAL_ACTIONS[$questionId] ?? null;
        if (!is_array($definition)
            || (int)$definition['theme_id'] !== $themeId
            || (string)$definition['question_type'] !== $type) {
            return null;
        }

        $semanticState = (string)$definition['action_type'];
        $value = $semanticState === 'NEGATIVE'
            ? ($questionId === 5
                && $themeId === 1
                && $type === 'input'
                    ? AntecedentesNoPatologicosModel::M03_Q5_NOT_STOPPED_DETAIL
                    : 'No')
            : ($semanticStateAvailable ? null : AntecedentesNoPatologicosModel::canonicalM03UnknownValue($semanticState));

        return $definition + [
            'semantic_state' => $semanticState,
            'structured_value' => $value,
        ];
    }

    /** Q5 may close only after its earlier eligible tobacco questions are read back as resolved. */
    private function earlierEligibleQuestionsResolved(array $questions, int $questionId): bool
    {
        foreach ($questions as $question) {
            if ((int)($question['idTema'] ?? 0) !== 1
                || (int)($question['idPregunta'] ?? 0) >= $questionId
                || empty($question['eligible'])) {
                continue;
            }
            $semanticState = strtoupper(trim((string)($question['semanticState'] ?? $question['estadoRespuesta'] ?? '')));
            $hasPersistedResponse = (int)($question['idRespuesta'] ?? 0) > 0;
            $resolvedWithoutText = $hasPersistedResponse && in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true);
            $hasPersistedValue = $hasPersistedResponse && trim((string)($question['respuesta'] ?? '')) !== '';
            if (!$resolvedWithoutText && !$hasPersistedValue) return false;
        }
        return true;
    }

    /**
     * Captures a complete numeric input in one explicit save-and-continue
     * action. The server remains authoritative: the question must be open,
     * eligible, editable and of the catalog's input type. No numeric bound is
     * invented here; the existing catalog type is the only clinical rule.
     */
    public function captureDirectNumeric(int $patientId, int $questionId, string $value, ?string $expectedVersion, bool $allowExisting = false, ?int $expectedResponseId = null): array
    {
        return $this->captureDirectValue($patientId, $questionId, $value, $expectedVersion, true, $allowExisting, $expectedResponseId);
    }

    /**
     * Captures a validated literal duration without creating a second
     * persistence path. The canonical capture() operation remains authoritative.
     */
    public function captureDirectText(int $patientId, int $questionId, string $value, ?string $expectedVersion, bool $allowExisting = false, ?int $expectedResponseId = null): array
    {
        return $this->captureDirectValue($patientId, $questionId, $value, $expectedVersion, false, $allowExisting, $expectedResponseId);
    }

    private function captureDirectValue(int $patientId, int $questionId, string $value, ?string $expectedVersion, bool $numericOnly, bool $allowExisting, ?int $expectedResponseId): array
    {
        // Kept in the internal signature for compatibility with existing
        // callers; the client flag is intentionally not an authorization input.
        unset($allowExisting);
        $value = trim($value);
        $state = $this->read($patientId);
        $question = null;
        foreach ($state['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] === $questionId) {
                $question = $candidate;
                break;
            }
        }

        if (!$question) return ['resultado' => 404, 'mensaje' => 'La pregunta M03 no pertenece al catálogo.', 'code' => 'DIRECT_QUESTION_NOT_FOUND'];
        if (!$state['write_enabled']) return ['resultado' => 403, 'mensaje' => 'El módulo M03 no admite nuevas respuestas.', 'code' => 'DIRECT_WRITE_DISABLED'];
        if (!$question['eligible']) return ['resultado' => 422, 'mensaje' => 'Primero responde la pregunta principal de este tema.', 'code' => 'DIRECT_DEPENDENCY_PENDING'];
        if ((string)$question['tipo'] !== 'input') return ['resultado' => 422, 'mensaje' => 'Esta respuesta requiere revisión antes de guardarse.', 'code' => 'DIRECT_INPUT_REQUIRED'];
        if (!$this->isNumericQuestion($question)) return ['resultado' => 422, 'mensaje' => 'Esta pregunta requiere revisión antes de guardarse.', 'code' => 'DIRECT_NUMERIC_QUESTION_REQUIRED'];
        $currentResponseId = (int)($question['idRespuesta'] ?? 0);
        if (($expectedResponseId !== null && $expectedResponseId > 0 && $currentResponseId !== $expectedResponseId)
            || ($currentResponseId > 0 && $expectedResponseId === null)) {
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. Actualiza la pregunta antes de continuar.', 'code' => 'M03_RESPONSE_ID_CONFLICT'];
        }
        $hasPersistedAnswer = $currentResponseId > 0
            && (trim((string)$question['respuesta']) !== '' || in_array(strtoupper((string)$question['semanticState']), ['UNKNOWN', 'NOT_REMEMBERED'], true));
        if ($hasPersistedAnswer && !$this->serverAuthorizesHistoricalEdit($state['questions'], $questionId)) {
            return ['resultado' => 409, 'mensaje' => 'Esta respuesta no está autorizada para edición en este momento.', 'code' => 'DIRECT_EDIT_NOT_AUTHORIZED'];
        }
        if ($numericOnly && !preg_match('/^\d+(?:[.,]\d+)?$/u', $value)) return ['resultado' => 422, 'mensaje' => 'Indica un valor numérico completo para guardarlo directamente.', 'code' => 'DIRECT_NUMERIC_VALUE_REQUIRED'];
        if (!$numericOnly && !$this->isDirectLiteralValue($value)) return ['resultado' => 422, 'mensaje' => 'Indica una duración completa para guardarla directamente.', 'code' => 'DIRECT_LITERAL_VALUE_REQUIRED'];

        $captureResponseId = $expectedResponseId ?? ($currentResponseId > 0 ? $currentResponseId : null);
        $result = $this->capture($patientId, [
            'kind' => 'm03_proposal',
            'question_id' => $questionId,
            'theme_id' => (int)$question['idTema'],
            'structured_value' => $value,
        ], $expectedVersion, $captureResponseId);
        if ((int)($result['resultado'] ?? 0) !== 200) return $result + ['code' => 'DIRECT_CAPTURE_REJECTED'];

        $after = $this->read($patientId);
        $readback = null;
        $readbackResponseId = 0;
        foreach ($after['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] === $questionId) {
                $readback = trim((string)$candidate['respuesta']);
                $readbackResponseId = (int)($candidate['idRespuesta'] ?? 0);
                break;
            }
        }
        if ($readback !== $value) return ['resultado' => 500, 'mensaje' => 'No fue posible verificar la respuesta guardada.', 'code' => 'DIRECT_READBACK_FAILED'];

        $nextQuestionId = null;
        foreach ($after['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] <= $questionId || !$candidate['eligible']) continue;
            $semanticState = strtoupper(trim((string)($candidate['semanticState'] ?? '')));
            if (trim((string)$candidate['respuesta']) === ''
                && !in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true)) {
                $nextQuestionId = (int)$candidate['idPregunta'];
                break;
            }
        }
        $eligibleIds = array_values(array_map(
            static fn(array $row): int => (int)$row['idPregunta'],
            array_filter($after['questions'], static fn(array $row): bool => !empty($row['eligible']))
        ));
        $editableIds = array_values(array_map(
            static fn(array $row): int => (int)$row['idPregunta'],
            array_filter($after['questions'], static fn(array $row): bool => !empty($row['historicalEditable']))
        ));
        return array_merge($result, [
            'mode' => 'direct',
            'code' => 'DIRECT_CAPTURE_ACCEPTED',
            'readback' => true,
            'readback_value' => $readback,
            'response_id' => $readbackResponseId,
            'eligible_question_ids' => $eligibleIds,
            'editable_question_ids' => $editableIds,
            'next_question_id' => $nextQuestionId,
            'version' => $after['version'],
        ]);
    }

    private function isNumericQuestion(array $question): bool
    {
        $prompt = mb_strtolower(trim((string)($question['pregunta'] ?? '')), 'UTF-8');
        return preg_match('/\b(?:cu[aá]ntos|cu[aá]nta|cu[aá]nto|qu[eé] edad|hace cu[aá]nto|durante cu[aá]nto)\b/u', $prompt) === 1;
    }

    private function isDirectLiteralValue(string $value): bool
    {
        if (preg_match('/^\d+(?:[.,]\d+)?$/u', $value)) return true;
        return preg_match('/^(?:hace|desde hace|durante)\s+(?:\d+(?:[.,]\d+)?|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce)\s+(?:a[nñ]os?|mes(?:es)?|semanas?|d[ií]as?)\.?$/iu', $value) === 1;
    }

    /**
     * The client may request an edit, but the server authorizes only an
     * eligible, persisted answer in the contiguous completed history before
     * the first pending question. The UI flag is never an authorization input.
     */
    private function serverAuthorizesHistoricalEdit(array $questions, int $questionId): bool
    {
        foreach ($questions as $question) {
            if ((int)($question['idPregunta'] ?? 0) === $questionId) {
                return !empty($question['eligible']) && !empty($question['historicalEditable']);
            }
        }
        return false;
    }

    /**
     * Selects the next server-eligible unanswered question after a confirmed
     * capture. Explicit UNKNOWN/NOT_REMEMBERED states are resolved responses,
     * not pending blanks. The question list is already ordered by catalog ID.
     */
    private function nextPendingQuestionId(array $questions, int $currentQuestionId): ?int
    {
        $afterCurrent = false;
        foreach ($questions as $question) {
            $questionId = (int)($question['idPregunta'] ?? 0);
            if (!$afterCurrent) {
                if ($questionId === $currentQuestionId) $afterCurrent = true;
                continue;
            }
            if ($questionId < 1 || empty($question['eligible'])) continue;
            $semanticState = strtoupper(trim((string)($question['semanticState'] ?? $question['estadoRespuesta'] ?? '')));
            $resolvedWithoutText = in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true);
            if (!$resolvedWithoutText && trim((string)($question['respuesta'] ?? '')) === '') return $questionId;
        }
        return null;
    }

    private function hasDependents(array $questions, int $theme): bool
    {
        foreach ($questions as $question) if ((int)$question['idTema'] === $theme && (int)$question['idPregunta'] !== (self::PARENT_BY_THEME[$theme] ?? -1)) return true;
        return false;
    }

    private function dependentResponses(int $patientId, int $theme, int $parentQuestionId): array
    {
        $stmt = $this->connection()->prepare('SELECT r.id_pregunta AS question_id, p.pregunta AS question FROM pac_respuestas_paciente_modulo_3 r INNER JOIN pac_preguntas_modulo_3 p ON p.id=r.id_pregunta WHERE r.id_paciente=:patient AND p.id_tema=:theme AND p.id<>:parent AND TRIM(COALESCE(r.respuesta,\'\'))<>\'\' ORDER BY p.id');
        $stmt->execute([':patient'=>$patientId, ':theme'=>$theme, ':parent'=>$parentQuestionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
