<?php

namespace App\Services;

use App\Config\Database;
use App\Models\ProcedimientosDolorModel;
use PDO;

/** Conversational, confirmation-first adapter for the M08 treatment catalog. */
final class ConversationalM08TreatmentAdapter
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
        $catalog = (new ProcedimientosDolorModel())->p10Tratamientos($patientId);
        $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 8);
        return [
            'key' => 'M08',
            'items' => $catalog['items'] ?? [],
            'comments_available' => (bool)($catalog['comments_available'] ?? false),
            'version' => hash('sha256', json_encode($catalog, JSON_UNESCAPED_UNICODE)),
            'finalized' => (bool)($policy['finalized'] ?? true),
            'write_enabled' => (bool)($policy['editable'] ?? false),
            'write_blocker' => $policy['reason'] ?? null,
            'policy_source' => $policy['source'] ?? 'pac_historia_clinica_finalizar',
        ];
    }

    private function item(int $patientId, int $treatmentId): ?array
    {
        foreach ($this->read($patientId)['items'] as $item) {
            if ((int)($item['id_tratamiento'] ?? 0) === $treatmentId) return $item;
        }
        return null;
    }

    public function interpret(int $patientId, int $treatmentId, string $utterance): array
    {
        $item = $this->item($patientId, $treatmentId);
        if (!$item) throw new \DomainException('El tratamiento no pertenece al catálogo disponible.');
        $text = trim($utterance);
        if ($text === '') throw new \InvalidArgumentException('Indica si utilizaste el tratamiento.');
        $normalized = mb_strtolower($text, 'UTF-8');
        if (preg_match('/^(no|nunca|no lo he (?:usado|utilizado)|no lo utilizo)\.?$/iu', $text)) {
            return $this->proposal($item, ['utilizo' => 'No']);
        }
        $fields = [];
        foreach (preg_split('/[;\n]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $segment) {
            if (!preg_match('/^\s*(utiliz[oó]|uso|resultado|descripci[oó]n|comentarios)\s*:\s*(.*?)\s*$/iu', $segment, $match)) continue;
            $key = mb_strtolower($match[1], 'UTF-8');
            $key = str_starts_with($key, 'utiliz') || $key === 'uso' ? 'utilizo' : $key;
            $key = $key === 'descripción' ? 'descripcion' : $key;
            $fields[$key] = trim($match[2]);
        }
        if (!$fields && preg_match('/^s[ií]\b/iu', $text)) $fields['utilizo'] = 'Si';
        if (isset($fields['utilizo'])) {
            $value = mb_strtolower($fields['utilizo'], 'UTF-8');
            if (in_array($value, ['sí', 'si'], true)) $fields['utilizo'] = 'Si';
            elseif ($value === 'no') $fields['utilizo'] = 'No';
        }
        if (($fields['utilizo'] ?? '') === 'No') return $this->proposal($item, ['utilizo' => 'No']);
        if (($fields['utilizo'] ?? '') !== 'Si') {
            return ['mode' => 'deterministic', 'kind' => 'clarification', 'target' => 'm08_treatment',
                'clarification' => 'Indica “No” si no utilizaste este tratamiento, o “utilizo: Sí” y el resultado documentado.',
                'requires_confirmation' => false, 'can_confirm' => false];
        }
        if (!isset($fields['resultado']) || !in_array($fields['resultado'], ['Funciono', 'No funciono', 'Tuve efectos adversos'], true)) {
            return ['mode' => 'deterministic', 'kind' => 'clarification', 'target' => 'm08_treatment',
                'clarification' => 'Indica el resultado exacto: Funciono, No funciono o Tuve efectos adversos.',
                'requires_confirmation' => false, 'can_confirm' => false];
        }
        if ($fields['resultado'] === 'Tuve efectos adversos' && trim((string)($fields['descripcion'] ?? '')) === '') {
            return ['mode' => 'deterministic', 'kind' => 'clarification', 'target' => 'm08_treatment',
                'clarification' => 'Describe el efecto adverso documentado antes de confirmar.',
                'requires_confirmation' => false, 'can_confirm' => false];
        }
        return $this->proposal($item, $fields);
    }

    private function proposal(array $item, array $fields): array
    {
        return [
            'mode' => 'deterministic', 'kind' => 'm08_treatment_proposal', 'target' => 'm08_treatment',
            'treatment_id' => (int)$item['id_tratamiento'], 'treatment' => (string)$item['tratamiento'],
            'fields' => $fields, 'interpretation' => 'Propuesta M08 preparada con datos expresados por el paciente. No se guardó ningún dato.',
            'requires_confirmation' => true, 'can_confirm' => false,
        ];
    }

    public function capture(int $patientId, array $proposal, ?string $expectedVersion): array
    {
        if ($patientId < 1 || ($proposal['kind'] ?? '') !== 'm08_treatment_proposal' || ($proposal['target'] ?? '') !== 'm08_treatment' || !is_array($proposal['fields'] ?? null)) {
            return ['resultado' => 422, 'mensaje' => 'La propuesta M08 no es válida.'];
        }
        $treatmentId = (int)($proposal['treatment_id'] ?? 0);
        $fields = $proposal['fields'];
        $allowed = ['utilizo', 'resultado', 'descripcion', 'comentarios'];
        foreach ($fields as $key => $value) if (!in_array($key, $allowed, true) || !is_scalar($value)) return ['resultado' => 422, 'mensaje' => 'Campo M08 no válido.'];
        if (($fields['utilizo'] ?? '') === 'No') $fields = ['utilizo' => 'No'];
        if (($fields['utilizo'] ?? '') !== 'No' && ($fields['utilizo'] ?? '') !== 'Si') return ['resultado' => 422, 'mensaje' => 'Indica si utilizaste el tratamiento.'];
        if ($fields['utilizo'] === 'Si' && !in_array((string)($fields['resultado'] ?? ''), ['Funciono', 'No funciono', 'Tuve efectos adversos'], true)) return ['resultado' => 422, 'mensaje' => 'El resultado M08 no es válido.'];
        if (($fields['resultado'] ?? '') === 'Tuve efectos adversos' && trim((string)($fields['descripcion'] ?? '')) === '') return ['resultado' => 422, 'mensaje' => 'Falta describir el efecto adverso.'];
        $db = $this->connection();
        $lock = $db->prepare('SELECT GET_LOCK(:name, 5)');
        $lock->execute([':name' => 'clinica_m08_treatment_' . $patientId . '_' . $treatmentId]);
        if ((int)$lock->fetchColumn() !== 1) return ['resultado' => 409, 'mensaje' => 'El tratamiento está siendo modificado. Inténtalo de nuevo.'];
        try {
            $state = $this->read($patientId);
            if (($state['finalized'] ?? true) || !($state['write_enabled'] ?? false)) return ['resultado' => 403, 'mensaje' => 'El módulo M08 no admite nuevas respuestas.'];
            if ($expectedVersion !== null && $expectedVersion !== '' && !hash_equals((string)$state['version'], $expectedVersion)) return ['resultado' => 409, 'mensaje' => 'M08 cambió mientras se revisaba. Recarga antes de confirmar.'];
            if (!$this->item($patientId, $treatmentId)) return ['resultado' => 404, 'mensaje' => 'Tratamiento no encontrado.'];
            $model = new ProcedimientosDolorModel();
            $order = ['utilizo' => 1, 'resultado' => 2, 'descripcion' => 4, 'comentarios' => 3];
            foreach ($order as $field => $edit) {
                if (!array_key_exists($field, $fields)) continue;
                $value = trim((string)$fields[$field]);
                if ($field === 'comentarios' && !($state['comments_available'] ?? false)) continue;
                $result = $model->p10EditarTratamiento(['idPaciente' => $patientId, 'idTratamiento' => $treatmentId, 'edicion' => $edit, 'detalle' => $value]);
                if ((int)($result['resultado'] ?? 500) !== 200) return ['resultado' => 500, 'mensaje' => 'No fue posible guardar la propuesta M08 completa.'];
            }
            $after = $this->read($patientId);
            return ['resultado' => 200, 'mensaje' => 'Respuesta M08 confirmada.', 'item' => $this->item($patientId, $treatmentId), 'version' => $after['version']];
        } finally {
            $db->query('SELECT RELEASE_LOCK(' . $db->quote('clinica_m08_treatment_' . $patientId . '_' . $treatmentId) . ')');
        }
    }
}
