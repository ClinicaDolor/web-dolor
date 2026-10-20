<?php

namespace App\Services;

use App\Config\Database;
use App\Models\AntecedentesNoPatologicosModel;
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

    public function __construct(private ?PDO $db = null) {}
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
        $db = $this->connection();
        $semanticStateAvailable = $this->semanticStateAvailable();
        $stateSelect = $semanticStateAvailable ? 'r.estado_respuesta' : 'NULL';
        $originSelect = $semanticStateAvailable ? 'r.origen_respuesta' : 'NULL';
        $stateAtSelect = $semanticStateAvailable ? 'r.estado_respuesta_at' : 'NULL';
        $stmt = $db->prepare("SELECT p.id AS idPregunta,p.id_tema AS idTema,t.tema,p.pregunta,p.tipo,r.id AS idRespuesta,COALESCE(r.respuesta,'') AS respuesta,{$stateSelect} AS estadoRespuesta,{$originSelect} AS origenRespuesta,{$stateAtSelect} AS estadoRespuestaAt FROM pac_preguntas_modulo_3 p INNER JOIN pac_temas_modulo_3 t ON t.id=p.id_tema LEFT JOIN pac_respuestas_paciente_modulo_3 r ON r.id_pregunta=p.id AND r.id_paciente=:patient ORDER BY p.id ASC");
        $stmt->execute([':patient' => $patientId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($questions as &$question) {
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
            $parent = self::PARENT_BY_THEME[$theme] ?? null;
            $question['parentQuestionId'] = $parent;
            $question['eligible'] = $parent === null || (int)$question['idPregunta'] === $parent || (($byId[$parent]['respuesta'] ?? '') === 'Si');
        }
        unset($question);
        $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 3);
        return ['key' => 'M03', 'questions' => $questions, 'version' => hash('sha256', json_encode($questions, JSON_UNESCAPED_UNICODE)), 'write_enabled' => (bool)($policy['editable'] ?? false), 'finalized' => (bool)($policy['finalized'] ?? true), 'write_blocker' => $policy['reason'] ?? null, 'semantic_state_available' => $semanticStateAvailable];
    }

    public function interpret(int $patientId, int $questionId, string $utterance): array
    {
        $text = trim($utterance);
        if ($text === '') throw new \InvalidArgumentException('La respuesta no puede estar vacía.');
        $state = $this->read($patientId); $question = null;
        foreach ($state['questions'] as $candidate) if ((int)$candidate['idPregunta'] === $questionId) { $question = $candidate; break; }
        if (!$question) throw new \DomainException('La pregunta no pertenece al catálogo M03.');
        if (!$question['eligible'] && trim((string)$question['respuesta']) === '') return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>'Primero responde la pregunta principal de este tema.','needs_clarification'=>true,'can_confirm'=>false];
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

    public function capture(int $patientId, array $proposal, ?string $expectedVersion): array
    {
        if ($patientId < 1 || ($proposal['kind'] ?? '') !== 'm03_proposal' || !isset($proposal['question_id'], $proposal['theme_id'])) return ['resultado'=>422,'mensaje'=>'La propuesta M03 no es válida.'];
        $state = $this->read($patientId); $question = null;
        foreach ($state['questions'] as $candidate) if ((int)$candidate['idPregunta'] === (int)$proposal['question_id']) { $question = $candidate; break; }
        if (!$question || (int)$question['idTema'] !== (int)$proposal['theme_id']) return ['resultado'=>404,'mensaje'=>'La pregunta M03 no pertenece al tema autorizado.'];
        if (!$state['write_enabled']) return ['resultado'=>403,'mensaje'=>'El módulo M03 no admite nuevas respuestas.'];
        if ($expectedVersion !== null && $expectedVersion !== '' && !hash_equals((string)$state['version'], $expectedVersion)) return ['resultado'=>409,'mensaje'=>'M03 cambió mientras se revisaba. Recarga antes de continuar.'];
        $value = trim((string)($proposal['structured_value'] ?? ''));
        $semanticState = strtoupper(trim((string)($proposal['semantic_state'] ?? '')));
        if ($semanticState === '') $semanticState = '';
        if ($semanticState !== '' && !in_array($semanticState, ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'], true)) return ['resultado'=>422,'mensaje'=>'El estado semántico M03 no es válido.'];
        if (in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) && $value !== '') {
            $canonicalValue = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($semanticState);
            $textualFallback = !($state['semantic_state_available'] ?? false)
                && $canonicalValue !== null
                && $value === $canonicalValue
                && ConversationalInterviewService::classifyM03Unknown($value) === $semanticState;
            if (!$textualFallback) return ['resultado'=>422,'mensaje'=>'El estado M03 no puede contener un valor clínico inventado.'];
        }
        if ($semanticState === '' && $value === '') return ['resultado'=>422,'mensaje'=>'La respuesta M03 no puede estar vacía.'];
        $unknownState = ConversationalInterviewService::classifyM03Unknown($value);
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
        if ($semanticState === 'NEGATIVE' && ((string)$question['tipo'] !== 'select' || $value !== 'No')) {
            return ['resultado'=>422,'mensaje'=>'La respuesta negativa no corresponde a esta pregunta.','code'=>'M03_NEGATIVE_STATE_MISMATCH','persist'=>false];
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
        $model = new AntecedentesNoPatologicosModel($this->connection());
        if ((string)$question['tipo'] === 'select' && $value === 'No') {
            $conflicts = $this->dependentResponses($patientId, (int)$question['idTema'], (int)$question['idPregunta']);
            if ($conflicts !== []) {
                return ['resultado'=>409,'mensaje'=>'No se puede cambiar esta respuesta porque existen respuestas dependientes. La corrección requiere un procedimiento clínico autorizado.','conflicts'=>$conflicts];
            }
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
            ]);
        } else {
            $result = $model->capturarRespuestaEntrevista(['idPaciente'=>$patientId,'idPregunta'=>(int)$question['idPregunta'],'idTema'=>(int)$question['idTema'],'detalle'=>$value,'estadoRespuesta'=>$semanticState,'origenRespuesta'=>'PATIENT_DECLARATION','expectedRespuesta'=>(string)$question['respuesta']]);
        }
        if ((int)($result['resultado'] ?? 0) !== 200) return $result + ['version' => $this->read($patientId)['version']];
        $after = $this->read($patientId);
        $readback = null;
        $readbackState = null;
        foreach ($after['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] === (int)$question['idPregunta']) {
                $readback = trim((string)$candidate['respuesta']);
                $readbackState = strtoupper(trim((string)($candidate['estadoRespuesta'] ?? '')));
                break;
            }
        }
        if ($readback !== $value || ($state['semantic_state_available'] && $readbackState !== $semanticState)) return ['resultado' => 500, 'mensaje' => 'No fue posible verificar la respuesta guardada.', 'code' => 'M03_READBACK_FAILED', 'readback' => false];
        return array_merge($result, ['version' => $after['version'], 'readback' => true, 'semantic_state' => $semanticState]);
    }

    /**
     * Captures a complete numeric input in one explicit save-and-continue
     * action. The server remains authoritative: the question must be open,
     * eligible, editable and of the catalog's input type. No numeric bound is
     * invented here; the existing catalog type is the only clinical rule.
     */
    public function captureDirectNumeric(int $patientId, int $questionId, string $value, ?string $expectedVersion, bool $allowExisting = false): array
    {
        return $this->captureDirectValue($patientId, $questionId, $value, $expectedVersion, true, $allowExisting);
    }

    /**
     * Captures a validated literal duration without creating a second
     * persistence path. The canonical capture() operation remains authoritative.
     */
    public function captureDirectText(int $patientId, int $questionId, string $value, ?string $expectedVersion, bool $allowExisting = false): array
    {
        return $this->captureDirectValue($patientId, $questionId, $value, $expectedVersion, false, $allowExisting);
    }

    private function captureDirectValue(int $patientId, int $questionId, string $value, ?string $expectedVersion, bool $numericOnly, bool $allowExisting): array
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
        if (trim((string)$question['respuesta']) !== '' && !$this->serverAuthorizesPreviousEdit($state['questions'], $questionId)) {
            return ['resultado' => 409, 'mensaje' => 'Esta respuesta no está autorizada para edición desde la pregunta anterior.', 'code' => 'DIRECT_EDIT_NOT_AUTHORIZED'];
        }
        if ($numericOnly && !preg_match('/^\d+(?:[.,]\d+)?$/u', $value)) return ['resultado' => 422, 'mensaje' => 'Indica un valor numérico completo para guardarlo directamente.', 'code' => 'DIRECT_NUMERIC_VALUE_REQUIRED'];
        if (!$numericOnly && !$this->isDirectLiteralValue($value)) return ['resultado' => 422, 'mensaje' => 'Indica una duración completa para guardarla directamente.', 'code' => 'DIRECT_LITERAL_VALUE_REQUIRED'];

        $result = $this->capture($patientId, [
            'kind' => 'm03_proposal',
            'question_id' => $questionId,
            'theme_id' => (int)$question['idTema'],
            'structured_value' => $value,
        ], $expectedVersion);
        if ((int)($result['resultado'] ?? 0) !== 200) return $result + ['code' => 'DIRECT_CAPTURE_REJECTED'];

        $after = $this->read($patientId);
        $readback = null;
        foreach ($after['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] === $questionId) {
                $readback = trim((string)$candidate['respuesta']);
                break;
            }
        }
        if ($readback !== $value) return ['resultado' => 500, 'mensaje' => 'No fue posible verificar la respuesta guardada.', 'code' => 'DIRECT_READBACK_FAILED'];

        $nextQuestionId = null;
        foreach ($after['questions'] as $candidate) {
            if ((int)$candidate['idPregunta'] <= $questionId || !$candidate['eligible']) continue;
            if (trim((string)$candidate['respuesta']) === '') {
                $nextQuestionId = (int)$candidate['idPregunta'];
                break;
            }
        }
        return $result + [
            'mode' => 'direct',
            'code' => 'DIRECT_CAPTURE_ACCEPTED',
            'readback' => true,
            'next_question_id' => $nextQuestionId,
            'version' => $after['version'],
        ];
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
     * The client may request an edit, but the server decides which existing
     * answer is the only editable predecessor of the first pending question.
     * This keeps the UI flag out of the authorization decision.
     */
    private function serverAuthorizesPreviousEdit(array $questions, int $questionId): bool
    {
        $eligible = array_values(array_filter($questions, static fn(array $question): bool => !empty($question['eligible'])));
        $firstPending = null;
        foreach ($eligible as $index => $question) {
            if (trim((string)($question['respuesta'] ?? '')) === '') {
                $firstPending = $index;
                break;
            }
        }
        if ($firstPending === null || $firstPending < 1) return false;
        $previous = $eligible[$firstPending - 1] ?? null;
        return is_array($previous)
            && (int)($previous['idPregunta'] ?? 0) === $questionId
            && trim((string)($previous['respuesta'] ?? '')) !== '';
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
