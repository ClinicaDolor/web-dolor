<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** Deterministic M02 free-text capture through the existing authorized local schema. */
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
        $relativeSets = [];
        $childTableExists = false;
        $relativeStorageChecked = false;
        try {
            $childTableExists = (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pc_antecedentes_familiares_parentescos'")->fetchColumn() === 1;
            $relativeStorageChecked = true;
            if ($childTableExists && $stored !== []) {
                $ids = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $stored)));
                $marks = implode(',', array_fill(0, count($ids), '?'));
                $children = $db->prepare("SELECT id_antecedente,parentesco FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente IN ($marks) ORDER BY parentesco");
                $children->execute($ids);
                foreach ($children->fetchAll(PDO::FETCH_ASSOC) as $child) $relativeSets[(int)$child['id_antecedente']][] = (string)$child['parentesco'];
            }
        } catch (\Throwable) {
            // The ordinary local application may not have the additive table.
            // Historical scalar reads remain available; writes fail closed by
            // the checked/existence flags returned below.
            $relativeStorageChecked = false;
        }
        $byDisease = [];
        $duplicates = [];
        foreach ($stored as $row) {
            $disease = (string)$row['enfermedad'];
            if (isset($byDisease[$disease])) $duplicates[$disease] = true;
            $row['_relatives'] = $relativeSets[(int)$row['id']] ?? [];
            $byDisease[$disease] = $row;
        }

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
        $localWriteAuthorized = $this->isAuthorizedLocalWriteDatabase($db);
        $relativeReadAvailable = $relativeStorageChecked;
        // New answers are stored only in the existing free-text column. The
        // additive relationship table remains a read-compatibility source; it
        // is not a prerequisite for creating a new conversational answer.
        $multiWriteEnabled = false;
        $writeEnabled = !$finalized && $duplicates === [] && $relativeReadAvailable && $localWriteAuthorized;
        return [
            'key' => 'M02',
            'title' => 'Antecedentes heredofamiliares',
            'records' => $records,
            'finalized' => $finalized,
            'write_enabled' => $writeEnabled,
            'multi_relative_write_enabled' => $multiWriteEnabled,
            'relative_storage_available' => $childTableExists && $relativeReadAvailable,
            'relative_storage_absent_confirmed' => $relativeStorageChecked && !$childTableExists,
            'write_blocker' => $finalized ? 'M02_FINALIZED' : ($duplicates !== [] ? 'M02_DUPLICATE_DISEASE_ROWS' : (!$relativeReadAvailable ? 'M02_RELATIVE_STORAGE_UNVERIFIED' : (!$localWriteAuthorized ? 'M02_DATABASE_NOT_AUTHORIZED' : null))),
            'duplicate_diseases' => array_keys($duplicates),
        ];
    }

    public function interpret(string $disease, string $utterance, array $context = []): array
    {
        $disease = trim($disease);
        $utterance = trim($utterance);
        if ($disease === '' || $utterance === '') throw new \InvalidArgumentException('Antecedente incompleto.');

        $context = $this->sanitizeContext($disease, $context);
        $normalized = $this->normalizeUtterance($utterance);
        $uncertain = preg_match('/\b(?:no\s+(?:recuerdo|lo\s+recuerdo|se)|no\s+lo\s+se|desconozco|no\s+estoy\s+segur[oa]|no\s+tengo\s+claro|creo\s+que|tal\s+vez|quiza|posiblemente)\b/u', $normalized) === 1;
        if ($uncertain) {
            $context = ['disease' => $disease, 'detail' => null, 'relative_description' => '', 'diabetes_type' => null];
            return $this->clarification($disease, $context, 'No registraré esa respuesta como Sí ni como No. M02 no guarda actualmente un estado persistente de incertidumbre; no se modificó el antecedente.', 'uncertainty_not_persisted');
        }

        if ($this->mentionsUnrelatedDisease($disease, $normalized)) {
            return $this->clarification($disease, $context, 'La respuesta menciona otra enfermedad. Confirma qué familiar o familiares tienen ' . mb_strtolower($disease, 'UTF-8') . '.', 'different_disease');
        }

        $globalNo = $this->isClearGlobalNegative($normalized);
        $containsNegation = preg_match('/\b(?:no|ningun[oa]?|nunca|sin\s+antecedentes)\b/u', $normalized) === 1;
        if ($containsNegation && !$globalNo) {
            return $this->clarification($disease, $context, 'La frase puede expresar una negación parcial. Confirma si ningún familiar tiene ' . mb_strtolower($disease, 'UTF-8') . '; no se guardó ningún cambio.', 'negative_scope_unclear');
        }

        if ($globalNo) {
            $context['detail'] = 'No';
            $context['relative_description'] = '';
            $context['diabetes_type'] = null;
            return $this->proposal($disease, $context);
        }

        if (preg_match('/^(?:si|claro|afirmativo)[.!\s]*$/u', $normalized) === 1) {
            $context['detail'] = 'Si';
            return $this->clarification($disease, $context, '¿Qué familiar o familiares tienen esa enfermedad?', 'positive_first');
        }

        $diabetesType = $this->diabetesTypeFrom($normalized);
        $isDiabetesTypeOnly = $disease === 'Diabetes Mellitus'
            && $diabetesType !== null
            && preg_match('/^(?:tipo\s*(?:1|2|uno|dos)|gestacional)[.!\s]*$/u', $normalized) === 1;
        if ($isDiabetesTypeOnly) {
            $context['diabetes_type'] = $diabetesType;
            if (trim((string)$context['relative_description']) === '') {
                $context['detail'] = 'Si';
                return $this->clarification($disease, $context, '¿Qué familiar o familiares tienen diabetes?', 'positive_first');
            }
        } else {
            $context['relative_description'] = $utterance;
            $context['detail'] = 'Si';
            if ($disease === 'Diabetes Mellitus' && $diabetesType !== null) $context['diabetes_type'] = $diabetesType;
        }
        return $this->proposal($disease, $context);
    }

    public function replacementConfirmationRequired(array $state, string $disease, array $proposal): bool
    {
        foreach (($state['records'] ?? []) as $record) {
            if (($record['disease'] ?? null) !== $disease) continue;
            return $this->hasExistingAnswer($record)
                && !$this->sameClinicalAnswer($record, $disease, (string)($proposal['detail'] ?? ''), (string)($proposal['relative_description'] ?? ''), (string)($proposal['diabetes_type'] ?? ''));
        }
        return false;
    }

    private function sanitizeContext(string $disease, array $context): array
    {
        $detail = in_array(($context['detail'] ?? null), ['Si', 'No'], true) ? $context['detail'] : null;
        $type = in_array(($context['diabetes_type'] ?? null), ['Tipo 1', 'Tipo 2', 'Gestacional'], true) ? $context['diabetes_type'] : null;
        $description = is_string($context['relative_description'] ?? null) ? trim($context['relative_description']) : '';
        return ['disease' => $disease, 'detail' => $detail, 'relative_description' => $description, 'diabetes_type' => $disease === 'Diabetes Mellitus' ? $type : null];
    }

    private function normalizeUtterance(string $utterance): string
    {
        $normalized = mb_strtolower(trim($utterance), 'UTF-8');
        return strtr($normalized, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u']);
    }

    private function isClearGlobalNegative(string $normalized): bool
    {
        return preg_match('/^(?:no|ningun(?:o|a)?(?:\s+de\s+mis\s+familiares|\s+de\s+mi\s+familia)?(?:\s+familiar(?:es)?)?(?:\s+(?:tiene|tienen|padece|padecen|presenta|presentan|ha\s+tenido|han\s+tenido)\b.*)?|nadie(?:\s+en\s+mi\s+familia)?(?:\s+(?:tiene|padece|presenta|ha\s+tenido)\b.*)?|mi\s+familia\s+no\s+(?:tiene|presenta|padece)\b.*|no\s+(?:hay|existen)\s+antecedentes(?:\s+familiares)?(?:\s+de\b.*)?|no\s+tengo\s+antecedentes(?:\s+familiares)?(?:\s+de\b.*)?|sin\s+antecedentes(?:\s+familiares)?(?:\s+de\b.*)?)[.!?,;\s]*$/u', $normalized) === 1;
    }

    private function mentionsUnrelatedDisease(string $disease, string $normalized): bool
    {
        $aliases = [
            'Enfermedades del corazón' => ['corazon', 'cardiaca', 'cardiaco'],
            'Hipertensión arterial sistémica' => ['hipertension'],
            'Enfermedad cerebrovascular' => ['enfermedad cerebrovascular', 'cerebrovascular'],
            'Diabetes Mellitus' => ['diabetes'],
            'Cáncer' => ['cancer'],
        ];
        $activeAliases = $aliases[$disease] ?? [];
        $mentionsActive = false;
        foreach ($activeAliases as $alias) {
            if (preg_match('/\b' . preg_quote($alias, '/') . '\b/u', $normalized) === 1) {
                $mentionsActive = true;
                break;
            }
        }
        foreach ($aliases as $otherDisease => $otherAliases) {
            if ($otherDisease === $disease) continue;
            foreach ($otherAliases as $alias) {
                if (!$mentionsActive && preg_match('/\b' . preg_quote($alias, '/') . '\b/u', $normalized) === 1) return true;
            }
        }
        return false;
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
        return ['mode' => 'deterministic', 'kind' => 'clarification', 'disease' => $disease, 'context' => $context, 'detail' => $context['detail'], 'relative_description' => $context['relative_description'], 'diabetes_type' => $context['diabetes_type'], 'clarification' => $message, 'clarification_reason' => $reason, 'can_confirm' => false, 'write_enabled' => false];
    }

    private function proposal(string $disease, array $context): array
    {
        return ['mode' => 'deterministic', 'kind' => 'm02_read_only_proposal', 'disease' => $disease, 'context' => $context, 'detail' => $context['detail'], 'relative_description' => $context['relative_description'], 'diabetes_type' => $context['diabetes_type'], 'can_confirm' => false, 'direct_save' => true, 'write_enabled' => false, 'message' => 'Respuesta M02 validada para guardado directo.'];
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
        $descriptionInput = $proposal['relative_description'] ?? ($proposal['relative'] ?? '');
        if (!is_string($descriptionInput)) return ['resultado' => 422, 'mensaje' => 'La descripción familiar no es válida.'];
        $description = trim($descriptionInput);
        $diabetesType = trim((string)($proposal['diabetes_type'] ?? ''));
        $fastNo = ($proposal['fast_no'] ?? false) === true;
        if (!in_array($detail, ['Si', 'No'], true)) return ['resultado' => 422, 'mensaje' => 'La respuesta M02 no es válida.'];
        if ($detail === 'No' && ($description !== '' || $diabetesType !== '')) return ['resultado' => 422, 'mensaje' => 'La propuesta negativa no puede incluir una descripción afirmativa.'];
        if ($detail === 'Si' && $description === '') return ['resultado' => 422, 'mensaje' => 'Describe qué familiar o familiares tienen esa enfermedad.'];
        if ($diabetesType !== '' && ($disease !== 'Diabetes Mellitus' || !in_array($diabetesType, ['Tipo 1', 'Tipo 2', 'Gestacional'], true))) {
            return ['resultado' => 422, 'mensaje' => 'El tipo de diabetes no es válido.'];
        }

        $db = $this->db ?: Database::getInstance();
        $lockName = 'm02:' . $patientId . ':' . substr(hash('sha256', $disease), 0, 32);
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
            if (!$record) {
                return ['resultado' => 409, 'mensaje' => 'El antecedente cambió mientras se revisaba. Recarga M02 antes de confirmar.'];
            }
            // The type is optional. When a patient updates only the family
            // description, retain the existing historical value rather than
            // erasing it. A new explicit type still replaces it.
            $effectiveDiabetesType = $detail === 'Si' && $disease === 'Diabetes Mellitus'
                ? ($diabetesType !== '' ? $diabetesType : (string)($record['diabetes_type'] ?? ''))
                : '';
            $sameAnswer = $this->sameClinicalAnswer($record, $disease, $detail, $description, $effectiveDiabetesType);
            if ($sameAnswer) {
                return ['resultado' => 200, 'mensaje' => 'Antecedente M02 ya estaba guardado.', 'record' => $record, 'readback' => true, 'idempotent_replay' => true];
            }
            if ($expectedVersion !== null && !hash_equals((string)$record['version'], $expectedVersion)) {
                return ['resultado' => 409, 'mensaje' => 'El antecedente cambió mientras se revisaba. Recarga M02 antes de confirmar.'];
            }
            if ($fastNo && $this->hasExistingAnswer($record)) {
                return ['resultado' => 409, 'mensaje' => 'La pregunta M02 ya contiene información. Usa una respuesta escrita para solicitar una sustitución explícita.'];
            }
            $requiresReplacementConfirmation = $this->hasExistingAnswer($record);
            if ($requiresReplacementConfirmation && (($proposal['confirm_replace_existing'] ?? false) !== true || $expectedVersion === null)) {
                return ['resultado' => 409, 'code' => 'M02_REPLACEMENT_CONFIRMATION_REQUIRED', 'mensaje' => 'Este antecedente ya contiene información. Confirma su sustitución antes de guardar.'];
            }
            $hasStructuredHistory = ($record['relatives'] ?? []) !== [] || ($record['invalid_relatives'] ?? []) !== [];
            if ($detail === 'No' && $hasStructuredHistory) {
                return ['resultado' => 409, 'code' => 'M02_STRUCTURED_HISTORY_CONFLICT', 'mensaje' => 'Este antecedente conserva parentescos estructurados anteriores. No se modificó para evitar perder o contradecir esos datos.'];
            }
            $rowId = (int)($record['id'] ?? 0);
            $db->beginTransaction();
            $patientLock = $db->prepare('SELECT id FROM pc_paciente WHERE id=:patient FOR UPDATE');
            $patientLock->execute([':patient'=>$patientId]);
            if (!$patientLock->fetchColumn()) throw new \RuntimeException('M02_PATIENT_NOT_FOUND');
            $lookup = $db->prepare('SELECT id FROM pc_antecedentes_familiares WHERE id_paciente = :p AND enfermedad = :e ORDER BY id FOR UPDATE');
            $lookup->execute([':p' => $patientId, ':e' => $disease]);
            $existingIds = array_map('intval', $lookup->fetchAll(PDO::FETCH_COLUMN));
            if (count($existingIds) > 1) throw new \RuntimeException('M02_DUPLICATE_DISEASE_ROWS');
            if ($rowId > 0 && $existingIds !== [$rowId]) throw new \RuntimeException('M02_PARENT_CHANGED');
            if ($rowId === 0 && $existingIds === []) {
                $insertParent = $db->prepare("INSERT INTO pc_antecedentes_familiares (id_paciente,enfermedad,tipo,detalle,especificar) VALUES (:p,:e,'','', '')");
                $insertParent->execute([':p'=>$patientId,':e'=>$disease]);
                $rowId=(int)$db->lastInsertId();
                $lookup->execute([':p' => $patientId, ':e' => $disease]);
                $existingIds = array_map('intval', $lookup->fetchAll(PDO::FETCH_COLUMN));
                if (count($existingIds) !== 1 || $existingIds[0] !== $rowId) throw new \RuntimeException('M02_PARENT_INSERT_NOT_UNIQUE');
            } elseif ($rowId === 0) {
                $rowId = $existingIds[0];
            }
            $parent = $db->prepare('SELECT id,detalle,tipo,especificar FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=:p AND enfermedad=:e FOR UPDATE');
            $parent->execute([':id'=>$rowId,':p'=>$patientId,':e'=>$disease]);
            $parentRow = $parent->fetch(PDO::FETCH_ASSOC);
            if (!$parentRow) throw new \RuntimeException('M02_PARENT_SCOPE_MISMATCH');
            $structuredBefore = $record['relatives'] ?? [];
            $invalidStructuredBefore = $record['invalid_relatives'] ?? [];
            $update = $db->prepare('UPDATE pc_antecedentes_familiares SET detalle=:detail,tipo=:type,especificar=:description WHERE id=:id AND id_paciente=:p AND enfermedad=:disease');
            $update->execute([
                ':detail'=>$detail,
                ':type'=>$effectiveDiabetesType,
                ':description'=>($detail === 'Si' ? $description : ''),
                ':id'=>$rowId,
                ':p'=>$patientId,
                ':disease'=>$disease,
            ]);
            $verifyParent = $db->prepare('SELECT detalle,tipo,especificar FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=:patient AND enfermedad=:disease');
            $verifyParent->execute([':id'=>$rowId, ':patient'=>$patientId, ':disease'=>$disease]);
            $savedParent = $verifyParent->fetch(PDO::FETCH_ASSOC);
            if (!$savedParent || $savedParent['detalle'] !== $detail
                || (string)$savedParent['especificar'] !== ($detail === 'Si' ? $description : '')
                || (string)$savedParent['tipo'] !== $effectiveDiabetesType) {
                throw new \RuntimeException('M02_TEXT_TX_READBACK_FAILED');
            }
            $db->commit();
            $readbackRecord = $this->findRecord($patientId, $disease);
            if (($readbackRecord['detail'] ?? null) !== $detail
                || ($readbackRecord['legacy_relative_text'] ?? '') !== ($detail === 'Si' ? $description : '')
                || ($readbackRecord['relatives'] ?? []) !== $structuredBefore
                || ($readbackRecord['invalid_relatives'] ?? []) !== $invalidStructuredBefore
                || (($disease === 'Diabetes Mellitus' && $detail === 'Si') && ($readbackRecord['diabetes_type'] ?? '') !== $effectiveDiabetesType)) {
                return ['resultado'=>500,'mensaje'=>'La lectura posterior no confirmó la descripción familiar.','record'=>$readbackRecord,'readback'=>false];
            }
            return ['resultado' => 200, 'mensaje' => 'Antecedente M02 guardado.', 'record' => $readbackRecord, 'readback' => true];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado' => 422, 'mensaje' => 'No fue posible guardar el antecedente M02.'];
        } finally {
            try {
                $release = $db->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $release->execute([':lock_name' => $lockName]);
            } catch (\Throwable) {
                // Connection teardown also releases a MySQL named lock.
            }
        }
    }

    private function sameClinicalAnswer(array $record, string $disease, string $detail, string $description, string $diabetesType): bool
    {
        return empty($record['inconsistent'])
            && ($record['detail'] ?? null) === $detail
            && trim((string)($record['legacy_relative_text'] ?? '')) === ($detail === 'Si' ? $description : '')
            && (($disease !== 'Diabetes Mellitus' || $detail !== 'Si' || $diabetesType === '') || ($record['diabetes_type'] ?? '') === $diabetesType);
    }

    private function hasExistingAnswer(array $record): bool
    {
        return ($record['detail'] ?? null) !== null
            || trim((string)($record['legacy_relative_text'] ?? '')) !== ''
            || ($record['relatives'] ?? []) !== []
            || ($record['invalid_relatives'] ?? []) !== []
            || !empty($record['inconsistent'])
            || trim((string)($record['diabetes_type'] ?? '')) !== '';
    }

    private function record(?array $row, string $disease): array
    {
        $rawDetail = $row ? trim((string)($row['detalle'] ?? '')) : null;
        $detail = $rawDetail === 'Sí' ? 'Si' : $rawDetail;
        if ($detail === '') $detail = null;
        $legacyText = $row ? trim((string)($row['especificar'] ?? '')) : '';
        $rawRelatives = $row ? array_values(array_unique(array_filter($row['_relatives'] ?? [], static fn($v): bool => is_string($v)))) : [];
        $allowedOrder = ['madre', 'padre', 'hermana', 'hermano', 'abuela', 'abuelo', 'hija', 'hijo', 'familia'];
        $invalidRelatives = array_values(array_diff($rawRelatives, $allowedOrder));
        $negativeHasRelatives = $detail === 'No' && ($rawRelatives !== [] || $legacyText !== '');
        $relatives = array_values(array_intersect($rawRelatives, $allowedOrder));
        usort($relatives, static fn(string $a, string $b): int => array_search($a, $allowedOrder, true) <=> array_search($b, $allowedOrder, true));
        $versionPayload = [
            'id' => $row['id'] ?? null,
            'detail' => $detail,
            'type' => $row['tipo'] ?? '',
            'legacy_text' => $row['especificar'] ?? '',
            'relatives' => $relatives,
        ];
        return [
            'id' => $row ? (int)$row['id'] : null,
            'disease' => $disease,
            'detail' => $detail,
            'diabetes_type' => $row ? (string)($row['tipo'] ?? '') : null,
            'relative' => count($relatives) === 1 ? $relatives[0] : ($relatives !== [] ? implode(', ', $relatives) : ($legacyText !== '' ? $legacyText : null)),
            'relative_description' => $legacyText !== '' ? $legacyText : null,
            'relatives' => $relatives,
            'legacy_relative_text' => $legacyText !== '' ? $legacyText : null,
            'inconsistent' => $negativeHasRelatives || $invalidRelatives !== [] || ($rawDetail !== null && $rawDetail !== '' && !in_array($rawDetail, ['Si', 'Sí', 'No'], true)),
            'invalid_relatives' => $invalidRelatives,
            'status' => $row && trim((string)($row['detalle'] ?? '')) !== '' ? 'documented' : 'pending',
            'read_only' => false,
            'version' => hash('sha256', json_encode($versionPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }

    private function isAuthorizedLocalWriteDatabase(PDO $db): bool
    {
        try {
            $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
            $serverPort = (int)$db->query('SELECT @@port')->fetchColumn();
            $configuredHost = strtolower(trim((string)($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '')));
            $configuredPort = (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
            return in_array($database, ['bd_clinica', 'clinica_dolor_demo_20261010_final'], true)
                && in_array($configuredHost, ['localhost', '127.0.0.1', '::1'], true)
                && $configuredPort === 3306
                && $serverPort === 3306;
        } catch (\Throwable) {
            return false;
        }
    }

    private function findRecord(int $patientId, string $disease): array
    {
        foreach ($this->read($patientId)['records'] as $record) if ($record['disease'] === $disease) return $record;
        throw new \RuntimeException('M02_RECORD_NOT_FOUND');
    }
}
