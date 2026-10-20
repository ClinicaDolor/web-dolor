<?php

namespace App\Services;

use App\Config\Database;
use App\Models\EvaluacionDolorModel;
use PDO;

/** Conversational adapter for the four-field M09 evaluation only. */
final class ConversationalM09Adapter
{
    public function __construct(private ?PDO $db = null) {}

    private function connection(): PDO
    {
        return $this->db ?: Database::getInstance();
    }

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $db = $this->connection();
        $stmt = $db->prepare('SELECT id, dolor, tiempo_dolor, descripcion, incremento FROM pc_paciente_evaluacion_dolor WHERE id_paciente = :patient ORDER BY id ASC LIMIT 1');
        $stmt->execute([':patient' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 9);
        return [
            'key' => 'M09',
            'evaluation' => $row,
            'version' => hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE)),
            'finalized' => (bool)($policy['finalized'] ?? true),
            'write_enabled' => (bool)($policy['editable'] ?? false),
            'write_blocker' => $policy['reason'] ?? null,
            'policy_source' => $policy['source'] ?? 'pac_historia_clinica_finalizar',
            'body_map' => ['mode' => 'existing_visual_component', 'text_mapping' => false],
        ];
    }

    public function interpret(string $utterance): array
    {
        $text = trim($utterance);
        if ($text === '') throw new \InvalidArgumentException('Indica los datos de la evaluación del dolor.');

        $fields = [];
        $segments = preg_split('/[;\n]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($segments as $segment) {
            if (!preg_match('/^\s*(dolor|tiempo|tiempo_dolor|duraci[oó]n|descripci[oó]n|incremento)\s*:\s*(.+?)\s*$/iu', $segment, $match)) continue;
            $key = mb_strtolower($match[1], 'UTF-8');
            $value = trim($match[2]);
            if (in_array($key, ['tiempo', 'duración'], true)) $key = 'tiempo_dolor';
            if ($key === 'descripción') $key = 'descripcion';
            if ($key === 'incremento') $key = 'incremento';
            if ($key === 'dolor') {
                $normalized = mb_strtolower($value, 'UTF-8');
                if (in_array($normalized, ['sí', 'si'], true)) $value = 'Si';
                elseif ($normalized === 'no') $value = 'No';
                else continue;
            }
            $fields[$key] = $value;
        }

        if (isset($fields['dolor']) && $fields['dolor'] === 'No') {
            return $this->proposal(['dolor' => 'No']);
        }
        $required = ['dolor', 'tiempo_dolor', 'descripcion', 'incremento'];
        $missing = array_values(array_diff($required, array_keys($fields)));
        if ($missing !== []) {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification', 'target' => 'm09',
                'missing' => $missing,
                'clarification' => 'Indica con etiquetas: dolor (Sí/No), tiempo, descripción e incremento; sepáralos con punto y coma.',
                'requires_confirmation' => false, 'can_confirm' => false,
            ];
        }
        return $this->proposal($fields);
    }

    private function proposal(array $fields): array
    {
        return [
            'mode' => 'deterministic', 'kind' => 'm09_proposal', 'target' => 'm09',
            'fields' => $fields,
            'interpretation' => 'Respuesta M09 validada con los cuatro campos de evaluación del dolor. El mapa corporal permanece separado.',
            'requires_confirmation' => false, 'can_confirm' => false, 'direct_save' => true,
        ];
    }

    public function capture(int $patientId, array $proposal, ?string $expectedVersion): array
    {
        if ($patientId < 1 || ($proposal['kind'] ?? '') !== 'm09_proposal' || ($proposal['target'] ?? '') !== 'm09' || !is_array($proposal['fields'] ?? null)) {
            return ['resultado' => 422, 'mensaje' => 'La propuesta M09 no es válida.'];
        }
        $fields = $proposal['fields'];
        $allowed = ['dolor', 'tiempo_dolor', 'descripcion', 'incremento'];
        $normalized = [];
        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowed, true) || !is_scalar($value)) return ['resultado' => 422, 'mensaje' => 'Campo M09 no válido.'];
            $value = trim((string)$value);
            if ($field === 'dolor') {
                if ($value === 'Sí') $value = 'Si';
                if (!in_array($value, ['Si', 'No'], true)) return ['resultado' => 422, 'mensaje' => 'La respuesta de dolor no es válida.'];
            } elseif (mb_strlen($value, 'UTF-8') > 4000) {
                return ['resultado' => 422, 'mensaje' => 'La respuesta M09 es demasiado larga.'];
            }
            $normalized[$field] = $value;
        }
        if (!isset($normalized['dolor'])) return ['resultado' => 422, 'mensaje' => 'Falta el campo dolor.'];
        if ($normalized['dolor'] === 'Si' && array_diff(['tiempo_dolor', 'descripcion', 'incremento'], array_keys($normalized))) return ['resultado' => 422, 'mensaje' => 'Faltan campos dependientes del dolor.'];

        $db = $this->connection();
        $lock = $db->prepare('SELECT GET_LOCK(:name, 5)');
        $lock->execute([':name' => 'clinica_m09_capture_' . $patientId]);
        if ((int)$lock->fetchColumn() !== 1) return ['resultado' => 409, 'mensaje' => 'El expediente está siendo modificado. Inténtalo de nuevo.'];
        try {
            $state = $this->read($patientId);
            if (($state['finalized'] ?? true) || !($state['write_enabled'] ?? false)) return ['resultado' => 403, 'mensaje' => 'El módulo M09 no admite nuevas respuestas.'];
            if ($expectedVersion !== null && $expectedVersion !== '' && !hash_equals((string)$state['version'], $expectedVersion)) return ['resultado' => 409, 'mensaje' => 'La evaluación cambió mientras se revisaba. Recarga antes de continuar.'];
            $serviceChanges = [];
            foreach ([1 => 'dolor', 2 => 'tiempo_dolor', 3 => 'descripcion', 4 => 'incremento'] as $fieldId => $fieldName) {
                if (array_key_exists($fieldName, $normalized)) $serviceChanges[$fieldId] = $normalized[$fieldName];
            }
            $result = (new EvaluacionDolorModel($db))->p11ActualizarEvaluacion($patientId, $serviceChanges);
            if ((int)($result['resultado'] ?? 422) !== 200) return $result;
            $after = $this->read($patientId);
            return ['resultado' => 200, 'mensaje' => 'Evaluación M09 guardada.', 'evaluation' => $after['evaluation'], 'version' => $after['version']];
        } finally {
            $db->query("SELECT RELEASE_LOCK('clinica_m09_capture_" . (int)$patientId . "')");
        }
    }
}
