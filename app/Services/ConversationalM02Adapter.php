<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** Conversational M02 adapter. Persistence is allowed only for an open synthetic/draft record. */
final class ConversationalM02Adapter
{
    private const FIXED_DISEASES = [
        'Enfermedades del corazón',
        'Hipertensión arterial sistémica',
        'Enfermedad cerebrovascular',
        'Diabetes Mellitus',
        'Cáncer',
    ];

    public function __construct(private ?PDO $db = null) {}

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $db = $this->db ?: Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id, enfermedad, tipo, detalle, especificar
             FROM pc_antecedentes_familiares
             WHERE id_paciente = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $patientId]);
        $stored = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byDisease = [];
        foreach ($stored as $row) $byDisease[(string)$row['enfermedad']] = $row;

        $records = [];
        foreach (self::FIXED_DISEASES as $disease) {
            $records[] = $this->record($byDisease[$disease] ?? null, $disease);
        }
        foreach ($stored as $row) {
            if (!in_array((string)$row['enfermedad'], self::FIXED_DISEASES, true)) {
                $records[] = $this->record($row, (string)$row['enfermedad']);
            }
        }

        $finalized = false;
        try {
            $final = $db->prepare('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 2 AND id_paciente = :id');
            $final->execute([':id' => $patientId]);
            $finalized = (int)$final->fetchColumn() > 0;
        } catch (\Throwable) {
            $finalized = true;
        }
        return [
            'key' => 'M02',
            'title' => 'Antecedentes heredofamiliares',
            'records' => $records,
            'finalized' => $finalized,
            'write_enabled' => !$finalized,
            'write_blocker' => $finalized ? 'M02_FINALIZED' : null,
        ];
    }

    public function interpret(string $disease, string $utterance, array $context = []): array
    {
        $disease = trim($disease);
        $utterance = trim($utterance);
        if ($disease === '' || $utterance === '') throw new \InvalidArgumentException('Antecedente incompleto.');

        $context = $this->sanitizeContext($disease, $context);
        $normalized = mb_strtolower($utterance, 'UTF-8');
        $uncertain = preg_match('/\bno\s+(recuerdo|sé|se)|no estoy seguro|no tengo claro|no lo sé\b/u', $normalized) === 1;
        $negative = !$uncertain && preg_match('/\b(no|ninguno|nunca|sin antecedentes)\b/u', $normalized) === 1;
        $positive = preg_match('/\b(s[ií]|sí,|tiene|padece|padeci[oó]|diagnosticad[oa])\b/u', $normalized) === 1;
        $relative = $this->relativeFrom($normalized);
        $diabetesType = $this->diabetesTypeFrom($normalized);
        $detail = $context['detail'];

        if ($uncertain) {
            return $this->clarification($disease, $context, 'No convertiré la duda en “No”. Puedes indicar Sí, No o responder después.', 'ambiguous');
        }
        if ($negative && $context['relative'] !== null) {
            return $this->clarification($disease, $context, 'Primero mencionaste a ' . $context['relative'] . ' y ahora indicas No. ¿Deseas corregir el familiar o confirmar que no hay antecedente?', 'contradiction');
        }
        if ($negative) $detail = 'No';
        elseif ($positive) $detail = 'Si';

        if ($relative !== null) {
            if ($context['detail'] === 'No') {
                return $this->clarification($disease, $context, 'Ya se indicó No. Confirma si deseas corregir la respuesta a Sí y conservar el familiar mencionado.', 'contradiction');
            }
            $context['relative'] = $relative;
        }
        if ($diabetesType !== null) $context['diabetes_type'] = $diabetesType;
        if ($detail !== null) $context['detail'] = $detail;

        if ($context['detail'] === 'No') return $this->proposal($disease, $context);
        if ($context['detail'] === null && $context['relative'] !== null) {
            return $this->clarification($disease, $context, '¿' . ucfirst($context['relative']) . ' tiene ' . mb_strtolower($disease, 'UTF-8') . '? Responde Sí o No.', 'relative_first');
        }
        if ($context['detail'] === 'Si' && $context['relative'] === null) {
            return $this->clarification($disease, $context, '¿Qué familiar tiene ' . mb_strtolower($disease, 'UTF-8') . '?', 'positive_first');
        }
        if ($disease === 'Diabetes Mellitus' && $context['detail'] === 'Si' && $context['diabetes_type'] === null) {
            return $this->clarification($disease, $context, '¿Es diabetes Tipo 1, Tipo 2 o gestacional?', 'diabetes_type');
        }
        if ($context['detail'] === null) {
            return $this->clarification($disease, $context, '¿Hay antecedentes de ' . mb_strtolower($disease, 'UTF-8') . '? Indica Sí o No.', 'answer_required');
        }
        return $this->proposal($disease, $context);
    }

    private function sanitizeContext(string $disease, array $context): array
    {
        $detail = in_array(($context['detail'] ?? null), ['Si', 'No'], true) ? $context['detail'] : null;
        $relative = is_string($context['relative'] ?? null) && in_array(mb_strtolower(trim($context['relative']), 'UTF-8'), ['madre', 'padre', 'hermana', 'hermano', 'abuela', 'abuelo', 'hija', 'hijo', 'familia'], true) ? mb_strtolower(trim($context['relative']), 'UTF-8') : null;
        $type = in_array(($context['diabetes_type'] ?? null), ['Tipo 1', 'Tipo 2', 'Gestacional'], true) ? $context['diabetes_type'] : null;
        return ['disease' => $disease, 'detail' => $detail, 'relative' => $relative, 'diabetes_type' => $disease === 'Diabetes Mellitus' ? $type : null];
    }

    private function relativeFrom(string $normalized): ?string
    {
        foreach (['madre', 'padre', 'hermana', 'hermano', 'abuela', 'abuelo', 'hija', 'hijo', 'familia'] as $candidate) if (preg_match('/\b' . preg_quote($candidate, '/') . '\b/u', $normalized)) return $candidate;
        return null;
    }

    private function diabetesTypeFrom(string $normalized): ?string
    {
        if (preg_match('/tipo\s*1|tipo uno/u', $normalized)) return 'Tipo 1';
        if (preg_match('/tipo\s*2|tipo dos/u', $normalized)) return 'Tipo 2';
        if (preg_match('/gestacional/u', $normalized)) return 'Gestacional';
        return null;
    }

    private function clarification(string $disease, array $context, string $message, string $reason): array
    {
        return ['mode' => 'deterministic', 'kind' => 'clarification', 'disease' => $disease, 'context' => $context, 'detail' => $context['detail'], 'relative' => $context['relative'], 'diabetes_type' => $context['diabetes_type'], 'clarification' => $message, 'clarification_reason' => $reason, 'can_confirm' => false, 'write_enabled' => false];
    }

    private function proposal(string $disease, array $context): array
    {
        return ['mode' => 'deterministic', 'kind' => 'm02_read_only_proposal', 'disease' => $disease, 'context' => $context, 'detail' => $context['detail'], 'relative' => $context['relative'], 'diabetes_type' => $context['diabetes_type'], 'can_confirm' => false, 'write_enabled' => false, 'message' => 'Propuesta preparada. La respuesta sólo se guarda después de una confirmación explícita.'];
    }

    public function capture(int $patientId, string $disease, array $proposal, ?string $expectedVersion): array
    {
        if ($patientId < 1 || !in_array($disease, self::FIXED_DISEASES, true)) {
            return ['resultado' => 422, 'mensaje' => 'Enfermedad M02 no autorizada.'];
        }
        if (isset($proposal['disease']) && (string)$proposal['disease'] !== $disease) {
            return ['resultado' => 422, 'mensaje' => 'La propuesta M02 no corresponde a la pregunta activa.'];
        }
        $detail = (string)($proposal['detail'] ?? '');
        $relative = trim((string)($proposal['relative'] ?? ''));
        $diabetesType = trim((string)($proposal['diabetes_type'] ?? ''));
        $fastNo = ($proposal['fast_no'] ?? false) === true;
        if (!in_array($detail, ['Si', 'No'], true)) return ['resultado' => 422, 'mensaje' => 'La respuesta M02 no es válida.'];
        if ($detail === 'No' && ($relative !== '' || $diabetesType !== '')) return ['resultado' => 422, 'mensaje' => 'La propuesta negativa no puede incluir datos dependientes.'];
        if ($detail === 'Si' && $relative === '') return ['resultado' => 422, 'mensaje' => 'Indica el familiar antes de confirmar.'];
        if ($disease === 'Diabetes Mellitus' && $detail === 'Si' && !in_array($diabetesType, ['Tipo 1', 'Tipo 2', 'Gestacional'], true)) {
            return ['resultado' => 422, 'mensaje' => 'Indica el tipo de diabetes antes de confirmar.'];
        }

        $db = $this->db ?: Database::getInstance();
        $lockName = 'clinica_m02_capture_' . $patientId . '_' . hash('sha256', $disease);
        $lockStatement = $db->prepare('SELECT GET_LOCK(:lock_name, 5)');
        $lockStatement->execute([':lock_name' => $lockName]);
        if ((int)$lockStatement->fetchColumn() !== 1) {
            return ['resultado' => 409, 'mensaje' => 'No fue posible confirmar el antecedente; inténtalo de nuevo.'];
        }

        try {
            // Re-read after acquiring the named lock. The version check must not
            // use a snapshot obtained before another confirmation completed.
            $state = $this->read($patientId);
            if (!$state['write_enabled']) {
                return ['resultado' => 409, 'mensaje' => 'M02 está finalizado o su edición no está autorizada.'];
            }
            $record = null;
            foreach ($state['records'] as $candidate) if ($candidate['disease'] === $disease) { $record = $candidate; break; }
            if (!$record || ($expectedVersion !== null && !hash_equals((string)$record['version'], $expectedVersion))) {
                return ['resultado' => 409, 'mensaje' => 'El antecedente cambió mientras se revisaba. Recarga M02 antes de confirmar.'];
            }
            if ($fastNo && (($record['status'] ?? 'pending') === 'documented' || trim((string)($record['relative'] ?? '')) !== '' || trim((string)($record['diabetes_type'] ?? '')) !== '')) {
                return ['resultado' => 409, 'mensaje' => 'La pregunta M02 tiene una respuesta o datos dependientes; no se sobrescribió.'];
            }

            $rowId = (int)($record['id'] ?? 0);
            $model = new \App\Models\AntecedenteFamiliarModel();
            $db->beginTransaction();
            $result = $model->editarEnfermedadPaciente([
                'idPaciente' => $patientId, 'idEnfermedad' => $rowId, 'enfermedad' => $disease,
                'edicion' => 2, 'detalle' => $detail,
            ]);
            if ((int)($result['resultado'] ?? 0) !== 200) throw new \RuntimeException('M02 base response failed');
            if ($rowId === 0) {
                $lookup = $db->prepare('SELECT id FROM pc_antecedentes_familiares WHERE id_paciente = :p AND enfermedad = :e LIMIT 1');
                $lookup->execute([':p' => $patientId, ':e' => $disease]);
                $rowId = (int)$lookup->fetchColumn();
            }
            if ($detail === 'Si' && $disease === 'Diabetes Mellitus') {
                $result = $model->editarEnfermedadPaciente([
                    'idPaciente' => $patientId, 'idEnfermedad' => $rowId, 'enfermedad' => $disease,
                    'edicion' => 3, 'detalle' => $diabetesType,
                ]);
                if ((int)($result['resultado'] ?? 0) !== 200) throw new \RuntimeException('M02 diabetes type failed');
            }
            if ($detail === 'Si') {
                $result = $model->editarEnfermedadPaciente([
                    'idPaciente' => $patientId, 'idEnfermedad' => $rowId, 'enfermedad' => $disease,
                    'edicion' => 4, 'detalle' => $relative,
                ]);
                if ((int)($result['resultado'] ?? 0) !== 200) throw new \RuntimeException('M02 relative failed');
            }
            $db->commit();
            return ['resultado' => 200, 'mensaje' => 'Antecedente M02 guardado.', 'record' => $this->findRecord($patientId, $disease)];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado' => 422, 'mensaje' => 'No fue posible guardar el antecedente M02.'];
        } finally {
            $release = $db->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $release->execute([':lock_name' => $lockName]);
        }
    }

    private function record(?array $row, string $disease): array
    {
        return [
            'id' => $row ? (int)$row['id'] : null,
            'disease' => $disease,
            'detail' => $row ? (string)$row['detalle'] : null,
            'diabetes_type' => $row ? (string)($row['tipo'] ?? '') : null,
            'relative' => $row ? (string)($row['especificar'] ?? '') : null,
            'status' => $row && trim((string)$row['detalle']) !== '' ? 'documented' : 'pending',
            'read_only' => false,
            'version' => hash('sha256', implode('|', [(string)($row['id'] ?? ''), (string)($row['detalle'] ?? ''), (string)($row['tipo'] ?? ''), (string)($row['especificar'] ?? '')])),
        ];
    }

    private function findRecord(int $patientId, string $disease): array
    {
        foreach ($this->read($patientId)['records'] as $record) if ($record['disease'] === $disease) return $record;
        throw new \RuntimeException('M02_RECORD_NOT_FOUND');
    }
}
