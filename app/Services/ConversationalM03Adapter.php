<?php

namespace App\Services;

use App\Config\Database;
use App\Models\AntecedentesNoPatologicosModel;
use PDO;

/** M03 adapter for the existing seven-theme catalog and response table. */
final class ConversationalM03Adapter
{
    private const PARENT_BY_THEME = [2 => 6, 3 => 12, 4 => 16, 5 => 18, 6 => 21];

    public function __construct(private ?PDO $db = null) {}
    private function connection(): PDO { return $this->db ?: Database::getInstance(); }

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $db = $this->connection();
        $stmt = $db->prepare('SELECT p.id AS idPregunta,p.id_tema AS idTema,t.tema,p.pregunta,p.tipo,r.id AS idRespuesta,COALESCE(r.respuesta,\'\') AS respuesta FROM pac_preguntas_modulo_3 p INNER JOIN pac_temas_modulo_3 t ON t.id=p.id_tema LEFT JOIN pac_respuestas_paciente_modulo_3 r ON r.id_pregunta=p.id AND r.id_paciente=:patient ORDER BY p.id ASC');
        $stmt->execute([':patient' => $patientId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        return ['key' => 'M03', 'questions' => $questions, 'version' => hash('sha256', json_encode($questions, JSON_UNESCAPED_UNICODE)), 'write_enabled' => (bool)($policy['editable'] ?? false), 'finalized' => (bool)($policy['finalized'] ?? true), 'write_blocker' => $policy['reason'] ?? null];
    }

    public function interpret(int $patientId, int $questionId, string $utterance): array
    {
        $text = trim($utterance);
        if ($text === '') throw new \InvalidArgumentException('La respuesta no puede estar vacía.');
        $state = $this->read($patientId); $question = null;
        foreach ($state['questions'] as $candidate) if ((int)$candidate['idPregunta'] === $questionId) { $question = $candidate; break; }
        if (!$question) throw new \DomainException('La pregunta no pertenece al catálogo M03.');
        if (!$question['eligible'] && trim((string)$question['respuesta']) === '') return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>'Primero responde la pregunta principal de este tema.','needs_clarification'=>true,'can_confirm'=>false];
        $normalized = mb_strtolower($text, 'UTF-8');
        if ((string)$question['tipo'] === 'select') {
            $yes = preg_match('/\b(s[ií]|sí|actualmente|consumo|practico|tengo)\b/u', $normalized) === 1;
            $no = preg_match('/\b(no|nunca)\b/u', $normalized) === 1;
            if ($yes === $no) return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>'Responde claramente sí o no.','needs_clarification'=>true,'can_confirm'=>false];
            $value = $yes ? 'Si' : 'No';
        } else {
            $value = $text;
        }
        return ['mode'=>'deterministic','kind'=>'m03_proposal','target'=>'m03','question_id'=>$questionId,'theme_id'=>(int)$question['idTema'],'question'=>$question['pregunta'],'structured_value'=>$value,'interpretation'=>$value,'requires_confirmation'=>true,'can_confirm'=>false];
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
        if ($value === '') return ['resultado'=>422,'mensaje'=>'La respuesta M03 no puede estar vacía.'];
        $model = new AntecedentesNoPatologicosModel();
        if ((string)$question['tipo'] === 'select' && $value === 'No') {
            $conflicts = $this->dependentResponses($patientId, (int)$question['idTema'], (int)$question['idPregunta']);
            if ($conflicts !== []) {
                return ['resultado'=>409,'mensaje'=>'No se puede cambiar esta respuesta porque existen respuestas dependientes. La corrección requiere un procedimiento clínico autorizado.','conflicts'=>$conflicts];
            }
            if ((int)($question['idRespuesta'] ?? 0) < 1) {
                return ['resultado'=>422,'mensaje'=>'La respuesta principal aún no está preparada para guardarse.'];
            }
            $result = $model->editarCuestionarioDato($patientId, (int)$question['idRespuesta'], (int)$question['idTema'], 'No');
        } else {
            $result = $model->capturarRespuestaEntrevista(['idPaciente'=>$patientId,'idPregunta'=>(int)$question['idPregunta'],'idTema'=>(int)$question['idTema'],'detalle'=>$value,'expectedRespuesta'=>(string)$question['respuesta']]);
        }
        return $result + ['version'=>$this->read($patientId)['version']];
    }

    /**
     * Captures a complete numeric input in one explicit save-and-continue
     * action. The server remains authoritative: the question must be open,
     * eligible, editable and of the catalog's input type. No numeric bound is
     * invented here; the existing catalog type is the only clinical rule.
     */
    public function captureDirectNumeric(int $patientId, int $questionId, string $value, ?string $expectedVersion): array
    {
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
        if (trim((string)$question['respuesta']) !== '') return ['resultado' => 409, 'mensaje' => 'La pregunta ya tiene una respuesta. Recarga antes de continuar.', 'code' => 'DIRECT_QUESTION_ALREADY_ANSWERED'];
        if (!preg_match('/^\d+(?:[.,]\d+)?$/u', $value)) return ['resultado' => 422, 'mensaje' => 'Indica un valor numérico completo para guardarlo directamente.', 'code' => 'DIRECT_NUMERIC_VALUE_REQUIRED'];

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
