<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** M04 conversational adapter. Reading and interpretation are safe; writing is gated. */
final class ConversationalM04Adapter
{
    public function __construct(private ?PDO $db = null) {}

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $db = $this->db ?: Database::getInstance();
        $rows = $db->prepare(
            'SELECT id, fecha, cirugia, observaciones
             FROM pc_antecedentes_quirurgicos
             WHERE id_paciente = :id
             ORDER BY fecha DESC, id DESC'
        );
        $rows->execute([':id' => $patientId]);
        $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 4);
        $records = $rows->fetchAll(PDO::FETCH_ASSOC);
        $versionRows = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'], 'fecha' => (string)($row['fecha'] ?? ''),
            'cirugia' => (string)($row['cirugia'] ?? ''), 'observaciones' => (string)($row['observaciones'] ?? ''),
        ], $records);

        return [
            'key' => 'M04',
            'title' => 'Antecedentes quirúrgicos',
            'records' => $records,
            'version' => hash('sha256', json_encode($versionRows, JSON_UNESCAPED_UNICODE)),
            'finalized' => (bool)($policy['finalized'] ?? true),
            'write_enabled' => (bool)($policy['editable'] ?? false),
            'write_blocker' => $policy['reason'] ?? null,
        ];
    }

    public function interpret(string $utterance): array
    {
        $utterance = trim($utterance);
        if ($utterance === '') throw new \InvalidArgumentException('Indica una respuesta sobre cirugías.');
        $normalized = mb_strtolower($utterance, 'UTF-8');
        $negative = preg_match('/\b(no|nunca|ninguna|ningún|sin cirug[ií]as?)\b/u', $normalized) === 1;
        $positive = preg_match('/\b(s[ií]|operad[oa]|cirug[ií]a|intervenci[oó]n|me operaron)\b/u', $normalized) === 1;
        if ($negative && !$positive) {
            return [
                'mode' => 'deterministic', 'kind' => 'm04_negative_review',
                'surgery' => null, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'message' => 'No se identificó una cirugía. La ausencia se revisa sin crear un registro quirúrgico.',
            ];
        }
        if (!$positive) {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification',
                'surgery' => null, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'clarification' => '¿Te realizaron alguna cirugía? Responde sí o no y, si fue sí, indica cuál.',
            ];
        }

        $surgery = null;
        if (preg_match('/(?:operaron|cirug[ií]a|intervenci[oó]n)(?:\s+(?:de|por|para))?\s+([^,.!?]+)/u', $normalized, $match)) {
            $surgery = trim($match[1]);
        }
        if ($surgery === null || $surgery === '') {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification',
                'surgery' => null, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'clarification' => '¿Qué cirugía te realizaron?',
            ];
        }
        $date = null;
        if (preg_match('/\b(19|20)\d{2}\b/u', $normalized, $year)) $date = $year[0] . '-01-01';
        $observations = preg_match('/\bhace\s+([^,.!?]+)/u', $normalized, $ago) ? 'Tiempo referido: ' . trim($ago[1]) : null;
        return [
            'mode' => 'deterministic', 'kind' => 'm04_proposal',
            'surgery' => $surgery, 'date' => $date, 'observations' => $observations,
            'requires_confirmation' => true, 'can_confirm' => false,
            'message' => 'Propuesta M04 preparada para revisión. Sólo se guardará después de una confirmación explícita.',
        ];
    }

    public function capture(int $patientId, array $proposal, ?string $expectedVersion): array
    {
        if ($patientId < 1 || ($proposal['kind'] ?? '') !== 'm04_proposal') {
            return ['resultado' => 422, 'mensaje' => 'La propuesta M04 no es válida.'];
        }
        $surgery = trim((string)($proposal['surgery'] ?? ''));
        $date = trim((string)($proposal['date'] ?? ''));
        $observations = array_key_exists('observations', $proposal) && $proposal['observations'] !== null
            ? trim((string)$proposal['observations']) : null;
        if ($surgery === '' || ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))) {
            return ['resultado' => 422, 'mensaje' => 'La cirugía o la fecha M04 no son válidas.'];
        }
        $db = $this->db ?: Database::getInstance();
        $lockName = 'clinica_m04_capture_' . $patientId;
        $lock = $db->prepare('SELECT GET_LOCK(:name, 5)');
        $lock->execute([':name' => $lockName]);
        if ((int)$lock->fetchColumn() !== 1) return ['resultado' => 409, 'mensaje' => 'No fue posible confirmar M04; inténtalo de nuevo.'];
        try {
            $state = $this->read($patientId);
            if (!$state['write_enabled']) return ['resultado' => 409, 'mensaje' => 'M04 está finalizado o su estado editable no está disponible.'];
            if ($expectedVersion !== null && !hash_equals((string)$state['version'], $expectedVersion)) {
                return ['resultado' => 409, 'mensaje' => 'M04 cambió mientras se revisaba. Recarga antes de confirmar.'];
            }
            $model = new \App\Models\AntecedentesQuirurgicos();
            $db->beginTransaction();
            $created = $model->agregarCirugiaPaciente(['idPaciente' => $patientId]);
            if ((int)($created['resultado'] ?? 0) !== 200) throw new \RuntimeException('M04 insert failed');
            $lookup = $db->prepare('SELECT id FROM pc_antecedentes_quirurgicos WHERE id_paciente=:patient ORDER BY id DESC LIMIT 1');
            $lookup->execute([':patient'=>$patientId]);
            $rowId = (int)$lookup->fetchColumn();
            $updates = [['edicion'=>2, 'detalle'=>$surgery]];
            if ($date !== '') $updates[] = ['edicion'=>1, 'detalle'=>$date];
            if ($observations !== null && $observations !== '') $updates[] = ['edicion'=>3, 'detalle'=>$observations];
            foreach ($updates as $update) {
                $result = $model->editarCirugiaPaciente(['idPaciente'=>$patientId, 'idCirugia'=>$rowId, 'edicion'=>$update['edicion'], 'detalle'=>$update['detalle']]);
                if ((int)($result['resultado'] ?? 0) !== 200) throw new \RuntimeException('M04 update failed');
            }
            $db->commit();
            return ['resultado'=>200, 'mensaje'=>'Antecedente quirúrgico M04 guardado.', 'record'=>$this->findRecord($patientId, $rowId)];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado'=>422, 'mensaje'=>'No fue posible guardar el antecedente quirúrgico M04.'];
        } finally {
            $release = $db->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute([':name'=>$lockName]);
        }
    }

    private function findRecord(int $patientId, int $rowId): array
    {
        foreach ($this->read($patientId)['records'] as $record) if ((int)$record['id'] === $rowId) return $record;
        throw new \RuntimeException('M04_RECORD_NOT_FOUND');
    }
}
