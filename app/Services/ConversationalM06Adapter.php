<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** Conversational M06 adapter over the existing current-medication record contract. */
final class ConversationalM06Adapter
{
    private const FIELDS = ['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'];
    private const OMITTABLE_FIELDS = ['descripcion', 'tiempo_uso', 'dosis', 'medico'];
    private const OMISSION_STATES = ['UNKNOWN', 'SKIPPED'];

    public function __construct(private ?PDO $db = null) {}

    private function connection(): PDO
    {
        return $this->db ?: Database::getInstance();
    }

    private function fieldStates(array $record, array $omissions): array
    {
        $states = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string)($record[$field] ?? ''));
            $states[$field] = $value !== ''
                ? 'ANSWERED'
                : ($omissions[$field] ?? 'PENDING');
        }
        return $states;
    }

    private function pendingFields(array $fieldStates): array
    {
        return array_values(array_filter(
            array_keys($fieldStates),
            static fn(string $field): bool => ($fieldStates[$field] ?? 'PENDING') === 'PENDING'
        ));
    }

    private function recordFor(array $records, int $recordId): ?array
    {
        foreach ($records as $record) {
            if ((int)($record['id'] ?? 0) === $recordId) return $record;
        }
        return null;
    }

    private function resultFromState(array $state, int $recordId, string $message): array
    {
        $record = $this->recordFor($state['records'] ?? [], $recordId);
        if (!is_array($record)) throw new \RuntimeException('M06_READBACK_FAILED');
        $pending = $state['pending_record'] ?? null;
        return [
            'resultado' => 200,
            'mensaje' => $message,
            'record' => $record,
            'version' => $state['version'],
            'readback' => true,
            'omissions' => $record['omissions'] ?? [],
            'pending_record' => $pending,
            'missing' => $record['pending_fields'] ?? [],
            'next_field' => is_array($pending) ? (($pending['pending_fields'][0] ?? null)) : null,
            'next_action' => is_array($pending) ? 'COMPLETE_MEDICATION' : 'ADD_ANOTHER_MEDICATION',
        ];
    }

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');

        $db = $this->connection();
        $statement = $db->prepare(
            'SELECT id, medicamento, descripcion, tiempo_uso, dosis, medico
             FROM pac_medicacion_actual_modulo_6
             WHERE id_paciente = :id
             ORDER BY id'
        );
        $statement->execute([':id' => $patientId]);
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);
        $model = new \App\Models\MedicacionActualModel($db);
        $omissionRows = $model->readM06Omissions($patientId);
        $omissionsByRecord = [];
        foreach ($omissionRows as $omission) {
            $recordId = (int)($omission['id_medicacion'] ?? 0);
            $field = (string)($omission['campo'] ?? '');
            $state = (string)($omission['estado'] ?? '');
            if ($recordId > 0 && in_array($field, self::OMITTABLE_FIELDS, true) && in_array($state, self::OMISSION_STATES, true)) {
                $omissionsByRecord[$recordId][$field] = $state;
            }
        }
        $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 6);
        $pending = null;

        foreach ($records as &$record) {
            $recordId = (int)($record['id'] ?? 0);
            $record['omissions'] = $omissionsByRecord[$recordId] ?? [];
            $record['field_states'] = $this->fieldStates($record, $record['omissions']);
            $record['pending_fields'] = $this->pendingFields($record['field_states']);
            if ($pending === null && $record['pending_fields'] !== []) $pending = $record;
        }
        unset($record);

        $versionPayload = ['records' => $records, 'omissions' => $omissionsByRecord];

        return [
            'key' => 'M06',
            'title' => 'Medicación actual',
            'records' => $records,
            'pending_record' => $pending,
            'version' => hash('sha256', json_encode($versionPayload, JSON_UNESCAPED_UNICODE)),
            'finalized' => ($policy['finalized'] ?? false) === true,
            'write_enabled' => ($policy['editable'] ?? false) === true,
            'write_blocker' => $policy['reason'] ?? null,
            'policy_source' => $policy['source'] ?? 'pac_historia_clinica_finalizar',
        ];
    }

    /**
     * Interpret one utterance. $activeField is supplied by the current
     * conversational prompt so a short answer such as "Paracetamol" is not
     * discarded merely because it has no sentence-level marker.
     */
    public function interpret(string $utterance, ?string $activeField = null): array
    {
        $text = trim($utterance);
        if ($text === '') throw new \InvalidArgumentException('Indica los datos de la medicación.');

        $normalized = mb_strtolower($text, 'UTF-8');
        $fields = array_fill_keys(self::FIELDS, null);
        $patterns = [
            'medicamento' => '/(?:medicamento|tomo|tomando)\s+([^,.;]+?)(?=\s+(?:para|por|durante|a dosis|dosis|me lo recet[oó]|m[eé]dico|doctor)\b|[,.;]|$)/iu',
            'descripcion' => '/(?:para|por)\s+(?!la\s+(?:noche|mañana|tarde)\b)([^,.;]+?)(?=\s+(?:durante|a dosis|dosis|me lo recet[oó]|m[eé]dico|doctor)\b|[,.;]|$)/iu',
            'tiempo_uso' => '/(?:durante|desde hace)\s+([^,.;]+?)(?=\s+(?:a dosis|dosis|me lo recet[oó]|m[eé]dico|doctor)\b|[,.;]|$)/iu',
            'dosis' => '/dosis\s+([^,.;]+?)(?=\s+(?:me lo recet[oó]|m[eé]dico|doctor)\b|[,.;]|$)/iu',
            'medico' => '/(?:m[eé]dico|doctor|recet[oó])\s+(?:por|de|:)?\s*([^,.;]+)$/iu',
        ];

        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $text, $matches)) $fields[$field] = trim($matches[1]);
        }
        if (!$fields['dosis'] && preg_match('/\b(\d+(?:[.,]\d+)?\s*(?:mg|g|mcg|ml|ui))\b/iu', $text, $matches)) {
            $fields['dosis'] = trim($matches[1]);
        }
        if (!$fields['medicamento'] && preg_match('/^\s*([[:alpha:]][^,.;]*?)(?=\s+de\s+\d+(?:[.,]\d+)?\s*(?:mg|g|mcg|ml|ui)\b|\s*,|\s+me\s+lo\s+recet[oó]\b)/iu', $text, $matches)) {
            $fields['medicamento'] = trim($matches[1]);
        }
        if (!$fields['medicamento']) {
            $parts = preg_split('/\s+para\s+/u', $text, 2);
            if (count($parts) === 2) $fields['medicamento'] = trim(preg_replace('/^(tomo|tomando)\s+/iu', '', $parts[0]));
        }
        if (is_string($fields['medicamento']) && trim($fields['medicamento']) !== '') {
            $fields['medicamento'] = trim((string)preg_replace('/^(?:un|una)\s+(?:medicamento|medicina)\s+(?:llamado|llamada)\s+/iu', '', $fields['medicamento']));
            if (preg_match('/^(?:un|una)\s+(?:medicamento|medicina|pastilla|tableta|fármaco|farmaco|algo)$/iu', $fields['medicamento'])) {
                $fields['medicamento'] = null;
            }
        }

        $unknownFields = [];
        $unknownPatterns = [
            'medicamento' => '/\b(?:no\s+(?:s[eé]|recuerdo)|desconozco)\b[^,.!?]{0,50}\b(?:medicamento|nombre|c[oó]mo\s+se\s+llama)\b/u',
            'descripcion' => '/\b(?:no\s+(?:s[eé]|recuerdo)|desconozco)\b[^,.!?]{0,30}\b(?:motivo|para\s+qu[eé])\b/u',
            'tiempo_uso' => '/\b(?:no\s+(?:s[eé]|recuerdo)|desconozco)\b[^,.!?]{0,30}\b(?:tiempo|duraci[oó]n|cu[aá]nto)\b/u',
            'dosis' => '/\b(?:no\s+(?:s[eé]|recuerdo)|desconozco)\b[^,.!?]{0,30}\bdosis\b/u',
            'medico' => '/\b(?:no\s+(?:s[eé]|recuerdo)|desconozco)\b[^,.!?]{0,30}\b(?:m[eé]dico|qui[eé]n)\b/u',
        ];
        foreach ($unknownPatterns as $field => $pattern) if (preg_match($pattern, $normalized)) $unknownFields[] = $field;

        $activeField = in_array($activeField, self::FIELDS, true) ? $activeField : null;
        if ($activeField !== null && !$fields[$activeField] && !in_array($activeField, $unknownFields, true)) {
            $value = $text;
            if ($activeField === 'medicamento') {
                $value = preg_replace('/^(?:tomo|tomando|medicamento)\s*:?\s*/iu', '', $value);
                $value = preg_replace('/\s+(?:de\s+)?\d+(?:[.,]\d+)?\s*(?:mg|g|mcg|ml|ui)\b.*$/iu', '', $value);
            } elseif ($activeField === 'descripcion') {
                $value = preg_replace('/^(?:para|por)\s+/iu', '', $value);
            } elseif ($activeField === 'tiempo_uso') {
                $value = preg_replace('/^(?:durante|desde hace)\s+/iu', '', $value);
            } elseif ($activeField === 'medico') {
                $value = preg_replace('/^(?:me lo\s+)?(?:recet[oó]|indic[oó])\s+(?:el\s+)?/iu', '', $value);
            }
            $fields[$activeField] = trim((string)$value);
        }

        $missing = array_values(array_filter(self::FIELDS, static fn(string $field): bool => trim((string)($fields[$field] ?? '')) === ''));
        $labels = [
            'medicamento' => 'el nombre del medicamento',
            'descripcion' => 'el motivo de uso',
            'tiempo_uso' => 'el tiempo de tratamiento',
            'dosis' => 'la dosis',
            'medico' => 'el médico que lo recetó',
        ];

        if (trim((string)($fields['medicamento'] ?? '')) === '') {
            $unknownText = array_map(static fn(string $field): string => $labels[$field], $unknownFields);
            $clarification = $unknownText !== []
                ? 'No necesitas inventar ' . implode(' ni ', $unknownText) . '. Indica que no lo recuerdas o proporciona el dato si lo conoces.'
                : 'Indica al menos el nombre del medicamento para registrarlo. Los demás datos pueden completarse después.';
            return [
                'mode' => 'deterministic', 'kind' => 'clarification', 'target' => 'm06',
                'fields' => array_map(static fn($value): string => is_scalar($value) ? trim((string)$value) : '', $fields),
                'missing' => ['medicamento'], 'unknown_fields' => $unknownFields,
                'clarification' => $clarification, 'requires_confirmation' => false,
                'can_confirm' => false, 'direct_save' => false,
            ];
        }

        return [
            'mode' => 'deterministic', 'kind' => 'm06_proposal', 'target' => 'm06',
            'fields' => array_map(static fn($value): string => is_scalar($value) ? trim((string)$value) : '', $fields),
            'structured_value' => $fields, 'missing' => $missing, 'unknown_fields' => $unknownFields,
            'interpretation' => $missing === [] ? 'Respuesta de medicación validada con los cinco campos del contrato.' : 'Nombre de medicamento reconocido; los demás datos pueden completarse después.',
            'requires_confirmation' => false, 'can_confirm' => true, 'direct_save' => true,
        ];
    }

    public function capture(int $patientId, array $proposal, ?string $expectedVersion): array
    {
        if ($patientId < 1 || ($proposal['kind'] ?? '') !== 'm06_proposal' || ($proposal['target'] ?? '') !== 'm06' || !is_array($proposal['fields'] ?? null)) return ['resultado' => 422, 'mensaje' => 'La propuesta M06 no es válida.'];

        $fields = [];
        foreach (self::FIELDS as $name) {
            $value = trim((string)($proposal['fields'][$name] ?? ''));
            if ($value !== '') $fields[$name] = $value;
        }
        if (trim((string)($fields['medicamento'] ?? '')) === '') return ['resultado' => 422, 'mensaje' => 'Indica al menos el nombre del medicamento.'];

        $db = $this->connection();
        $lockName = 'clinica_m06_capture_' . $patientId;
        $lockAcquired = false;
        $failureStage = 'lock';
        try {
            $lock = $db->prepare('SELECT GET_LOCK(:name,5)');
            $lock->execute([':name' => $lockName]);
            $lockAcquired = (int)$lock->fetchColumn() === 1;
            if (!$lockAcquired) return ['resultado' => 409, 'mensaje' => 'No fue posible confirmar M06; inténtalo de nuevo.'];

            $failureStage = 'state_read';
            $state = $this->read($patientId);
            if (!$state['write_enabled']) return ['resultado' => 409, 'mensaje' => 'M06 está finalizado o su estado editable no está disponible.'];
            if ($expectedVersion !== null && !hash_equals((string)$state['version'], $expectedVersion)) return ['resultado' => 409, 'mensaje' => 'M06 cambió mientras se revisaba. Recarga antes de confirmar.'];

            $targetId = filter_var($proposal['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $createNew = ($proposal['create_new'] ?? false) === true;
            $target = null;
            if ($targetId !== false && $targetId !== null) {
                foreach ($state['records'] as $record) if ((int)$record['id'] === (int)$targetId) $target = $record;
                if (!is_array($target)) return ['resultado' => 403, 'mensaje' => 'La medicación indicada no pertenece al paciente autorizado.'];
            } elseif (!$createNew && is_array($state['pending_record'] ?? null)) {
                $target = $state['pending_record'];
            }

            if (!is_array($target)) {
                foreach ($state['records'] as $existing) {
                    $same = trim((string)($existing['medicamento'] ?? '')) !== ''
                        && mb_strtolower(trim((string)$existing['medicamento']), 'UTF-8') === mb_strtolower($fields['medicamento'], 'UTF-8');
                    foreach ($fields as $field => $value) $same = $same && mb_strtolower(trim((string)($existing[$field] ?? '')), 'UTF-8') === mb_strtolower($value, 'UTF-8');
                    if ($same) return ['resultado' => 409, 'mensaje' => 'Esta medicación ya está registrada; no se creó un duplicado.', 'record' => $existing, 'version' => $state['version'], 'code' => 'M06_DUPLICATE_EXISTING'];
                }
            }

            $failureStage = 'persist';
            $db->beginTransaction();
            $model = new \App\Models\MedicacionActualModel($db);
            if (is_array($target) && (int)($target['id'] ?? 0) > 0) {
                $id = (int)$target['id'];
                $edited = $model->editarMedicamentoPaciente(['idPaciente' => $patientId, 'idMedicamento' => $id, 'fields' => $fields]);
                if ((int)($edited['resultado'] ?? 0) !== 200) throw new \RuntimeException('M06 update failed');
                $model->resolveM06Omissions($patientId, $id, array_keys($fields));
            } else {
                $created = $model->agregarMedicacionPaciente(['idPaciente' => $patientId, 'fields' => $fields]);
                if ((int)($created['resultado'] ?? 0) !== 200) throw new \RuntimeException('M06 insert failed');
                $id = (int)($created['id'] ?? 0);
            }
            if ($id < 1) throw new \RuntimeException('M06_RECORD_ID_MISSING');
            $failureStage = 'commit';
            $db->commit();

            $failureStage = 'readback';
            $after = $this->read($patientId);
            $record = null;
            foreach ($after['records'] as $row) if ((int)$row['id'] === $id) $record = $row;
            if (!is_array($record)) throw new \RuntimeException('M06_READBACK_FAILED');
            foreach ($fields as $field => $value) if (trim((string)($record[$field] ?? '')) !== $value) throw new \RuntimeException('M06_READBACK_MISMATCH');
            return $this->resultFromState(
                $after,
                $id,
                is_array($target) ? 'Datos de medicación guardados y confirmados.' : 'Medicamento guardado y confirmado.'
            );
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[clinica] M06 capture failed; stage=' . $failureStage . '; type=' . get_class($exception));
            return ['resultado' => 422, 'mensaje' => 'No fue posible guardar M06.'];
        } finally {
            if ($lockAcquired) {
                try { $release = $db->prepare('SELECT RELEASE_LOCK(:name)'); $release->execute([':name' => $lockName]); } catch (\Throwable) { /* preserve the primary result */ }
            }
        }
    }

    /** Persist an explicit UNKNOWN/SKIPPED state for the active medication field. */
    public function captureOmission(int $patientId, int $recordId, string $field, string $state, ?string $expectedVersion): array
    {
        if ($patientId < 1 || $recordId < 1 || !in_array($field, self::OMITTABLE_FIELDS, true) || !in_array($state, self::OMISSION_STATES, true)) {
            return ['resultado' => 422, 'mensaje' => 'La omisión M06 no es válida.'];
        }

        $db = $this->connection();
        $lockName = 'clinica_m06_capture_' . $patientId;
        $lockAcquired = false;
        $failureStage = 'lock';
        try {
            $lock = $db->prepare('SELECT GET_LOCK(:name,5)');
            $lock->execute([':name' => $lockName]);
            $lockAcquired = (int)$lock->fetchColumn() === 1;
            if (!$lockAcquired) return ['resultado' => 409, 'mensaje' => 'No fue posible confirmar M06; inténtalo de nuevo.'];

            $failureStage = 'state_read';
            $before = $this->read($patientId);
            if (!$before['write_enabled']) return ['resultado' => 409, 'mensaje' => 'M06 está finalizado o su estado editable no está disponible.'];
            if ($expectedVersion !== null && !hash_equals((string)$before['version'], $expectedVersion)) return ['resultado' => 409, 'mensaje' => 'M06 cambió mientras se revisaba. Recarga antes de confirmar.'];
            $record = $this->recordFor($before['records'], $recordId);
            if (!is_array($record)) return ['resultado' => 403, 'mensaje' => 'La medicación indicada no pertenece al paciente autorizado.'];
            if (trim((string)($record[$field] ?? '')) !== '') return ['resultado' => 409, 'mensaje' => 'Este dato ya está respondido; no se registró una omisión.', 'code' => 'M06_OMISSION_ALREADY_RESOLVED'];

            $failureStage = 'persist';
            $db->beginTransaction();
            (new \App\Models\MedicacionActualModel($db))->upsertM06Omission($patientId, $recordId, $field, $state);
            $failureStage = 'commit';
            $db->commit();

            $failureStage = 'readback';
            $after = $this->read($patientId);
            $readbackRecord = $this->recordFor($after['records'], $recordId);
            if (!is_array($readbackRecord) || (($readbackRecord['omissions'][$field] ?? null) !== $state)) throw new \RuntimeException('M06_OMISSION_READBACK_FAILED');
            return $this->resultFromState($after, $recordId, 'Estado M06 guardado y confirmado mediante readback.');
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[clinica] M06 omission failed; stage=' . $failureStage . '; type=' . get_class($exception));
            return ['resultado' => 422, 'mensaje' => 'No fue posible guardar el estado de M06.'];
        } finally {
            if ($lockAcquired) {
                try { $release = $db->prepare('SELECT RELEASE_LOCK(:name)'); $release->execute([':name' => $lockName]); } catch (\Throwable) { /* preserve the primary result */ }
            }
        }
    }
}
