<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** Conversational M05 adapter over the existing V1 pathological-history contract. */
final class ConversationalM05Adapter
{
    private const V2_TYPE_UNKNOWN_VALUE = 'No lo sé';
    // The legacy year_diagnostico column is a short VARCHAR in the available
    // fixture schema. Keep this explicit semantic marker within that field's
    // existing storage contract; never present it as a calendar year.
    private const V2_YEAR_NOT_REMEMBERED_VALUE = 'NR';

    public function __construct(private ?PDO $db = null) {}
    private function connection(): PDO { return $this->db ?: Database::getInstance(); }

    /**
     * M05 V1 uses the catalog structure as its dependency contract: the first
     * select question in a theme is the parent and following input questions
     * in that same theme are affirmative-only details. This projection is
     * deliberately independent of visible wording and does not add schema
     * columns or infer a negative from an unknown response.
     */
    public static function resolveV1Eligibility(array $questions): array
    {
        $parentByTheme = [];
        foreach ($questions as $index => $row) {
            if (!is_array($row)) continue;
            $themeId = (int)($row['theme_id'] ?? 0);
            if ($themeId < 1 || (string)($row['tipo'] ?? '') !== 'select') continue;
            if (!array_key_exists($themeId, $parentByTheme)) $parentByTheme[$themeId] = $index;
        }

        $resolved = [];
        foreach ($questions as $index => $row) {
            if (!is_array($row)) continue;
            $themeId = (int)($row['theme_id'] ?? 0);
            $value = trim((string)($row['respuesta'] ?? ''));
            $type = (string)($row['tipo'] ?? '');
            $parentIndex = $parentByTheme[$themeId] ?? null;
            $isParent = $parentIndex !== null && $parentIndex === $index;
            $eligible = true;
            $dependencyState = 'not_applicable';
            $historicalPreserved = false;
            $needsClarification = false;
            $parentQuestionId = null;

            if ($isParent && $type === 'select') {
                $dependencyState = 'parent';
                if ($value !== '' && !in_array($value, ['Si', 'Sí', 'No'], true)) {
                    $needsClarification = true;
                    $dependencyState = 'unknown_parent';
                }
            } elseif ($parentIndex !== null && $type !== 'select') {
                $parent = $questions[$parentIndex] ?? [];
                $parentQuestionId = (int)($parent['question_id'] ?? 0);
                $parentValue = trim((string)($parent['respuesta'] ?? ''));
                if ($parentValue === 'Si' || $parentValue === 'Sí') {
                    $dependencyState = 'enabled_by_affirmative';
                } elseif ($parentValue === 'No') {
                    $eligible = false;
                    $dependencyState = $value === ''
                        ? 'skipped_by_negative'
                        : 'skipped_by_negative_historical_preserved';
                    $historicalPreserved = $value !== '';
                } elseif ($parentValue === '') {
                    $eligible = false;
                    $dependencyState = 'pending_parent';
                } else {
                    $eligible = false;
                    $dependencyState = 'blocked_by_unknown_parent';
                }
            }

            $resolvedRow = $row;
            $resolvedRow['eligible'] = $eligible;
            $resolvedRow['dependency_parent_question_id'] = $parentQuestionId;
            $resolvedRow['dependency_state'] = $dependencyState;
            $resolvedRow['historical_preserved'] = $historicalPreserved;
            $resolvedRow['needs_clarification'] = $needsClarification;
            $resolved[] = $resolvedRow;
        }

        return $resolved;
    }

    /**
     * Binary M05 answers are classified from the complete utterance. Known,
     * contextual or not-remembered responses never become a silent No.
     */
    public static function classifyBinaryResponse(string $utterance): array
    {
        $text = trim($utterance);
        $normalized = mb_strtolower($text, 'UTF-8');
        $unknownOrContextual = [
            '/\bno\s+(lo|la)\s+sé\b/u',
            '/\bno\s+recuerdo\b/u',
            '/\bdesconozco\b/u',
            '/\bno\s+estoy\s+segur[oa]\b/u',
            '/\bahora\s+no\b.*\bpero\b.*\b(s[ií]|antes)\b/u',
            '/\bantes\s+(s[ií])\b/u',
        ];
        foreach ($unknownOrContextual as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return ['value' => null, 'classification' => 'ambiguous_or_unknown', 'clarification' => 'Aclara si la respuesta a esta pregunta es sí o no.', 'clarification_event_id' => 'M05:message:V1_CONTEXT'];
            }
        }

        $no = preg_match('/\b(no|nunca|negativo)\b/u', $normalized) === 1;
        $explicitYes = preg_match('/\b(s[ií]|afirmativo|correcto)\b/u', $normalized) === 1;
        // A negated diagnosis phrase (for example, "No me han diagnosticado")
        // is negative evidence, not an affirmative cue from the word "diagnosticado".
        $diagnosisCue = !$no && preg_match('/\bdiagnosticad[oa]\b/u', $normalized) === 1;
        $yes = $explicitYes || $diagnosisCue;
        if ($yes && !$no) return ['value' => 'Si', 'classification' => 'explicit_yes'];
        if ($no && !$yes) return ['value' => 'No', 'classification' => 'explicit_no'];
        return ['value' => null, 'classification' => 'ambiguous_or_unknown', 'clarification' => 'Responde sí o no para esta pregunta M05.', 'clarification_event_id' => 'M05:message:V1_BINARY'];
    }

    /** Return a year only when the full response contains exactly one calendar-year token. */
    public static function extractV2Year(string $utterance): ?string
    {
        preg_match_all('/\b(?:19|20)\d{2}\b/u', $utterance, $matches);
        $years = array_values(array_unique($matches[0] ?? []));
        return count($years) === 1 ? (string)$years[0] : null;
    }

    /** The V2 catalog supports distinct type values only for these diseases. */
    public static function v2TypesForDisease(string $disease): array
    {
        return match (trim($disease)) {
            'Diabetes Mellitus' => ['Tipo 1' => 'tipo 1', 'Tipo 2' => 'tipo 2', 'Gestacional' => 'gestacional', 'Otro' => 'otro'],
            'Enfermedad pulmonar' => ['EPOC' => 'epoc', 'Asma' => 'asma', 'Otro' => 'otro'],
            default => [],
        };
    }

    public static function classifyV2DiseaseType(string $disease, string $utterance): ?string
    {
        $normalized = mb_strtolower($utterance, 'UTF-8');
        $patterns = [
            'Tipo 1' => '/\btipo\s*1\b/u',
            'Tipo 2' => '/\btipo\s*2\b/u',
            'Gestacional' => '/\bgestacional\b/u',
            'EPOC' => '/\bepoc\b/u',
            'Asma' => '/\basma\b/u',
            'Otro' => '/\botro\b/u',
        ];
        foreach (self::v2TypesForDisease($disease) as $value => $_needle) {
            if (isset($patterns[$value]) && preg_match($patterns[$value], $normalized) === 1) return $value;
        }
        return null;
    }

    /** Pure proposal validation, shared by capture and no-database contracts. */
    public static function normalizeV2Changes(string $disease, array $changes): ?array
    {
        $normalized = [];
        foreach ($changes as $change) {
            if (!is_array($change)) return null;
            $edition = (int)($change['edicion'] ?? 0);
            $detail = trim((string)($change['detalle'] ?? ''));
            if (!in_array($edition, [1, 3], true) || $detail === '' || array_key_exists($edition, $normalized)) return null;
            if ($edition === 1 && self::classifyBinaryResponse($detail)['value'] === null) return null;
            if ($edition === 3 && $detail !== self::V2_YEAR_NOT_REMEMBERED_VALUE
                && !preg_match('/^(19|20)\d{2}$/', $detail)) return null;
            $normalized[$edition] = $detail;
        }

        if (!isset($normalized[1])) return null;
        $responseClass = self::classifyBinaryResponse($normalized[1])['value'] ?? null;
        if (isset($normalized[3])) {
            if ($responseClass !== 'Si' || $normalized[3] === self::V2_YEAR_NOT_REMEMBERED_VALUE
                || self::extractV2Year($normalized[1]) !== $normalized[3]) return null;
        }
        return $normalized;
    }

    /** Normalize one server-authorized V2 step without replaying prior fields. */
    public static function normalizeV2Step(string $disease, string $activeField, array $changes, array $current): ?array
    {
        if (!in_array($activeField, ['response', 'year_diagnostico'], true)
            || (string)($current['active_field'] ?? '') !== $activeField) return null;

        if ($activeField === 'response') {
            if (trim((string)($current['respuesta'] ?? '')) !== '') return null;
            if (!in_array(count($changes), [1, 2], true)) return null;
            $normalized = self::normalizeV2Changes($disease, $changes);
            if ($normalized === null || !isset($normalized[1])) return null;
            $classification = self::classifyBinaryResponse($normalized[1])['value'] ?? null;
            if (isset($normalized[3]) && $classification !== 'Si') return null;
            return $normalized;
        }

        if ((self::classifyBinaryResponse((string)($current['respuesta'] ?? ''))['value'] ?? null) !== 'Si' || count($changes) !== 1) return null;
        $change = array_values($changes)[0] ?? null;
        if (!is_array($change)) return null;
        $edition = (int)($change['edicion'] ?? 0);
        $detail = trim((string)($change['detalle'] ?? ''));
        if ($edition !== 3 || trim((string)($current['year_diagnostico'] ?? '')) !== '') return null;
        if ($detail !== self::V2_YEAR_NOT_REMEMBERED_VALUE && !preg_match('/^(19|20)\d{2}$/', $detail)) return null;
        return [3 => $detail];
    }

    public static function v2QuestionText(array $disease): string
    {
        $name = trim((string)($disease['enfermedad'] ?? 'esta enfermedad'));
        return match ((string)($disease['active_field'] ?? 'response')) {
            'year_diagnostico' => '¿En qué año te diagnosticaron ' . $name . '?',
            default => '¿Has sido diagnosticado con ' . $name . '?',
        };
    }

    /** Pure state projection so readback and contract tests share field ordering. */
    public static function deriveV2ClarificationState(array $disease): array
    {
        $response = trim((string)($disease['respuesta'] ?? ''));
        $year = trim((string)($disease['year_diagnostico'] ?? ''));
        $classification = self::classifyBinaryResponse($response)['value'] ?? null;
        $missing = [];
        if ($classification === 'Si' && $year === '') $missing[] = 'year_diagnostico';
        $responsePending = $response === '' || $classification === null;
        return [
            'needs_clarification' => $responsePending || $missing !== [],
            'missing_fields' => $missing,
            'active_field' => $responsePending ? 'response' : ($missing[0] ?? null),
            'year_state' => $year === '' ? 'PENDING' : ($year === self::V2_YEAR_NOT_REMEMBERED_VALUE ? 'NOT_REMEMBERED' : 'KNOWN'),
        ];
    }

    /**
     * Return the single contextual action authorized for the active V1 row.
     * Open answers persist the exact literal "No recuerdo" in their existing
     * response field; this is a durable text marker, not a new schema enum.
     */
    public static function contextualActionForV1(array $question, bool $writeEnabled, bool $finalized = false): ?array
    {
        if (!$writeEnabled || $finalized || ($question['eligible'] ?? true) !== true
            || trim((string)($question['respuesta'] ?? '')) !== '') return null;

        return match ((string)($question['tipo'] ?? '')) {
            'select' => ['type' => 'NEGATIVE', 'label' => 'No, guardar y continuar', 'value' => 'No'],
            'input' => ['type' => 'NOT_REMEMBERED', 'label' => 'No recuerdo, guardar y continuar', 'value' => 'No recuerdo', 'semantic_state' => 'NOT_REMEMBERED'],
            default => null,
        };
    }

    /** Return the contextual negative action for an unanswered V2 disease. */
    public static function contextualActionForV2(array $disease, bool $writeEnabled, bool $finalized = false): ?array
    {
        if (!$writeEnabled || $finalized || trim((string)($disease['respuesta'] ?? '')) !== '') return null;
        return ['type' => 'NEGATIVE', 'label' => 'No, guardar y continuar', 'value' => 'No'];
    }

    public static function contextualActionForV2Field(array $disease, bool $writeEnabled, bool $finalized = false): ?array
    {
        if (!$writeEnabled || $finalized || (self::classifyBinaryResponse((string)($disease['respuesta'] ?? ''))['value'] ?? null) !== 'Si') return null;
        return match ((string)($disease['active_field'] ?? '')) {
            'year_diagnostico' => trim((string)($disease['year_diagnostico'] ?? '')) === ''
                ? ['type' => 'NOT_REMEMBERED', 'label' => 'No recuerdo', 'value' => 'No recuerdo']
                : null,
            default => null,
        };
    }

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $db = $this->connection();
        $q = $db->prepare('SELECT t.id AS theme_id,t.tema,q.id AS question_id,q.pregunta,q.tipo,COALESCE(r.id,0) AS response_id,COALESCE(r.respuesta,"") AS respuesta FROM pac_temas_modulo_5 t INNER JOIN pac_preguntas_modulo_5 q ON q.id_tema=t.id LEFT JOIN pac_respuestas_paciente_modulo_5 r ON r.id_pregunta=q.id AND r.id_paciente=:id ORDER BY t.id,q.id');
        $q->execute([':id'=>$patientId]); $questions=$q->fetchAll(PDO::FETCH_ASSOC);
        $d = $db->prepare('SELECT e.id AS disease_id,e.descripcion AS enfermedad,COALESCE(r.id,0) AS response_id,COALESCE(r.respuesta,"") AS respuesta,COALESCE(r.tipo,"") AS tipo,COALESCE(r.year_diagnostico,"") AS year_diagnostico FROM pac_enfermedades_modulo_5 e LEFT JOIN pac_enfermedades_respuestas_modulo_5 r ON r.id_enfermedad=e.id AND r.id_paciente=:id ORDER BY e.id');
        $d->execute([':id'=>$patientId]); $diseases=$d->fetchAll(PDO::FETCH_ASSOC);
        $questions = self::resolveV1Eligibility($questions);
        $version = hash('sha256', json_encode(['questions'=>$questions,'diseases'=>$diseases], JSON_UNESCAPED_UNICODE));
        $policy=(new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId,5); $pending=null;
        foreach($questions as $row) {
            $value = trim((string)($row['respuesta'] ?? ''));
            $needsClarification = ($row['needs_clarification'] ?? false) === true;
            if (($row['eligible'] ?? true) === true && ($value === '' || $needsClarification)) { $pending=$row; break; }
        }
        $finalized = (bool)($policy['finalized'] ?? true);
        $writeEnabled = (bool)($policy['editable'] ?? false);
        foreach ($diseases as &$disease) {
            $disease = array_merge($disease, self::deriveV2ClarificationState($disease));
        }
        unset($disease);
        $pendingDisease = null;
        foreach ($diseases as $disease) {
            if (trim((string)($disease['respuesta'] ?? '')) === '' || !empty($disease['needs_clarification'])) {
                $pendingDisease = $disease;
                break;
            }
        }
        $v2VersionDiseases = array_map(static function (array $disease): array {
            return array_intersect_key($disease, array_flip([
                'disease_id', 'enfermedad', 'response_id', 'respuesta', 'year_diagnostico',
                'needs_clarification', 'missing_fields', 'active_field', 'year_state',
            ]));
        }, $diseases);
        $v2Version = hash('sha256', json_encode([
            'patient_id' => $patientId,
            'diseases' => $v2VersionDiseases,
            'finalized' => $finalized,
            'write_enabled' => $writeEnabled,
        ], JSON_UNESCAPED_UNICODE));
        $previousV1Version = hash('sha256', json_encode([
            'patient_id' => $patientId,
            'questions' => $questions,
            'finalized' => $finalized,
        ], JSON_UNESCAPED_UNICODE));
        $previousV2Diseases = array_map(static function (array $disease): array {
            return array_intersect_key($disease, array_flip([
                'disease_id', 'enfermedad', 'response_id', 'respuesta', 'tipo', 'year_diagnostico',
                'needs_clarification', 'missing_fields', 'active_field', 'year_state',
            ]));
        }, $diseases);
        $previousV2Version = hash('sha256', json_encode([
            'patient_id' => $patientId,
            'diseases' => $previousV2Diseases,
            'finalized' => $finalized,
        ], JSON_UNESCAPED_UNICODE));
        return ['key'=>'M05','title'=>'Antecedentes personales patológicos','questions'=>$questions,'diseases'=>$diseases,'pending_question'=>$pending,'pending_disease'=>$pendingDisease,'version'=>$version,'v2_version'=>$v2Version,'previous_v1_version'=>$previousV1Version,'previous_v2_version'=>$previousV2Version,'finalized'=>$finalized,'write_enabled'=>$writeEnabled,'write_blocker'=>$policy['reason']??null,'policy_source'=>$policy['source']??'pac_historia_clinica_finalizar','disease_write_enabled'=>$writeEnabled,'disease_write_blocker'=>$policy['reason']??null];
    }

    /** Update one existing M05 V1 history row without creating or finalizing anything. */
    public function editPreviousResponseV1(int $patientId, int $questionId, int $responseId, string $expectedVersion, string $answer): array
    {
        if ($patientId < 1 || $questionId < 1 || $responseId < 1
            || preg_match('/^[a-f0-9]{64}$/D', $expectedVersion) !== 1
            || trim($answer) === '' || mb_strlen($answer, 'UTF-8') > 2000) {
            return ['resultado' => 422, 'mensaje' => 'La respuesta M05 no es válida.'];
        }

        $db = $this->connection();
        $lockName = 'clinica_m05_previous_' . $patientId;
        $lockAcquired = false;
        try {
            $lock = $db->prepare('SELECT GET_LOCK(:name,5)');
            $lock->execute([':name' => $lockName]);
            $lockAcquired = (int)$lock->fetchColumn() === 1;
            if (!$lockAcquired) return ['resultado' => 409, 'mensaje' => 'M05 se está actualizando en otra operación.'];

            $db->beginTransaction();
            $rowLocks = $db->prepare('SELECT id FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente=:patient ORDER BY id FOR UPDATE');
            $rowLocks->execute([':patient' => $patientId]);
            $rowLocks->fetchAll(PDO::FETCH_COLUMN);

            $state = $this->read($patientId);
            if (!hash_equals((string)$state['previous_v1_version'], $expectedVersion)) {
                $db->rollBack();
                return ['resultado' => 409, 'mensaje' => 'Las respuestas de M05 cambiaron. Recarga para revisar la versión actual.'];
            }
            $matching = array_values(array_filter(
                $state['questions'],
                static fn(array $row): bool => (int)($row['question_id'] ?? 0) === $questionId
            ));
            if (count($matching) !== 1 || (int)($matching[0]['response_id'] ?? 0) !== $responseId) {
                $db->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta M05 ya no está disponible para edición.'];
            }
            $question = $matching[0];
            if (($question['eligible'] ?? true) !== true) {
                $db->rollBack();
                return ['resultado' => 403, 'mensaje' => 'La dependencia de esta respuesta M05 no está habilitada para edición histórica.'];
            }
            $questionType = (string)($question['tipo'] ?? '');
            if (!in_array($questionType, ['select', 'input'], true)
                || ($questionType === 'select' && !in_array($answer, ['Si', 'No'], true))) {
                $db->rollBack();
                return ['resultado' => 422, 'mensaje' => 'La respuesta no corresponde al tipo de esta pregunta M05.'];
            }

            $target = $db->prepare('SELECT id,id_paciente,id_pregunta,respuesta FROM pac_respuestas_paciente_modulo_5 WHERE id=:response AND id_paciente=:patient AND id_pregunta=:question FOR UPDATE');
            $target->execute([':response' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            $persistedBefore = $target->fetch(PDO::FETCH_ASSOC);
            if (!is_array($persistedBefore)) {
                $db->rollBack();
                return ['resultado' => 404, 'mensaje' => 'La respuesta M05 no pertenece a esta sesión.'];
            }

            $update = $db->prepare('UPDATE pac_respuestas_paciente_modulo_5 SET respuesta=:answer WHERE id=:response AND id_paciente=:patient AND id_pregunta=:question');
            $update->execute([':answer' => $answer, ':response' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            $db->commit();

            $readbackQuery = $db->prepare('SELECT id,id_paciente,id_pregunta,respuesta FROM pac_respuestas_paciente_modulo_5 WHERE id=:response AND id_paciente=:patient AND id_pregunta=:question');
            $readbackQuery->execute([':response' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            $readback = $readbackQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($readback) || (int)$readback['id'] !== $responseId
                || (int)$readback['id_paciente'] !== $patientId || (int)$readback['id_pregunta'] !== $questionId
                || (string)$readback['respuesta'] !== $answer) {
                return ['resultado' => 500, 'mensaje' => 'La respuesta se guardó, pero el servidor no confirmó su lectura posterior.', 'readback' => false];
            }
            $after = $this->read($patientId);
            $afterRows = array_values(array_filter($after['questions'], static fn(array $row): bool => (int)($row['question_id'] ?? 0) === $questionId));
            if (count($afterRows) !== 1 || (int)($afterRows[0]['response_id'] ?? 0) !== $responseId
                || (string)($afterRows[0]['respuesta'] ?? '') !== $answer) {
                return ['resultado' => 500, 'mensaje' => 'La respuesta se guardó, pero el servidor no confirmó la lectura posterior de M05.', 'readback' => false];
            }
            return [
                'resultado' => 200,
                'mensaje' => 'Respuesta M05 actualizada.',
                'response_id' => $responseId,
                'question_id' => $questionId,
                'answer' => (string)$readback['respuesta'],
                'version' => (string)$after['previous_v1_version'],
                'readback' => true,
            ];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado' => 422, 'mensaje' => 'No fue posible actualizar la respuesta M05.'];
        } finally {
            if ($lockAcquired) {
                try {
                    $release = $db->prepare('SELECT RELEASE_LOCK(:name)');
                    $release->execute([':name' => $lockName]);
                } catch (\Throwable) {
                    // Connection teardown releases the advisory lock if needed.
                }
            }
        }
    }

    /** Update one existing M05 V2 history row, preserving tipo and omitted year values. */
    public function editPreviousResponseV2(
        int $patientId,
        int $diseaseId,
        int $responseId,
        string $expectedVersion,
        string $answer,
        ?string $year,
        bool $clearYear
    ): array {
        if ($patientId < 1 || $diseaseId < 1 || $responseId < 1
            || preg_match('/^[a-f0-9]{64}$/D', $expectedVersion) !== 1
            || trim($answer) === '' || mb_strlen($answer, 'UTF-8') > 2000
            || ($year !== null && (preg_match('/^(19|20)\d{2}$/D', $year) !== 1 || (int)$year > (int)date('Y')))
            || ($clearYear && $year !== null)) {
            return ['resultado' => 422, 'mensaje' => 'La respuesta o el año de M05 no son válidos.'];
        }

        $db = $this->connection();
        $lockName = 'clinica_m05_previous_' . $patientId;
        $lockAcquired = false;
        try {
            $lock = $db->prepare('SELECT GET_LOCK(:name,5)');
            $lock->execute([':name' => $lockName]);
            $lockAcquired = (int)$lock->fetchColumn() === 1;
            if (!$lockAcquired) return ['resultado' => 409, 'mensaje' => 'M05 se está actualizando en otra operación.'];

            $db->beginTransaction();
            $rowLocks = $db->prepare('SELECT id FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente=:patient ORDER BY id FOR UPDATE');
            $rowLocks->execute([':patient' => $patientId]);
            $rowLocks->fetchAll(PDO::FETCH_COLUMN);

            $state = $this->read($patientId);
            if (!hash_equals((string)$state['previous_v2_version'], $expectedVersion)) {
                $db->rollBack();
                return ['resultado' => 409, 'mensaje' => 'Las respuestas de enfermedades M05 cambiaron. Recarga para revisar la versión actual.'];
            }
            $matching = array_values(array_filter(
                $state['diseases'],
                static fn(array $row): bool => (int)($row['disease_id'] ?? 0) === $diseaseId
            ));
            if (count($matching) !== 1 || (int)($matching[0]['response_id'] ?? 0) !== $responseId) {
                $db->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta de esa enfermedad M05 ya no está disponible para edición.'];
            }
            $disease = $matching[0];
            $target = $db->prepare('SELECT id,id_paciente,id_enfermedad,respuesta,tipo,year_diagnostico FROM pac_enfermedades_respuestas_modulo_5 WHERE id=:response AND id_paciente=:patient AND id_enfermedad=:disease FOR UPDATE');
            $target->execute([':response' => $responseId, ':patient' => $patientId, ':disease' => $diseaseId]);
            $before = $target->fetch(PDO::FETCH_ASSOC);
            if (!is_array($before) || (int)$before['id'] !== $responseId
                || (int)$before['id_paciente'] !== $patientId || (int)$before['id_enfermedad'] !== $diseaseId) {
                $db->rollBack();
                return ['resultado' => 404, 'mensaje' => 'La respuesta M05 no pertenece a esta sesión.'];
            }
            $originalYear = (string)($before['year_diagnostico'] ?? '');
            $responseClass = self::classifyBinaryResponse($answer)['value'] ?? null;
            if ($responseClass === 'No' && $year !== null) {
                $db->rollBack();
                return ['resultado' => 422, 'mensaje' => 'Una respuesta negativa no puede conservar un año nuevo de diagnóstico.'];
            }
            if ($responseClass === 'No' && $originalYear !== '' && !$clearYear) {
                $db->rollBack();
                return ['resultado' => 422, 'mensaje' => 'La respuesta negativa requiere confirmar la eliminación del año de diagnóstico guardado.'];
            }
            $effectiveYear = $clearYear ? '' : ($year ?? $originalYear);

            $update = $db->prepare('UPDATE pac_enfermedades_respuestas_modulo_5 SET respuesta=:answer,year_diagnostico=:year WHERE id=:response AND id_paciente=:patient AND id_enfermedad=:disease');
            $update->execute([
                ':answer' => $answer,
                ':year' => $effectiveYear,
                ':response' => $responseId,
                ':patient' => $patientId,
                ':disease' => $diseaseId,
            ]);
            $db->commit();

            $readbackQuery = $db->prepare('SELECT id,id_paciente,id_enfermedad,respuesta,tipo,year_diagnostico FROM pac_enfermedades_respuestas_modulo_5 WHERE id=:response AND id_paciente=:patient AND id_enfermedad=:disease');
            $readbackQuery->execute([':response' => $responseId, ':patient' => $patientId, ':disease' => $diseaseId]);
            $readback = $readbackQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($readback) || (int)$readback['id'] !== $responseId
                || (int)$readback['id_paciente'] !== $patientId || (int)$readback['id_enfermedad'] !== $diseaseId
                || (string)$readback['respuesta'] !== $answer
                || (string)$readback['year_diagnostico'] !== $effectiveYear
                || (string)$readback['tipo'] !== (string)($before['tipo'] ?? '')) {
                return ['resultado' => 500, 'mensaje' => 'La respuesta se guardó, pero el servidor no confirmó sus campos después de leerla.', 'readback' => false];
            }
            $after = $this->read($patientId);
            $afterRows = array_values(array_filter($after['diseases'], static fn(array $row): bool => (int)($row['disease_id'] ?? 0) === $diseaseId));
            if (count($afterRows) !== 1 || (int)($afterRows[0]['response_id'] ?? 0) !== $responseId
                || (string)($afterRows[0]['respuesta'] ?? '') !== $answer
                || (string)($afterRows[0]['year_diagnostico'] ?? '') !== $effectiveYear
                || (string)($afterRows[0]['tipo'] ?? '') !== (string)($before['tipo'] ?? '')) {
                return ['resultado' => 500, 'mensaje' => 'La respuesta se guardó, pero el servidor no confirmó la lectura posterior de M05.', 'readback' => false];
            }
            return [
                'resultado' => 200,
                'mensaje' => 'Respuesta de enfermedad M05 actualizada.',
                'record' => $afterRows[0],
                'response_id' => $responseId,
                'disease_id' => $diseaseId,
                'version' => (string)$after['previous_v2_version'],
                'readback' => true,
                'tipo_unchanged' => true,
            ];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado' => 422, 'mensaje' => 'No fue posible actualizar la respuesta M05.'];
        } finally {
            if ($lockAcquired) {
                try {
                    $release = $db->prepare('SELECT RELEASE_LOCK(:name)');
                    $release->execute([':name' => $lockName]);
                } catch (\Throwable) {
                    // Connection teardown releases the advisory lock if needed.
                }
            }
        }
    }

    public function interpret(int $questionId,string $utterance): array
    {
        if($questionId<1||trim($utterance)==='') throw new \InvalidArgumentException('Indica una respuesta M05.');
        $s=$this->connection()->prepare('SELECT id,id_tema,pregunta,tipo FROM pac_preguntas_modulo_5 WHERE id=:id LIMIT 1'); $s->execute([':id'=>$questionId]); $question=$s->fetch(PDO::FETCH_ASSOC);
        if(!$question) throw new \DomainException('La pregunta M05 no pertenece al catálogo autorizado.');
        $text=trim($utterance); $type=(string)$question['tipo'];
        if($type==='select') { $classification=self::classifyBinaryResponse($text); if(($classification['value']??null)===null)return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>$classification['clarification']??'Responde sí o no para esta pregunta M05.','clarification_event_id'=>$classification['clarification_event_id']??'M05:message:V1_BINARY','requires_confirmation'=>false,'can_confirm'=>false]; $value=(string)$classification['value']; }
        else $value=$text;
        return ['mode'=>'deterministic','kind'=>'m05_proposal','target'=>'v1','question_id'=>(int)$question['id'],'theme_id'=>(int)$question['id_tema'],'question_type'=>$type,'structured_value'=>$value,'detail'=>$value,'interpretation'=>'Respuesta M05 validada conforme al tipo del catálogo.','requires_confirmation'=>false,'can_confirm'=>false,'direct_save'=>true];
    }

    public function capture(int $patientId,array $proposal,?string $expectedVersion): array
    {
        if($patientId<1||($proposal['kind']??'')!=='m05_proposal'||($proposal['target']??'')!=='v1') return ['resultado'=>422,'mensaje'=>'La propuesta M05 no es válida.'];
        $questionId=(int)($proposal['question_id']??0); $themeId=(int)($proposal['theme_id']??0); $value=trim((string)($proposal['structured_value']??$proposal['detail']??''));
        if($questionId<1||$themeId<1||$value==='') return ['resultado'=>422,'mensaje'=>'La respuesta M05 no es válida.'];
        $db=$this->connection(); $lockName='clinica_m05_capture_'.$patientId.'_'.$questionId; $lock=$db->prepare('SELECT GET_LOCK(:name,5)'); $lock->execute([':name'=>$lockName]);
        if((int)$lock->fetchColumn()!==1) return ['resultado'=>409,'mensaje'=>'No fue posible confirmar M05; inténtalo de nuevo.'];
        try {
            $state=$this->read($patientId); if(!$state['write_enabled']) return ['resultado'=>409,'mensaje'=>'M05 está finalizado o su estado editable no está disponible.'];
            if($expectedVersion!==null&&!hash_equals((string)$state['version'],$expectedVersion)) return ['resultado'=>409,'mensaje'=>'M05 cambió mientras se revisaba. Recarga antes de confirmar.'];
            $question=null; foreach($state['questions'] as $row) if((int)$row['question_id']===$questionId&&(int)$row['theme_id']===$themeId){$question=$row;break;}
            if(!$question||(string)$question['tipo']!==(string)($proposal['question_type']??$question['tipo'])) return ['resultado'=>403,'mensaje'=>'La pregunta M05 no está autorizada.'];
            if (($question['eligible'] ?? true) !== true) return ['resultado'=>409,'mensaje'=>'La pregunta M05 no está disponible según la respuesta anterior.'];
            if($question['tipo']==='select'&&!in_array($value,['Si','No'],true)) return ['resultado'=>422,'mensaje'=>'La respuesta M05 no coincide con el catálogo.'];
            if($question['tipo']==='select'&&$value==='No') foreach($state['questions'] as $row) if((int)$row['theme_id']===$themeId&&(int)$row['question_id']!==$questionId&&trim((string)$row['respuesta'])!=='') return ['resultado'=>409,'mensaje'=>'M05 tiene respuestas dependientes; no se modificaron.'];
            $result=(new \App\Models\AntecedentesPatologicosModel())->editarCuestionarioV1Modulo(['idPaciente'=>$patientId,'idRespuesta'=>(int)$question['response_id'],'idPregunta'=>$questionId,'idTema'=>$themeId,'detalle'=>$value,'idTipo'=>(string)$question['tipo']]);
            if((int)($result['resultado']??0)!==200)return ['resultado'=>(int)($result['resultado']??422),'mensaje'=>'No fue posible guardar M05.'];
            $after=$this->read($patientId); $record=null; foreach($after['questions'] as $row) if((int)$row['question_id']===$questionId&&(int)$row['theme_id']===$themeId){$record=$row;break;}
            if (!is_array($record) || trim((string)($record['respuesta'] ?? '')) !== $value) {
                return ['resultado'=>500,'mensaje'=>'La respuesta M05 se guardó, pero el servidor no confirmó el readback.','readback'=>false];
            }
            $next=$after['pending_question']??null;
            $nextQuestion=is_array($next)?['question_id'=>(int)$next['question_id'],'theme_id'=>(int)$next['theme_id'],'tema'=>(string)$next['tema'],'pregunta'=>(string)$next['pregunta'],'tipo'=>(string)$next['tipo'],'stable_id'=>'M05:v1:'.(int)$next['question_id']]:null;
            $nextDisease=null;
            foreach (($after['diseases'] ?? []) as $disease) {
                if (trim((string)($disease['respuesta'] ?? '')) === '') {
                    $nextDisease=['disease_id'=>(int)$disease['disease_id'],'enfermedad'=>(string)$disease['enfermedad'],'stable_id'=>'M05:v2:'.(int)$disease['disease_id']];
                    break;
                }
            }
            $semanticState = $question['tipo'] === 'input' && $value === 'No recuerdo' ? 'NOT_REMEMBERED' : null;
            return ['resultado'=>200,'mensaje'=>'Respuesta M05 guardada.','record'=>$record,'semantic_state'=>$semanticState,'version'=>$after['version'],'readback'=>true,'next_question'=>$nextQuestion,'next_disease'=>$nextDisease];
        } catch(\Throwable){return ['resultado'=>422,'mensaje'=>'No fue posible guardar M05.'];}
        finally{$release=$db->prepare('SELECT RELEASE_LOCK(:name)');$release->execute([':name'=>$lockName]);}
    }

    public function interpretV2(int $diseaseId, string $utterance): array
    {
        if ($diseaseId < 1 || trim($utterance) === '') throw new \InvalidArgumentException('Indica una respuesta M05 V2.');
        $db = $this->connection();
        $stmt = $db->prepare('SELECT id,descripcion FROM pac_enfermedades_modulo_5 WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$diseaseId]); $disease=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$disease) throw new \DomainException('La enfermedad M05 no pertenece al catálogo autorizado.');
        $text=trim($utterance); $n=mb_strtolower($text,'UTF-8');
        $classification=self::classifyBinaryResponse($text);
        if (($classification['value'] ?? null) === null) {
            return ['mode'=>'deterministic','kind'=>'clarification','target'=>'v2','disease_id'=>$diseaseId,'disease'=>$disease['descripcion'],'clarification'=>'Indica si has sido diagnosticado con esta enfermedad: sí o no.','requires_confirmation'=>false,'can_confirm'=>false];
        }
        $response=(string)$classification['value']; $yes=$response==='Si'; $changes=[['edicion'=>1,'detalle'=>$response]];
        $dependent=in_array((string)$disease['descripcion'],['Diabetes Mellitus','Enfermedad pulmonar'],true);
        if ($yes && $dependent) {
            $type=null;
            foreach (['Tipo 1'=>'tipo 1','Tipo 2'=>'tipo 2','Gestacional'=>'gestacional','EPOC'=>'epoc','Asma'=>'asma'] as $value=>$needle) if (str_contains($n,$needle)) {$type=$value;break;}
            $year=null; if(preg_match('/\b(19|20)\d{2}\b/u',$n,$m))$year=$m[0];
            if ($type===null || $year===null) return ['mode'=>'deterministic','kind'=>'clarification','target'=>'v2','disease_id'=>$diseaseId,'disease'=>$disease['descripcion'],'response'=>'Si','missing'=>array_values(array_filter(['tipo'=>$type===null?'tipo de enfermedad':null,'year_diagnostico'=>$year===null?'año de diagnóstico':null])),'clarification'=>'Para completar esta respuesta afirmativa indica el tipo de enfermedad y el año aproximado del diagnóstico.','requires_confirmation'=>false,'can_confirm'=>false];
            $changes[]=['edicion'=>2,'detalle'=>$type]; $changes[]=['edicion'=>3,'detalle'=>$year];
        }
        return ['mode'=>'deterministic','kind'=>'m05_v2_proposal','target'=>'v2','disease_id'=>$diseaseId,'disease'=>$disease['descripcion'],'changes'=>$changes,'structured_value'=>['respuesta'=>$response,'tipo'=>$changes[1]['detalle']??'','year_diagnostico'=>$changes[2]['detalle']??''],'interpretation'=>'Respuesta M05 V2 validada conforme al catálogo y sus dependencias.','requires_confirmation'=>false,'can_confirm'=>false,'direct_save'=>true];
    }

    private function v2DiseasePayload(?array $disease): ?array
    {
        if (!is_array($disease)) return null;
        $id = (int)($disease['disease_id'] ?? 0);
        return [
            'disease_id' => $id,
            'enfermedad' => (string)($disease['enfermedad'] ?? ''),
            'stable_id' => 'M05:v2:' . $id,
            'active_field' => $disease['active_field'] ?? null,
            'respuesta' => (string)($disease['respuesta'] ?? ''),
            'year_diagnostico' => (string)($disease['year_diagnostico'] ?? ''),
            'needs_clarification' => !empty($disease['needs_clarification']),
            'missing_fields' => array_values($disease['missing_fields'] ?? []),
        ];
    }

    private function v2Conflict(array $state, string $code, string $message): array
    {
        return [
            'resultado' => 409,
            'codigo' => $code,
            'mensaje' => $message,
            'reconcile_contract' => true,
            'version' => (string)($state['version'] ?? ''),
            'v2_version' => (string)($state['v2_version'] ?? ''),
            'next_disease' => $this->v2DiseasePayload($state['pending_disease'] ?? null),
        ];
    }

    public function captureV2(int $patientId, array $proposal, ?string $expectedVersion): array
    {
        if ($patientId < 1 || ($proposal['kind'] ?? '') !== 'm05_v2_proposal' || ($proposal['target'] ?? '') !== 'v2') {
            return ['resultado'=>422,'mensaje'=>'La propuesta M05 V2 no es válida.'];
        }
        $diseaseId = (int)($proposal['disease_id'] ?? 0);
        $changes = $proposal['changes'] ?? [];
        if ($diseaseId < 1 || !is_array($changes) || $changes === []) {
            return ['resultado'=>422,'mensaje'=>'La propuesta M05 V2 no contiene cambios confirmables.'];
        }

        $db = $this->connection();
        $lockName = 'clinica_m05_v2_patient_' . $patientId;
        $lockAcquired = false;
        try {
            $lock = $db->prepare('SELECT GET_LOCK(:name,5)');
            $lock->execute([':name' => $lockName]);
            $lockAcquired = (int)$lock->fetchColumn() === 1;
            if (!$lockAcquired) {
                return $this->v2Conflict($this->read($patientId), 'M05_CAPTURE_BUSY', 'M05 se está actualizando en otra operación. Revisa el estado actual antes de confirmar.');
            }

            $db->beginTransaction();
            $rowLocks = $db->prepare('SELECT id FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente=:id ORDER BY id FOR UPDATE');
            $rowLocks->execute([':id' => $patientId]);
            $rowLocks->fetchAll(PDO::FETCH_COLUMN);

            $state = $this->read($patientId);
            if (empty($state['write_enabled'])) {
                $db->rollBack();
                return $this->v2Conflict($state, 'M05_WRITE_BLOCKED', 'M05 está finalizado o su estado editable no está disponible.');
            }
            if (!is_string($expectedVersion)
                || preg_match('/^[a-f0-9]{64}$/', $expectedVersion) !== 1
                || !hash_equals((string)$state['v2_version'], $expectedVersion)) {
                $db->rollBack();
                return $this->v2Conflict($state, 'M05_STALE_VERSION', 'M05 cambió mientras revisabas. Revisa la pregunta actual antes de confirmar nuevamente.');
            }

            $disease = null;
            $matchingDiseases = [];
            foreach ($state['diseases'] as $row) {
                if ((int)$row['disease_id'] === $diseaseId) $matchingDiseases[] = $row;
            }
            if (count($matchingDiseases) > 1) {
                $db->rollBack();
                return $this->v2Conflict($state, 'M05_DUPLICATE_RECORDS', 'M05 contiene registros ambiguos para esta respuesta. No se guardaron cambios.');
            }
            $disease = $matchingDiseases[0] ?? null;
            if (!is_array($disease)) {
                $db->rollBack();
                return ['resultado'=>403,'mensaje'=>'La enfermedad M05 V2 no está autorizada.'];
            }
            $pendingDisease = $state['pending_disease'] ?? null;
            if (!is_array($pendingDisease) || (int)$pendingDisease['disease_id'] !== $diseaseId) {
                $db->rollBack();
                return $this->v2Conflict($state, 'M05_PENDING_CHANGED', 'La enfermedad pendiente de M05 cambió. Revisa la pregunta actual antes de confirmar.');
            }
            $activeField = (string)($proposal['active_field'] ?? '');
            $stableId = (string)($proposal['stable_id'] ?? '');
            if ($activeField === '' || $activeField !== (string)($pendingDisease['active_field'] ?? '')
                || $stableId !== 'M05:v2:' . $diseaseId
                || !is_string($proposal['expected_version'] ?? null)
                || !hash_equals((string)$state['v2_version'], (string)$proposal['expected_version'])) {
                $db->rollBack();
                return $this->v2Conflict($state, 'M05_ACTIVE_FIELD_CHANGED', 'La pregunta activa de M05 cambió. Revisa la aclaración vigente antes de confirmar.');
            }

            $normalized = self::normalizeV2Step((string)$disease['enfermedad'], $activeField, $changes, $disease);
            if ($normalized === null) {
                $db->rollBack();
                return ['resultado'=>422,'mensaje'=>'La respuesta no corresponde al campo M05 activo.'];
            }

            $model = new \App\Models\AntecedentesPatologicosModel($db, true);
            foreach ($normalized as $edition => $detail) {
                $result = $model->editarCuestionarioV2Modulo([
                    'idPaciente' => $patientId,
                    'idEnfermedad' => (int)$disease['response_id'],
                    'idEnfermedadCatalogo' => $diseaseId,
                    'edicion' => $edition,
                    'detalle' => $detail,
                    'preserveV2Details' => true,
                ]);
                if ((int)($result['resultado'] ?? 0) !== 200) throw new \RuntimeException('M05 V2 persistence failed');
            }
            if (!$db->commit()) throw new \RuntimeException('M05 V2 transaction commit failed');

            $readbackQuery = $db->prepare('SELECT id,id_paciente,id_enfermedad,respuesta,year_diagnostico FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente=:patient AND id_enfermedad=:disease ORDER BY id');
            $readbackQuery->execute([':patient' => $patientId, ':disease' => $diseaseId]);
            $persistedRows = $readbackQuery->fetchAll(PDO::FETCH_ASSOC);
            $persisted = $persistedRows[0] ?? null;
            $expectedResponse = $normalized[1] ?? (string)($disease['respuesta'] ?? '');
            $expectedYear = $normalized[3] ?? (string)($disease['year_diagnostico'] ?? '');
            if (count($persistedRows) !== 1 || !is_array($persisted)
                || (int)$persisted['id_paciente'] !== $patientId
                || (int)$persisted['id_enfermedad'] !== $diseaseId
                || (string)$persisted['respuesta'] !== $expectedResponse
                || (string)$persisted['year_diagnostico'] !== $expectedYear) {
                return ['resultado'=>500,'mensaje'=>'La respuesta M05 V2 se guardó, pero el servidor no confirmó los campos persistidos.','readback'=>false];
            }

            $after = $this->read($patientId);
            $afterRows = array_values(array_filter($after['diseases'], static fn(array $row): bool => (int)$row['disease_id'] === $diseaseId));
            if (count($afterRows) !== 1) {
                return ['resultado'=>500,'mensaje'=>'La respuesta M05 V2 se guardó, pero el servidor no confirmó el readback del módulo.','readback'=>false];
            }
            $record = $afterRows[0];
            $followupRequired = !empty($record['needs_clarification']);
            $readbackFields = array_map(static fn(int $edition): string => [1 => 'respuesta', 3 => 'year_diagnostico'][$edition], array_keys($normalized));

            return [
                'resultado' => 200,
                'mensaje' => 'Respuesta M05 V2 guardada.',
                'record' => $record,
                'version' => $after['version'],
                'v2_version' => $after['v2_version'],
                'readback' => true,
                'readback_fields' => array_values(array_unique($readbackFields)),
                'followup_required' => $followupRequired,
                'clarification' => null,
                'next_disease' => $this->v2DiseasePayload($after['pending_disease'] ?? null),
            ];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado'=>422,'mensaje'=>'No fue posible guardar M05 V2.'];
        } finally {
            if ($lockAcquired) {
                try {
                    $release = $db->prepare('SELECT RELEASE_LOCK(:name)');
                    $release->execute([':name' => $lockName]);
                } catch (\Throwable) {
                    // The connection will release its advisory lock when it closes.
                }
            }
        }
    }
}
