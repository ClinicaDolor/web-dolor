<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Shared canonical M07 service.
 *
 * Reads are safe for the conversational and traditional P09 consumers. The
 * activation gate remains required for every canonical write.
 */
final class M07CanonicalService
{
    private const HISTORICAL = 'NOT_DOCUMENTED_HISTORICAL';
    private const CONFIRMED = 'CONFIRMED';
    private const NOT_APPLICABLE = 'NOT_APPLICABLE';
    private const PENDING = 'PENDING_REVIEW';
    private const UNANSWERED = 'UNANSWERED';

    private const CATALOG_COLUMNS = [
        'm07_respuesta_state', 'm07_dosis_state', 'm07_resultados_state',
        'm07_consumo_state', 'm07_descripcion_state', 'm07_eficacia_codigo',
        'm07_eficacia_state', 'm07_tolerabilidad_codigo', 'm07_tolerabilidad_state',
        'm07_efectos_descripcion', 'm07_efectos_descripcion_state', 'm07_version',
        'm07_updated_at', 'm07_updated_by', 'm07_provenance',
    ];

    public function __construct(private PDO $db, ?M07CanonicalActivationGate $gate = null)
    {
        $this->gate = $gate ?? M07CanonicalActivationGate::fromTrustedServerConfiguration();
    }

    private M07CanonicalActivationGate $gate;

    public function active(): bool
    {
        return $this->gate->allows($this->db) && $this->schemaAvailable();
    }

    /** Read-only unified DTO. It never creates initialized legacy rows. */
    public function read(int $patientId, int $clinicId, string $role): array
    {
        if ($this->gate->requiresSyntheticEnvironment() && !$this->gate->allows($this->db)) {
            throw new M07CanonicalException('M07_CANONICAL_DISABLED', 403, 'El entorno sintético M07 no está verificado.');
        }
        $this->assertScope($patientId, $clinicId, $role, false);
        $this->requireSchema();

        $sql = 'SELECT t.id AS theme_id, t.tema AS theme, q.id AS question_id,
                       q.pregunta AS question, r.id AS response_id,
                       r.respuesta, r.dosis, r.resultados, r.consumo, r.descripcion,
                       r.m07_respuesta_state, r.m07_dosis_state,
                       r.m07_resultados_state, r.m07_consumo_state,
                       r.m07_descripcion_state, r.m07_eficacia_codigo,
                       r.m07_eficacia_state, r.m07_tolerabilidad_codigo,
                       r.m07_tolerabilidad_state, r.m07_efectos_descripcion,
                       r.m07_efectos_descripcion_state, r.m07_version,
                       r.m07_provenance
                FROM pac_temas_modulo_7 t
                INNER JOIN pac_preguntas_modulo_7 q ON q.id_tema = t.id
                LEFT JOIN pac_respuestas_paciente_modulo_7 r
                  ON r.id_pregunta = q.id AND r.id_paciente = :patient
               ORDER BY t.id, q.id, r.id';
        $statement = $this->db->prepare($sql);
        $statement->execute([':patient' => $patientId]);

        $grouped = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (int) $row['question_id'];
            $grouped[$key][] = $row;
        }

        $records = [];
        foreach ($grouped as $rows) {
            $first = $rows[0];
            $duplicate = count(array_filter($rows, static fn(array $row): bool => (int)($row['response_id'] ?? 0) > 0)) > 1;
            $records[] = $this->recordFromCatalogRow($first, $patientId, $clinicId, $duplicate);
        }

        $other = $this->readOther($patientId, $clinicId, $role);
        $linkedOther = [];
        foreach ($other as $entry) {
            $questionId = (int)($entry['question_id'] ?? 0);
            if ($questionId > 0 && ($entry['record_status'] ?? '') === 'ACTIVO') $linkedOther[$questionId][] = $entry;
        }
        foreach ($records as $index => $record) {
            $questionId = (int)$record->questionId;
            if (!$this->isOtherCatalogQuestion($record->name)) continue;
            $linked = $linkedOther[$questionId] ?? [];
            if ($linked === []) continue;
            $complete = count(array_filter($linked, static fn(array $entry): bool => ($entry['status'] ?? '') === 'complete')) === count($linked);
            $recordArray = $record->toArray();
            $catalogHasDependentData = trim((string)($recordArray['fields']['dosis'] ?? '')) !== ''
                || trim((string)($recordArray['fields']['resultados'] ?? '')) !== ''
                || trim((string)($recordArray['fields']['consumo'] ?? '')) !== ''
                || trim((string)($recordArray['fields']['descripcion'] ?? '')) !== '';
            if (($recordArray['fields']['respuesta'] ?? '') === 'No' || $catalogHasDependentData) {
                $recordArray['status'] = 'conflict';
                $recordArray['pending_reason'] = ($recordArray['fields']['respuesta'] ?? '') === 'No'
                    ? 'NEGATIVE_WITH_ACTIVE_OTHER_ENTRY'
                    : 'DUPLICATE_OTHER_CLINICAL_DATA';
                $records[$index] = $recordArray;
                continue;
            }
            $recordArray['fields']['respuesta'] = 'Si';
            $recordArray['states']['respuesta'] = self::CONFIRMED;
            $recordArray['status'] = $complete ? 'complete' : 'pending';
            $recordArray['pending_reason'] = $complete ? null : 'OTHER_MEDICATIONS_INCOMPLETE';
            $records[$index] = $recordArray;
        }
        $finalizedStatement = $this->db->prepare(
            'SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente = :patient'
        );
        $finalizedStatement->execute([':patient' => $patientId]);
        $finalized = (int)$finalizedStatement->fetchColumn() > 0;
        $payload = [
            'key' => 'M07',
            'records' => array_map(static fn(M07CanonicalRecord|array $record): array => $record instanceof M07CanonicalRecord ? $record->toArray() : $record, $records),
            'other_medications' => $other,
            'finalized' => $finalized,
            'finalization_enabled' => $this->active() && !$finalized,
        ];
        $payload['version'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $pendingCatalog = count(array_filter($records, static fn(M07CanonicalRecord|array $record): bool => ($record instanceof M07CanonicalRecord ? $record->status : ($record['status'] ?? 'pending')) !== 'complete'));
        $pendingOther = count(array_filter($other, static fn(array $record): bool => ($record['status'] ?? '') === 'pending'));
        $payload['pending_items'] = $pendingCatalog + $pendingOther;
        return $payload;
    }

    /** Alias that makes the shared DTO intent explicit to callers. */
    public function readUnified(int $patientId, int $clinicId, string $role): array
    {
        return $this->read($patientId, $clinicId, $role);
    }

    /** Return a verified retry result before an adapter rejects its stale page version. */
    public function replayCatalogCapture(
        array $scope,
        int $questionId,
        array $patch,
        array $fieldMask,
        string $idempotencyKey,
        string $provenance = 'INTERVIEW',
        bool $confirmClear = false,
        bool $draft = false,
    ): ?array {
        $patientId = (int)($scope['patient_id'] ?? 0);
        $clinicId = (int)($scope['clinic_id'] ?? 0);
        $role = (string)($scope['role'] ?? '');
        $this->assertScope($patientId, $clinicId, $role, true);
        $this->requireWrite();
        $this->assertIdempotencyKey($idempotencyKey);
        $operation = $this->findOperation($patientId, $idempotencyKey);
        if ($operation === null) return null;

        $fieldMask = array_values(array_unique(array_map(static fn($field): string => (string)$field, $fieldMask)));
        $requestHash = hash('sha256', json_encode([
            $patientId,
            $clinicId,
            $questionId,
            $patch,
            $fieldMask,
            (int)$operation['expected_version'],
            $provenance,
            $confirmClear,
            $draft,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (!hash_equals((string)$operation['request_hash'], $requestHash)) {
            throw new M07CanonicalException('M07_IDEMPOTENCY_CONFLICT', 409, 'La clave de idempotencia ya fue utilizada con otra solicitud.');
        }
        return [
            'resultado' => 200,
            'code' => 'M07_IDEMPOTENT_REPLAY',
            'operation_id' => (int)$operation['id'],
            'record' => $this->readOneCatalog($patientId, $clinicId, $role, $questionId),
        ];
    }

    /**
     * Explicit draft capture. This is not called by current UI routes. It is
     * the only path that can persist UNANSWERED, and it requires a closed gate.
     */
    public function saveUnansweredDraft(array $scope, int $questionId, int $expectedVersion, string $idempotencyKey, ?int $actorId = null): array
    {
        return $this->captureCatalog($scope, $questionId, ['respuesta_state' => self::UNANSWERED], ['respuesta_state'], $expectedVersion, $idempotencyKey, 'INTERVIEW', $actorId, false, true);
    }

    /** Persist an explicit, field-masked catalog entry atomically. */
    public function captureCatalog(
        array $scope,
        int $questionId,
        array $patch,
        array $fieldMask,
        int $expectedVersion,
        string $idempotencyKey,
        string $provenance = 'INTERVIEW',
        ?int $actorId = null,
        bool $confirmClear = false,
        bool $draft = false,
    ): array {
        $patientId = (int)($scope['patient_id'] ?? 0);
        $clinicId = (int)($scope['clinic_id'] ?? 0);
        $role = (string)($scope['role'] ?? '');
        $this->assertScope($patientId, $clinicId, $role, true);
        $this->requireWrite();
        $this->assertIdempotencyKey($idempotencyKey);
        $fieldMask = array_values(array_unique(array_map(static fn($field): string => (string)$field, $fieldMask)));
        if ($fieldMask === []) throw new M07CanonicalException('M07_FIELD_MASK_REQUIRED', 422, 'La operación M07 requiere una máscara de campos.');
        $allowed = ['respuesta', 'dosis', 'resultados', 'consumo', 'descripcion', 'respuesta_state', 'dosis_state', 'resultados_state', 'consumo_state', 'descripcion_state', 'eficacia_codigo', 'tolerabilidad_codigo', 'descripcion_efectos'];
        if (array_diff($fieldMask, $allowed) !== []) throw new M07CanonicalException('M07_FIELD_MASK_INVALID', 422, 'La máscara M07 contiene campos no autorizados.');

        $requestHash = hash('sha256', json_encode([$patientId, $clinicId, $questionId, $patch, $fieldMask, $expectedVersion, $provenance, $confirmClear, $draft], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existingOperation = $this->findOperation($patientId, $idempotencyKey);
        if ($existingOperation !== null) {
            if (!hash_equals((string)$existingOperation['request_hash'], $requestHash)) {
                throw new M07CanonicalException('M07_IDEMPOTENCY_CONFLICT', 409, 'La clave de idempotencia ya fue utilizada con otra solicitud.');
            }
            return ['resultado' => 200, 'code' => 'M07_IDEMPOTENT_REPLAY', 'operation_id' => (int)$existingOperation['id'], 'record' => $this->readOneCatalog($patientId, $clinicId, $role, $questionId)];
        }

        $question = $this->questionInClinic($patientId, $clinicId, $questionId);
        if ($question === null) throw new M07CanonicalException('M07_SCOPE_DENIED', 403, 'La pregunta no pertenece al catálogo autorizado.');

        $this->db->beginTransaction();
        $committed = false;
        try {
            $patientLock = $this->db->prepare('SELECT id FROM pc_paciente WHERE id=:patient AND id_clinica=:clinic FOR UPDATE');
            $patientLock->execute([':patient' => $patientId, ':clinic' => $clinicId]);
            if ($patientLock->fetchColumn() === false) {
                throw new M07CanonicalException('M07_CLINIC_DENIED', 403, 'El paciente no pertenece a la clínica solicitada.');
            }
            $rows = $this->lockedCatalogRows($patientId, $questionId);
            if (count($rows) > 1) throw new M07CanonicalException('M07_DUPLICATE_RESPONSE', 409, 'Existen respuestas duplicadas; no se modificó el expediente.');
            $current = $rows[0] ?? $this->emptyCatalogValues();
            $currentVersion = $rows === [] ? 0 : (int)($current['m07_version'] ?? 1);
            if ($currentVersion !== $expectedVersion) throw new M07CanonicalException('M07_VERSION_CONFLICT', 409, 'La versión M07 está obsoleta.');
            if ($draft) $this->assertDraftBaseline($current);

            $values = $this->mergePatch($current, $patch, $fieldMask, $draft, $confirmClear);
            if ($this->isOtherCatalogQuestion((string)$question['pregunta'])) {
                if ($values['respuesta'] === 'Si') {
                    throw new M07CanonicalException('M07_OTHER_CREATE_REQUIRED', 422, 'Registra el nombre del medicamento adicional desde la captura vinculada a esta pregunta.');
                }
                $linked = $this->db->prepare('SELECT COUNT(*) FROM pac_m07_medicamentos_no_catalogados WHERE id_paciente=:patient AND id_clinica=:clinic AND ambito=:scope AND id_pregunta=:question AND estado_registro=:status');
                $linked->execute([':patient'=>$patientId, ':clinic'=>$clinicId, ':scope'=>'M07_P09', ':question'=>$questionId, ':status'=>'ACTIVO']);
                if ((int)$linked->fetchColumn() > 0) {
                    throw new M07CanonicalException('M07_OTHER_ACTIVE_ENTRIES_CONFLICT', 409, 'No se puede confirmar “No” mientras existan medicamentos adicionales activos vinculados.');
                }
            }
            $beforeHash = $rows === [] ? null : $this->hashValues($current);
            if ($rows === []) {
                $insert = $this->db->prepare('INSERT INTO pac_respuestas_paciente_modulo_7
                    (id_pregunta,id_paciente,respuesta,dosis,resultados,consumo,descripcion,
                     m07_respuesta_state,m07_dosis_state,m07_resultados_state,m07_consumo_state,m07_descripcion_state,
                     m07_eficacia_codigo,m07_eficacia_state,m07_tolerabilidad_codigo,m07_tolerabilidad_state,
                     m07_efectos_descripcion,m07_efectos_descripcion_state,m07_version,m07_updated_at,m07_updated_by,m07_provenance)
                    VALUES (:question,:patient,:respuesta,:dosis,:resultados,:consumo,:descripcion,
                     :respuesta_state,:dosis_state,:resultados_state,:consumo_state,:descripcion_state,
                     :eficacia_codigo,:eficacia_state,:tolerabilidad_codigo,:tolerabilidad_state,
                     :efectos,:efectos_state,1,NOW(),:actor,:provenance)');
                $insert->execute($this->writeParams($questionId, $patientId, $values, $actorId, $provenance));
                $targetId = (int)$this->db->lastInsertId();
            } else {
                $targetId = (int)$current['id'];
                $update = $this->db->prepare('UPDATE pac_respuestas_paciente_modulo_7 SET
                    respuesta=:respuesta,dosis=:dosis,resultados=:resultados,consumo=:consumo,descripcion=:descripcion,
                    m07_respuesta_state=:respuesta_state,m07_dosis_state=:dosis_state,m07_resultados_state=:resultados_state,
                    m07_consumo_state=:consumo_state,m07_descripcion_state=:descripcion_state,
                    m07_eficacia_codigo=:eficacia_codigo,m07_eficacia_state=:eficacia_state,
                    m07_tolerabilidad_codigo=:tolerabilidad_codigo,m07_tolerabilidad_state=:tolerabilidad_state,
                    m07_efectos_descripcion=:efectos,m07_efectos_descripcion_state=:efectos_state,
                    m07_version=m07_version+1,m07_updated_at=NOW(),m07_updated_by=:actor,m07_provenance=:provenance
                    WHERE id=:id AND id_paciente=:patient AND id_pregunta=:question');
                $params = $this->writeParams($questionId, $patientId, $values, $actorId, $provenance);
                $params[':id'] = $targetId;
                $update->execute($params);
            }
            $afterRow = $this->lockedCatalogRows($patientId, $questionId)[0];
            $afterHash = $this->hashValues($afterRow);
            $operation = $this->db->prepare('INSERT INTO pac_m07_operaciones
                (idempotency_key,id_paciente,id_clinica,ambito,target_type,target_id,expected_version,request_hash,result_code,action,provenance,before_hash,after_hash,actor_id,reason,created_at)
                VALUES (:key,:patient,:clinic,:scope,:target_type,:target_id,:expected,:request,:result,:action,:provenance,:before_hash,:after_hash,:actor,:reason,NOW())');
            $operation->execute([
                ':key' => $idempotencyKey, ':patient' => $patientId, ':clinic' => $clinicId,
                ':scope' => 'M07_P09', ':target_type' => 'CATALOG', ':target_id' => $targetId,
                ':expected' => $expectedVersion, ':request' => $requestHash, ':result' => 'M07_CONFIRMED',
                ':action' => $draft ? 'DRAFT' : 'CAPTURE', ':provenance' => $provenance,
                ':before_hash' => $beforeHash, ':after_hash' => $afterHash, ':actor' => $actorId,
                ':reason' => $draft ? 'UNANSWERED_EXPLICIT_DRAFT' : null,
            ]);
            $operationId = (int)$this->db->lastInsertId();
            $this->db->commit();
            $committed = true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($committed) throw new M07CanonicalException('M07_READBACK_FAILED', 500, 'La respuesta se guardó, pero el servidor no pudo verificarla.', $exception);
            if ($exception instanceof M07CanonicalException) throw $exception;
            throw new M07CanonicalException('M07_ATOMIC_ROLLBACK', 422, 'No fue posible guardar M07; no se aplicaron cambios parciales.', $exception);
        }
        try {
            $record = $this->readOneCatalog($patientId, $clinicId, $role, $questionId);
        } catch (\Throwable $exception) {
            throw new M07CanonicalException('M07_READBACK_FAILED', 500, 'La respuesta se guardó, pero el servidor no pudo verificarla.', $exception);
        }
        return ['resultado' => 200, 'code' => $draft ? 'M07_DRAFT_SAVED' : 'M07_CONFIRMED', 'operation_id' => $operationId, 'record' => $record];
    }

    /**
     * Finalize M07 only after every canonical catalogue item and active
     * unlisted medication is complete. The existing module-finalization table
     * remains the durable source of truth; the canonical operation log records
     * this transition atomically.
     */
    public function finalizeModule(array $scope, string $expectedStateVersion, string $idempotencyKey, ?int $actorId = null): array
    {
        $patientId = (int)($scope['patient_id'] ?? 0);
        $clinicId = (int)($scope['clinic_id'] ?? 0);
        $role = (string)($scope['role'] ?? '');
        $this->assertScope($patientId, $clinicId, $role, true);
        $this->requireWrite();
        $this->assertIdempotencyKey($idempotencyKey);
        if ($expectedStateVersion === '') {
            throw new M07CanonicalException('M07_VERSION_REQUIRED', 422, 'La finalización M07 requiere una versión vigente.');
        }

        $requestHash = hash('sha256', json_encode(
            [$patientId, $clinicId, 'M07_FINALIZE', $expectedStateVersion],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
        $existing = $this->findOperation($patientId, $idempotencyKey);
        if ($existing !== null) {
            if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                throw new M07CanonicalException('M07_IDEMPOTENCY_CONFLICT', 409, 'La clave de idempotencia ya fue utilizada con otra solicitud.');
            }
            $readback = $this->read($patientId, $clinicId, $role);
            if (($readback['finalized'] ?? false) !== true
                || (int)($readback['pending_items'] ?? -1) !== 0
                || !hash_equals((string)($existing['after_hash'] ?? ''), (string)$readback['version'])) {
                throw new M07CanonicalException('M07_FINALIZATION_READBACK_FAILED', 500, 'El servidor no confirmó el cierre de M07.');
            }
            return ['resultado' => 200, 'code' => 'M07_FINALIZATION_REPLAY', 'finalized' => true, 'readback' => true, 'version' => $readback['version']];
        }

        $this->db->beginTransaction();
        $committed = false;
        try {
            $patient = $this->db->prepare('SELECT id FROM pc_paciente WHERE id=:patient AND id_clinica=:clinic FOR UPDATE');
            $patient->execute([':patient' => $patientId, ':clinic' => $clinicId]);
            if ($patient->fetchColumn() === false) {
                throw new M07CanonicalException('M07_CLINIC_DENIED', 403, 'El paciente no pertenece a la clínica solicitada.');
            }

            $current = $this->read($patientId, $clinicId, $role);
            if (!hash_equals((string)$current['version'], $expectedStateVersion)) {
                throw new M07CanonicalException('M07_VERSION_CONFLICT', 409, 'M07 cambió antes de finalizarse. Recarga el módulo.');
            }
            if (count($current['records'] ?? []) !== 46) {
                throw new M07CanonicalException('M07_CATALOG_INCOMPLETE', 503, 'No se pudo verificar el catálogo completo de M07.');
            }
            if ((int)($current['pending_items'] ?? 0) !== 0) {
                throw new M07CanonicalException('M07_PENDING_RESPONSES', 409, 'M07 conserva respuestas pendientes o conflictos.');
            }
            if (($current['finalized'] ?? false) === true) {
                $this->db->commit();
                return ['resultado' => 200, 'code' => 'M07_ALREADY_FINALIZED', 'finalized' => true, 'readback' => true, 'version' => $current['version']];
            }

            $insert = $this->db->prepare('INSERT INTO pac_historia_clinica_finalizar (id_modulo,id_paciente) VALUES (7,:patient)');
            $insert->execute([':patient' => $patientId]);
            $finalizationId = (int)$this->db->lastInsertId();
            $afterState = $current;
            unset($afterState['version'], $afterState['pending_items']);
            $afterState['finalized'] = true;
            $afterState['finalization_enabled'] = false;
            $afterHash = hash('sha256', json_encode($afterState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $operation = $this->db->prepare('INSERT INTO pac_m07_operaciones
                (idempotency_key,id_paciente,id_clinica,ambito,target_type,target_id,expected_version,request_hash,result_code,action,provenance,before_hash,after_hash,actor_id,reason,created_at)
                VALUES (:key,:patient,:clinic,:scope,:type,:target,:expected,:request,:result,:action,:provenance,:before_hash,:after_hash,:actor,:reason,NOW())');
            $operation->execute([
                ':key' => $idempotencyKey,
                ':patient' => $patientId,
                ':clinic' => $clinicId,
                ':scope' => 'M07_P09',
                ':type' => 'MODULE',
                ':target' => $finalizationId,
                ':expected' => 0,
                ':request' => $requestHash,
                ':result' => 'M07_FINALIZED',
                ':action' => 'FINALIZE',
                ':provenance' => 'INTERVIEW',
                ':before_hash' => $current['version'],
                ':after_hash' => $afterHash,
                ':actor' => $actorId,
                ':reason' => 'ALL_REQUIRED_ITEMS_COMPLETE',
            ]);
            $this->db->commit();
            $committed = true;

            $readback = $this->read($patientId, $clinicId, $role);
            if (($readback['finalized'] ?? false) !== true || (int)($readback['pending_items'] ?? -1) !== 0 || !hash_equals($afterHash, (string)$readback['version'])) {
                throw new M07CanonicalException('M07_FINALIZATION_READBACK_FAILED', 500, 'El servidor no confirmó el cierre de M07.');
            }
            return ['resultado' => 200, 'code' => 'M07_FINALIZED', 'finalized' => true, 'readback' => true, 'version' => $readback['version']];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($committed) throw new M07CanonicalException('M07_FINALIZATION_READBACK_FAILED', 500, 'El cierre se guardó, pero el servidor no pudo verificarlo.', $exception);
            if ($exception instanceof M07CanonicalException) throw $exception;
            throw new M07CanonicalException('M07_FINALIZATION_ROLLBACK', 500, 'No fue posible finalizar M07; no se aplicaron cambios parciales.', $exception);
        }
    }

    /** Create an independently versioned “Otro” row linked to its canonical yes/no question. */
    public function createOtherMedication(
        array $scope,
        int $questionId,
        int $themeId,
        string $name,
        string $expectedStateVersion,
        int $actorId,
        string $idempotencyKey,
    ): array {
        $patientId = (int)($scope['patient_id'] ?? 0);
        $clinicId = (int)($scope['clinic_id'] ?? 0);
        $role = (string)($scope['role'] ?? '');
        $this->assertScope($patientId, $clinicId, $role, true);
        $this->requireWrite();
        $this->assertIdempotencyKey($idempotencyKey);
        $name = $this->validatedOtherName($name);
        $question = $this->authorizedOtherQuestion($patientId, $clinicId, $questionId, $themeId);
        $requestHash = hash('sha256', json_encode([$patientId,$clinicId,$questionId,$themeId,$name,$expectedStateVersion], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $existingOperation = $this->findOperation($patientId, $idempotencyKey);
        if ($existingOperation !== null) {
            if (!hash_equals((string)$existingOperation['request_hash'], $requestHash)) {
                throw new M07CanonicalException('M07_IDEMPOTENCY_CONFLICT', 409, 'La clave de idempotencia ya fue utilizada con otra solicitud.');
            }
            $record = $this->readOtherEntry($patientId, $clinicId, (int)$existingOperation['target_id']);
            if ($record === null || (int)$record['question_id'] !== $questionId || $record['name'] !== $name
                || !hash_equals((string)($existingOperation['after_hash'] ?? ''), $this->hashValues($record))) {
                throw new M07CanonicalException('M07_READBACK_FAILED', 500, 'No se pudo verificar el reintento del medicamento adicional.');
            }
            return ['resultado'=>200,'code'=>'M07_IDEMPOTENT_REPLAY','operation_id'=>(int)$existingOperation['id'],'record'=>$record];
        }

        $this->db->beginTransaction();
        try {
            $this->lockAuthorizedPatient($patientId, $clinicId);
            $state = $this->read($patientId, $clinicId, $role);
            if (!hash_equals((string)$state['version'], $expectedStateVersion)) {
                throw new M07CanonicalException('M07_VERSION_CONFLICT', 409, 'M07 cambió antes de crear el medicamento adicional. Recarga la entrevista.');
            }
            if (($state['finalized'] ?? false) === true) throw new M07CanonicalException('M07_FINALIZED',409,'M07 ya está finalizado y no admite cambios.');
            $catalog = $this->readOneCatalog($patientId, $clinicId, $role, $questionId);
            if (($catalog['fields']['respuesta'] ?? '') === 'No') {
                throw new M07CanonicalException('M07_OTHER_NEGATIVE_REQUIRES_CORRECTION', 409, 'La respuesta negativa existente requiere una corrección autorizada antes de agregar un medicamento.');
            }

            $entryKey = $this->uuidV4();
            $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? $name, 'UTF-8');
            $insert = $this->db->prepare('INSERT INTO pac_m07_medicamentos_no_catalogados
                (entry_key,id_paciente,id_clinica,ambito,id_tema,id_pregunta,nombre,nombre_normalizado,estado_registro,
                 respuesta,dosis,consumo,eficacia_codigo,eficacia_state,tolerabilidad_codigo,tolerabilidad_state,descripcion_efectos,
                 respuesta_state,dosis_state,consumo_state,descripcion_efectos_state,version,provenance,created_at,updated_at,created_by,updated_by)
                VALUES (:entry,:patient,:clinic,:scope,:theme,:question,:name,:normalized,:status,
                 :response,NULL,NULL,NULL,:efficacy_state,NULL,:tolerability_state,NULL,
                 :response_state,:dose_state,:consumption_state,:description_state,1,:provenance,NOW(),NOW(),:created_by,:updated_by)');
            $insert->execute([
                ':entry'=>$entryKey, ':patient'=>$patientId, ':clinic'=>$clinicId, ':scope'=>'M07_P09',
                ':theme'=>(int)$question['id_tema'], ':question'=>$questionId, ':name'=>$name, ':normalized'=>$normalized,
                ':status'=>'ACTIVO', ':response'=>'Si', ':efficacy_state'=>self::UNANSWERED,
                ':tolerability_state'=>self::UNANSWERED, ':response_state'=>self::CONFIRMED,
                ':dose_state'=>self::UNANSWERED, ':consumption_state'=>self::UNANSWERED,
                ':description_state'=>self::UNANSWERED, ':provenance'=>'INTERVIEW',
                ':created_by'=>$actorId, ':updated_by'=>$actorId,
            ]);
            $id = (int)$this->db->lastInsertId();
            $record = $this->readOtherEntry($patientId, $clinicId, $id);
            if ($record === null || (int)$record['question_id'] !== $questionId || $record['name'] !== $name || $record['status'] !== 'pending') {
                throw new M07CanonicalException('M07_READBACK_FAILED', 500, 'No se confirmó la lectura del medicamento adicional creado.');
            }
            $afterHash = $this->hashValues($record);
            $operation = $this->insertOtherOperation($idempotencyKey,$patientId,$clinicId,$id,0,$requestHash,'M07_OTHER_CREATED','CREATE_OTHER',$afterHash,$actorId);
            $this->db->commit();
            return ['resultado'=>200,'code'=>'M07_OTHER_CREATED','operation_id'=>$operation,'record'=>$record];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($exception instanceof M07CanonicalException) throw $exception;
            throw new M07CanonicalException('M07_ATOMIC_ROLLBACK',422,'No fue posible crear el medicamento adicional; no se aplicaron cambios parciales.',$exception);
        }
    }

    /** Update one linked additional-medication record with optimistic versioning and audit. */
    public function updateOtherMedication(
        array $scope,
        int $questionId,
        int $themeId,
        int $entryId,
        int $expectedVersion,
        array $patch,
        string $expectedStateVersion,
        int $actorId,
        string $idempotencyKey,
    ): array {
        $patientId=(int)($scope['patient_id']??0); $clinicId=(int)($scope['clinic_id']??0); $role=(string)($scope['role']??'');
        $this->assertScope($patientId,$clinicId,$role,true); $this->requireWrite(); $this->assertIdempotencyKey($idempotencyKey);
        $question = $this->authorizedOtherQuestion($patientId,$clinicId,$questionId,$themeId);
        $patch = $this->validateOtherPatch($patch);
        if ($entryId < 1 || $expectedVersion < 1 || $expectedStateVersion === '' || $patch === []) throw new M07CanonicalException('M07_OTHER_PATCH_INVALID',422,'La actualización del medicamento adicional está incompleta.');
        $requestHash=hash('sha256',json_encode([$patientId,$clinicId,$questionId,$themeId,$entryId,$expectedVersion,$patch,$expectedStateVersion],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $existing=$this->findOperation($patientId,$idempotencyKey);
        if($existing!==null){ if(!hash_equals((string)$existing['request_hash'],$requestHash)) throw new M07CanonicalException('M07_IDEMPOTENCY_CONFLICT',409,'La clave de idempotencia ya fue utilizada con otra solicitud.'); $record=$this->readOtherEntry($patientId,$clinicId,$entryId); if($record===null||(int)$record['question_id']!==$questionId||!hash_equals((string)($existing['after_hash']??''),$this->hashValues($record))) throw new M07CanonicalException('M07_READBACK_FAILED',500,'No se pudo verificar el reintento del medicamento adicional.'); return ['resultado'=>200,'code'=>'M07_IDEMPOTENT_REPLAY','operation_id'=>(int)$existing['id'],'record'=>$record]; }
        $this->db->beginTransaction();
        try {
            $this->lockAuthorizedPatient($patientId,$clinicId);
            $state=$this->read($patientId,$clinicId,$role);
            if(!hash_equals((string)$state['version'],$expectedStateVersion)) throw new M07CanonicalException('M07_VERSION_CONFLICT',409,'M07 cambió antes de actualizar el medicamento adicional. Recarga la entrevista.');
            if(($state['finalized']??false)===true) throw new M07CanonicalException('M07_FINALIZED',409,'M07 ya está finalizado y no admite cambios.');
            $select=$this->db->prepare('SELECT * FROM pac_m07_medicamentos_no_catalogados WHERE id=:id AND id_paciente=:patient AND id_clinica=:clinic AND ambito=:scope AND id_tema=:theme AND id_pregunta=:question AND estado_registro=:status FOR UPDATE');
            $select->execute([':id'=>$entryId,':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07_P09',':theme'=>(int)$question['id_tema'],':question'=>$questionId,':status'=>'ACTIVO']);
            $current=$select->fetch(PDO::FETCH_ASSOC);
            if(!$current) throw new M07CanonicalException('M07_SCOPE_DENIED',403,'El medicamento no pertenece a la pregunta, paciente y clínica autorizados.');
            if((int)$current['version']!==$expectedVersion) throw new M07CanonicalException('M07_VERSION_CONFLICT',409,'La versión del medicamento quedó obsoleta. Recarga antes de corregir.');
            $next=$this->mergeOtherPatch($current,$patch);
            $beforeHash=$this->hashValues($current);
            $update=$this->db->prepare('UPDATE pac_m07_medicamentos_no_catalogados SET nombre=:name,nombre_normalizado=:normalized,dosis=:dose,consumo=:consumption,eficacia_codigo=:efficacy,eficacia_state=:efficacy_state,tolerabilidad_codigo=:tolerability,tolerabilidad_state=:tolerability_state,descripcion_efectos=:description,respuesta_state=:response_state,dosis_state=:dose_state,consumo_state=:consumption_state,descripcion_efectos_state=:description_state,version=version+1,updated_at=NOW(),updated_by=:actor WHERE id=:id AND id_paciente=:patient AND id_clinica=:clinic AND id_pregunta=:question AND version=:version AND estado_registro=:status');
            $update->execute([':name'=>$next['nombre'],':normalized'=>$next['nombre_normalizado'],':dose'=>$next['dosis'],':consumption'=>$next['consumo'],':efficacy'=>$next['eficacia_codigo'],':efficacy_state'=>$next['eficacia_state'],':tolerability'=>$next['tolerabilidad_codigo'],':tolerability_state'=>$next['tolerabilidad_state'],':description'=>$next['descripcion_efectos'],':response_state'=>$next['respuesta_state'],':dose_state'=>$next['dosis_state'],':consumption_state'=>$next['consumo_state'],':description_state'=>$next['descripcion_efectos_state'],':actor'=>$actorId,':id'=>$entryId,':patient'=>$patientId,':clinic'=>$clinicId,':question'=>$questionId,':version'=>$expectedVersion,':status'=>'ACTIVO']);
            if($update->rowCount()!==1) throw new M07CanonicalException('M07_VERSION_CONFLICT',409,'El medicamento cambió durante la actualización.');
            $record=$this->readOtherEntry($patientId,$clinicId,$entryId);
            if($record===null||(int)$record['question_id']!==$questionId||(int)$record['version']!==$expectedVersion+1||!$this->otherPatchMatches($record,$patch)) throw new M07CanonicalException('M07_READBACK_FAILED',500,'No se confirmó la lectura del medicamento actualizado.');
            $afterHash=$this->hashValues($record);
            $operation=$this->insertOtherOperation($idempotencyKey,$patientId,$clinicId,$entryId,$expectedVersion,$requestHash,'M07_OTHER_UPDATED','UPDATE_OTHER',$afterHash,$actorId,$beforeHash);
            $this->db->commit();
            return ['resultado'=>200,'code'=>'M07_OTHER_UPDATED','operation_id'=>$operation,'record'=>$record];
        } catch(\Throwable $exception){ if($this->db->inTransaction())$this->db->rollBack(); if($exception instanceof M07CanonicalException)throw $exception; throw new M07CanonicalException('M07_ATOMIC_ROLLBACK',422,'No fue posible actualizar el medicamento adicional; no se aplicaron cambios parciales.',$exception); }
    }

    /** Review can only be written against the exact snapshot the reviewer saw. */
    public function recordReview(array $scope, string $expectedSnapshotHash, string $status, int $reviewerId): array
    {
        $patientId=(int)($scope['patient_id']??0); $clinicId=(int)($scope['clinic_id']??0); $role=(string)($scope['role']??'');
        $this->assertScope($patientId,$clinicId,$role,true); $this->requireWrite();
        if (!in_array($status,['PENDING','ACCEPTED','REQUIRES_CORRECTION'],true)) throw new M07CanonicalException('M07_REVIEW_STATUS_INVALID',422,'El estado de revisión no es válido.');
        $current=$this->read($patientId,$clinicId,$role); if (!hash_equals($expectedSnapshotHash,(string)$current['version'])) throw new M07CanonicalException('M07_REVIEW_STALE',409,'La revisión quedó obsoleta porque M07 cambió.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id,version FROM pac_m07_revision_clinica WHERE id_paciente=:patient AND id_clinica=:clinic AND scope=:scope FOR UPDATE'); $stmt->execute([':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07']); $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { $reviewId=(int)$row['id']; $up=$this->db->prepare('UPDATE pac_m07_revision_clinica SET status=:status,version=version+1,reviewed_by=:reviewer,reviewed_at=NOW(),updated_at=NOW() WHERE id=:id'); $up->execute([':status'=>$status,':reviewer'=>$reviewerId,':id'=>$reviewId]); $version=(int)$row['version']+1; } else { $in=$this->db->prepare('INSERT INTO pac_m07_revision_clinica (id_paciente,id_clinica,scope,status,version,reviewed_by,reviewed_at,created_at,updated_at) VALUES (:patient,:clinic,:scope,:status,1,:reviewer,NOW(),NOW(),NOW())'); $in->execute([':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07',':status'=>$status,':reviewer'=>$reviewerId]); $reviewId=(int)$this->db->lastInsertId(); $version=1; }
            $operationKey=$this->uuidV4(); $operation=$this->db->prepare('INSERT INTO pac_m07_operaciones (idempotency_key,id_paciente,id_clinica,ambito,target_type,target_id,expected_version,request_hash,result_code,action,provenance,before_hash,after_hash,actor_id,reason,created_at) VALUES (:key,:patient,:clinic,:scope,:type,:id,:expected,:request,:result,:action,:prov,:before,:after,:actor,:reason,NOW())'); $operation->execute([':key'=>$operationKey,':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07_P09',':type'=>'REVIEW',':id'=>$reviewId,':expected'=>0,':request'=>hash('sha256',$expectedSnapshotHash.'|'.$status.'|'.$reviewerId),':result'=>'M07_REVIEW_RECORDED',':action'=>'REVIEW',':prov'=>'INTERVIEW',':before'=>$expectedSnapshotHash,':after'=>$current['version'],':actor'=>$reviewerId,':reason'=>'CLINICAL_REVIEW']);
            $this->db->commit(); return ['resultado'=>200,'code'=>'M07_REVIEW_RECORDED','version'=>$version,'status'=>$status,'operation_id'=>(int)$this->db->lastInsertId()];
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw new M07CanonicalException('M07_ATOMIC_ROLLBACK',422,'No fue posible registrar la revisión.',$exception); }
    }

    private function requireWrite(): void
    {
        if (!$this->active()) throw new M07CanonicalException('M07_CANONICAL_DISABLED',403,'La escritura canónica M07 está deshabilitada.');
    }

    private function assertScope(int $patientId, int $clinicId, string $role, bool $write): void
    {
        if ($patientId < 1 || $clinicId < 1 || !in_array(mb_strtolower(trim($role), 'UTF-8'), ['doctor','patient','synthetic','admin'], true)) throw new M07CanonicalException('M07_SCOPE_DENIED',403,'El rol o alcance M07 no está autorizado.');
        if (!$this->gate->allowsPatientScope($patientId, $clinicId, $role)) throw new M07CanonicalException('M07_SCOPE_DENIED',403,'El alcance M07 no está autorizado en este entorno.');
        $stmt=$this->db->prepare('SELECT id_clinica FROM pc_paciente WHERE id=:patient LIMIT 1'); $stmt->execute([':patient'=>$patientId]); $patientClinic=$stmt->fetchColumn();
        if ($patientClinic === false || (int)$patientClinic !== $clinicId) throw new M07CanonicalException('M07_CLINIC_DENIED',403,'El paciente no pertenece a la clínica solicitada.');
    }

    private function requireSchema(): void
    {
        if (!$this->schemaAvailable()) throw new M07CanonicalException('M07_SCHEMA_UNAVAILABLE',503,'La extensión canónica M07 no está migrada.');
    }

    private function schemaAvailable(): bool
    {
        try {
            $table=$this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?,?,?)'); $table->execute(['pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica','pac_historia_clinica_finalizar']);
            if ((int)$table->fetchColumn() !== 4) return false;
            $columns=$this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name IN (' . implode(',', array_fill(0,count(self::CATALOG_COLUMNS),'?')) . ')'); $columns->execute(array_merge(['pac_respuestas_paciente_modulo_7'],self::CATALOG_COLUMNS));
            if ((int)$columns->fetchColumn() !== count(self::CATALOG_COLUMNS)) return false;
            $link=$this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? AND is_nullable=\'YES\'');
            $link->execute(['pac_m07_medicamentos_no_catalogados','id_pregunta']);
            return (int)$link->fetchColumn() === 1;
        } catch (\Throwable) { return false; }
    }

    private function questionInClinic(int $patientId,int $clinicId,int $questionId): ?array
    { $s=$this->db->prepare('SELECT q.id,q.id_tema,q.pregunta,t.tema FROM pac_preguntas_modulo_7 q INNER JOIN pac_temas_modulo_7 t ON t.id=q.id_tema WHERE q.id=:question AND EXISTS (SELECT 1 FROM pc_paciente p WHERE p.id=:patient AND p.id_clinica=:clinic) LIMIT 1'); $s->execute([':question'=>$questionId,':patient'=>$patientId,':clinic'=>$clinicId]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }

    private function readOneCatalog(int $patientId,int $clinicId,string $role,int $questionId): array
    { $state=$this->read($patientId,$clinicId,$role); foreach($state['records'] as $record) if((int)$record['question_id']===$questionId) return $record; throw new M07CanonicalException('M07_SCOPE_DENIED',403,'La entrada M07 no existe.'); }

    private function lockedCatalogRows(int $patientId,int $questionId): array
    { $s=$this->db->prepare('SELECT * FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente=:patient AND id_pregunta=:question ORDER BY id FOR UPDATE'); $s->execute([':patient'=>$patientId,':question'=>$questionId]); return $s->fetchAll(PDO::FETCH_ASSOC); }

    private function emptyCatalogValues(): array
    { return ['respuesta'=>'','dosis'=>'','resultados'=>'','consumo'=>'','descripcion'=>'','m07_respuesta_state'=>self::HISTORICAL,'m07_dosis_state'=>self::HISTORICAL,'m07_resultados_state'=>self::HISTORICAL,'m07_consumo_state'=>self::HISTORICAL,'m07_descripcion_state'=>self::HISTORICAL,'m07_eficacia_codigo'=>null,'m07_eficacia_state'=>self::HISTORICAL,'m07_tolerabilidad_codigo'=>null,'m07_tolerabilidad_state'=>self::HISTORICAL,'m07_efectos_descripcion'=>null,'m07_efectos_descripcion_state'=>self::HISTORICAL,'m07_version'=>0,'m07_provenance'=>'INTERVIEW']; }

    private function assertDraftBaseline(array $current): void
    {
        $state = (string)($current['m07_respuesta_state'] ?? self::HISTORICAL);
        $hasDocumentedValue = trim((string)($current['respuesta'] ?? '')) !== ''
            || trim((string)($current['dosis'] ?? '')) !== ''
            || trim((string)($current['resultados'] ?? '')) !== ''
            || trim((string)($current['consumo'] ?? '')) !== ''
            || trim((string)($current['descripcion'] ?? '')) !== '';

        if ($hasDocumentedValue || !in_array($state, [self::HISTORICAL, self::UNANSWERED], true)) {
            throw new M07CanonicalException(
                'M07_DRAFT_EXISTING_RESPONSE',
                409,
                'No se puede guardar un borrador sobre una entrada con información clínica existente.'
            );
        }
    }

    private function mergePatch(array $current,array $patch,array $mask,bool $draft,bool $confirmClear): array
    {
        $values=array_merge($this->emptyCatalogValues(),$current);
        foreach($mask as $field) if(array_key_exists($field,$patch)) {
            $target = in_array($field, ['respuesta','dosis','resultados','consumo','descripcion'], true) ? $field : ($field === 'descripcion_efectos' ? 'm07_efectos_descripcion' : 'm07_'.$field);
            $values[$target] = $patch[$field];
        }
        if(in_array('respuesta_state',$mask,true)) { $state=(string)$values['m07_respuesta_state']; if($state!==self::UNANSWERED) throw new M07CanonicalException('M07_STATE_INVALID',422,'El estado de captura no es válido.'); if(!$draft) throw new M07CanonicalException('M07_STATE_INVALID',422,'UNANSWERED sólo puede guardarse como borrador explícito.'); return $values; }
        $values['respuesta']=trim((string)($values['respuesta']??'')); $values['dosis']=trim((string)($values['dosis']??'')); $values['resultados']=trim((string)($values['resultados']??'')); $values['consumo']=trim((string)($values['consumo']??'')); $values['descripcion']=trim((string)($values['descripcion']??''));
        if(!in_array($values['respuesta'],['Si','No'],true)) throw new M07CanonicalException('M07_RESPONSE_INVALID',422,'La respuesta M07 debe ser Sí o No.');
        if($values['respuesta']==='No') { $has=($current['dosis']??'')!==''||($current['resultados']??'')!==''||($current['consumo']??'')!==''||($current['descripcion']??'')!==''; if($has&&!$confirmClear) throw new M07CanonicalException('M07_DEPENDENT_CLEAR_CONFIRMATION_REQUIRED',409,'Confirma explícitamente la limpieza de datos dependientes.'); $values['dosis']=$values['resultados']=$values['consumo']=$values['descripcion']=''; $values['m07_respuesta_state']=self::CONFIRMED; foreach(['dosis','resultados','consumo','descripcion'] as $name) $values['m07_'.$name.'_state']=self::NOT_APPLICABLE; $values['m07_eficacia_codigo']=null; $values['m07_eficacia_state']=self::NOT_APPLICABLE; $values['m07_tolerabilidad_codigo']=null; $values['m07_tolerabilidad_state']=self::NOT_APPLICABLE; $values['m07_efectos_descripcion']=null; $values['m07_efectos_descripcion_state']=self::NOT_APPLICABLE; return $values; }
        if($values['dosis']===''||$values['consumo']===''||!in_array($values['consumo'],['Si','No'],true)) throw new M07CanonicalException('M07_DEPENDENCIES_INCOMPLETE',422,'Completa dosis y consumo antes de confirmar.');
        if($values['resultados']!==''&&!in_array($values['resultados'],['Funciono','No funciono','Tuve efectos adversos'],true)) throw new M07CanonicalException('M07_RESULT_INVALID',422,'El resultado no coincide con las opciones oficiales.');
        if($values['resultados']==='') { $values['m07_resultados_state']=self::PENDING; } else { $values['m07_resultados_state']=self::CONFIRMED; if($values['resultados']==='Funciono'){ $values['m07_eficacia_codigo']='FUNCIONO';$values['m07_eficacia_state']=self::CONFIRMED;$values['m07_tolerabilidad_codigo']=null;$values['m07_tolerabilidad_state']=self::HISTORICAL; } elseif($values['resultados']==='No funciono'){ $values['m07_eficacia_codigo']='NO_FUNCIONO';$values['m07_eficacia_state']=self::CONFIRMED;$values['m07_tolerabilidad_codigo']=null;$values['m07_tolerabilidad_state']=self::HISTORICAL; } else { $values['m07_eficacia_codigo']=null;$values['m07_eficacia_state']=self::HISTORICAL;$values['m07_tolerabilidad_codigo']='PRESENTO_EFECTOS';$values['m07_tolerabilidad_state']=self::CONFIRMED; if($values['descripcion']==='') throw new M07CanonicalException('M07_ADVERSE_DESCRIPTION_REQUIRED',422,'Describe los efectos adversos antes de confirmar.'); } }
        $values['m07_respuesta_state']=self::CONFIRMED; $values['m07_dosis_state']=self::CONFIRMED; $values['m07_consumo_state']=self::CONFIRMED; $values['m07_descripcion_state']=$values['descripcion']!==''?self::CONFIRMED:self::HISTORICAL; $values['m07_efectos_descripcion']=$values['resultados']==='Tuve efectos adversos'?$values['descripcion']:null; $values['m07_efectos_descripcion_state']=$values['resultados']==='Tuve efectos adversos'?$values['m07_descripcion_state']:self::NOT_APPLICABLE;
        if(array_key_exists('eficacia_codigo',$patch)||array_key_exists('tolerabilidad_codigo',$patch)){ $values['resultados']='';$values['m07_resultados_state']=self::PENDING;$values['m07_eficacia_codigo']=$patch['eficacia_codigo']??$values['m07_eficacia_codigo'];$values['m07_tolerabilidad_codigo']=$patch['tolerabilidad_codigo']??$values['m07_tolerabilidad_codigo'];$values['m07_eficacia_state']=$values['m07_eficacia_codigo']?self::CONFIRMED:self::HISTORICAL;$values['m07_tolerabilidad_state']=$values['m07_tolerabilidad_codigo']?self::CONFIRMED:self::HISTORICAL; if($values['m07_tolerabilidad_codigo']==='PRESENTO_EFECTOS'){ $values['m07_efectos_descripcion']=$patch['descripcion_efectos']??$patch['descripcion']??$values['m07_efectos_descripcion']??null; if(trim((string)$values['m07_efectos_descripcion'])==='') throw new M07CanonicalException('M07_ADVERSE_DESCRIPTION_REQUIRED',422,'Describe los efectos adversos antes de confirmar.'); $values['m07_efectos_descripcion_state']=self::CONFIRMED; } }
        return $values;
    }

    private function writeParams(int $questionId,int $patientId,array $v,?int $actorId,string $provenance): array
    { return [':question'=>$questionId,':patient'=>$patientId,':respuesta'=>$v['respuesta'],':dosis'=>$v['dosis'],':resultados'=>$v['resultados'],':consumo'=>$v['consumo'],':descripcion'=>$v['descripcion'],':respuesta_state'=>$v['m07_respuesta_state'],':dosis_state'=>$v['m07_dosis_state'],':resultados_state'=>$v['m07_resultados_state'],':consumo_state'=>$v['m07_consumo_state'],':descripcion_state'=>$v['m07_descripcion_state'],':eficacia_codigo'=>$v['m07_eficacia_codigo'],':eficacia_state'=>$v['m07_eficacia_state'],':tolerabilidad_codigo'=>$v['m07_tolerabilidad_codigo'],':tolerabilidad_state'=>$v['m07_tolerabilidad_state'],':efectos'=>$v['m07_efectos_descripcion'],':efectos_state'=>$v['m07_efectos_descripcion_state'],':actor'=>$actorId,':provenance'=>$provenance]; }

    private function recordFromCatalogRow(array $row,int $patientId,int $clinicId,bool $duplicate): M07CanonicalRecord
    { $id=(int)($row['response_id']??0); $f=['respuesta'=>trim((string)($row['respuesta']??'')),'dosis'=>trim((string)($row['dosis']??'')),'resultados'=>trim((string)($row['resultados']??'')),'consumo'=>trim((string)($row['consumo']??'')),'descripcion'=>trim((string)($row['descripcion']??''))]; $e=['code'=>$row['m07_eficacia_codigo']??null,'state'=>$row['m07_eficacia_state']??self::HISTORICAL]; $t=['code'=>$row['m07_tolerabilidad_codigo']??null,'state'=>$row['m07_tolerabilidad_state']??self::HISTORICAL,'description'=>$row['m07_efectos_descripcion']??null,'description_state'=>$row['m07_efectos_descripcion_state']??self::HISTORICAL]; $states=['respuesta'=>$row['m07_respuesta_state']??self::HISTORICAL,'dosis'=>$row['m07_dosis_state']??self::HISTORICAL,'resultados'=>$row['m07_resultados_state']??self::HISTORICAL,'consumo'=>$row['m07_consumo_state']??self::HISTORICAL,'descripcion'=>$row['m07_descripcion_state']??self::HISTORICAL,'eficacia'=>$e['state'],'tolerabilidad'=>$t['state'],'efectos_descripcion'=>$t['description_state']]; $status=$duplicate?'conflict':$this->status($f,$row); $reason=$duplicate?'DUPLICATE_RESPONSE':($status==='pending'?'INCOMPLETE':null); return new M07CanonicalRecord('CATALOG',$id,$patientId,$clinicId,(int)$row['theme_id'],(int)$row['question_id'],(string)$row['question'],$f,$e,$t,['theme'=>$row['theme']??'M07','resultados'=>$f['resultados'],'provenance'=>$row['m07_provenance']??'LEGACY'],(int)($row['m07_version']??0),$status,$reason,null,$states); }

    private function status(array $f,array $row): string
    { if($f['respuesta']==='') return 'pending'; if($f['respuesta']==='No') return ($f['dosis'].$f['resultados'].$f['consumo'].$f['descripcion'])===''?'complete':'conflict'; if($f['respuesta']!=='Si'||$f['dosis']===''||$f['consumo']===''||!in_array($f['consumo'],['Si','No'],true)) return 'pending'; if($f['resultados']===''||$row['m07_resultados_state']===self::PENDING) return 'pending'; if($f['resultados']==='Tuve efectos adversos'&&$f['descripcion']==='') return 'pending'; return 'complete'; }

    private function isOtherCatalogQuestion(string $question): bool
    { return preg_match('/\b(otro|otros|otra|otras)\b/iu', $question) === 1; }

    private function readOther(int $patientId,int $clinicId,string $role): array
    {
        $s=$this->db->prepare('SELECT * FROM pac_m07_medicamentos_no_catalogados WHERE id_paciente=:patient AND id_clinica=:clinic AND ambito=:scope ORDER BY id');
        $s->execute([':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07_P09']);
        return array_map(function(array $r): array {
            $result = match ((string)($r['eficacia_codigo'] ?? '')) {
                'FUNCIONO' => 'Funciono',
                'NO_FUNCIONO' => 'No funciono',
                default => (($r['tolerabilidad_codigo'] ?? null) === 'PRESENTO_EFECTOS' ? 'Tuve efectos adversos' : ''),
            };
            $active = ($r['estado_registro'] ?? '') === 'ACTIVO';
            $complete = $active
                && trim((string)($r['nombre'] ?? '')) !== ''
                && ($r['respuesta'] ?? '') === 'Si'
                && ($r['respuesta_state'] ?? '') === self::CONFIRMED
                && trim((string)($r['dosis'] ?? '')) !== ''
                && ($r['dosis_state'] ?? '') === self::CONFIRMED
                && in_array($r['consumo'] ?? null, ['Si','No'], true)
                && ($r['consumo_state'] ?? '') === self::CONFIRMED
                && in_array($result, ['Funciono','No funciono','Tuve efectos adversos'], true)
                && (($r['eficacia_state'] ?? '') === self::CONFIRMED || $result === 'Tuve efectos adversos')
                && ($result !== 'Tuve efectos adversos' || (trim((string)($r['descripcion_efectos'] ?? '')) !== '' && ($r['descripcion_efectos_state'] ?? '') === self::CONFIRMED));
            $status = !$active ? 'inactive' : ($complete ? 'complete' : 'pending');
            return [
                'source'=>'OTHER','id'=>(int)$r['id'],'patient_id'=>(int)$r['id_paciente'],'clinic_id'=>(int)$r['id_clinica'],
                'theme_id'=>(int)$r['id_tema'],'question_id'=>isset($r['id_pregunta'])?(int)$r['id_pregunta']:null,
                'name'=>(string)$r['nombre'],'fields'=>['respuesta'=>$r['respuesta'],'dosis'=>$r['dosis'],'resultados'=>$result,'consumo'=>$r['consumo'],'descripcion'=>$r['descripcion_efectos']],
                'efficacy'=>['code'=>$r['eficacia_codigo'],'state'=>$r['eficacia_state']],
                'tolerability'=>['code'=>$r['tolerabilidad_codigo'],'state'=>$r['tolerabilidad_state'],'description'=>$r['descripcion_efectos'],'description_state'=>$r['descripcion_efectos_state']],
                'states'=>['respuesta'=>$r['respuesta_state']??self::UNANSWERED,'dosis'=>$r['dosis_state']??self::UNANSWERED,'consumo'=>$r['consumo_state']??self::UNANSWERED,'resultados'=>$result!==''?self::CONFIRMED:self::UNANSWERED,'eficacia'=>$r['eficacia_state']??self::UNANSWERED,'tolerabilidad'=>$r['tolerabilidad_state']??self::UNANSWERED,'efectos_descripcion'=>$r['descripcion_efectos_state']??self::UNANSWERED],
                'legacy_projection'=>['provenance'=>$r['provenance']],'version'=>(int)$r['version'],'status'=>$status,
                'pending_reason'=>$status==='pending'?'OTHER_MEDICATION_INCOMPLETE':null,'record_status'=>(string)$r['estado_registro'],'entry_key'=>$r['entry_key'],
            ];
        },$s->fetchAll(PDO::FETCH_ASSOC));
    }

    private function readOtherEntry(int $patientId,int $clinicId,int $entryId): ?array
    {
        foreach ($this->readOther($patientId,$clinicId,'patient') as $entry) if ((int)$entry['id']===$entryId) return $entry;
        return null;
    }

    private function authorizedOtherQuestion(int $patientId,int $clinicId,int $questionId,int $themeId): array
    {
        if (!in_array($questionId,[8,15,22,30,37,46],true)) throw new M07CanonicalException('M07_OTHER_QUESTION_INVALID',403,'La pregunta no pertenece al conjunto canónico “Otro” autorizado.');
        $question=$this->questionInClinic($patientId,$clinicId,$questionId);
        if ($question===null || (int)$question['id_tema']!==$themeId || !$this->isOtherCatalogQuestion((string)$question['pregunta'])) throw new M07CanonicalException('M07_SCOPE_DENIED',403,'La pregunta “Otro” no coincide con la clínica y categoría autorizadas.');
        return $question;
    }

    private function validatedOtherName(string $name): string
    {
        $name=trim(preg_replace('/\s+/u',' ',$name)??$name);
        if ($name==='' || mb_strlen($name,'UTF-8')>255 || preg_match('/[\x00-\x1F\x7F]/u',$name)) throw new M07CanonicalException('M07_OTHER_NAME_INVALID',422,'El nombre del medicamento adicional no es válido.');
        return $name;
    }

    private function validateOtherPatch(array $patch): array
    {
        $allowed=['nombre','dosis','consumo','resultados','descripcion'];
        if ($patch===[] || array_diff(array_keys($patch),$allowed)!==[]) throw new M07CanonicalException('M07_OTHER_PATCH_INVALID',422,'La actualización contiene campos no autorizados.');
        $normalized=[];
        foreach($patch as $field=>$value){ if(!is_scalar($value)) throw new M07CanonicalException('M07_OTHER_PATCH_INVALID',422,'La actualización del medicamento adicional no es válida.'); $value=trim((string)$value); if($value==='') throw new M07CanonicalException('M07_OTHER_PATCH_INVALID',422,'No se puede borrar un dato confirmado mediante una respuesta vacía.'); $normalized[$field]=$value; }
        if(isset($normalized['nombre'])) $normalized['nombre']=$this->validatedOtherName($normalized['nombre']);
        if(isset($normalized['dosis']) && mb_strlen($normalized['dosis'],'UTF-8')>2000) throw new M07CanonicalException('M07_OTHER_PATCH_INVALID',422,'La dosis excede la longitud permitida.');
        if(isset($normalized['descripcion']) && mb_strlen($normalized['descripcion'],'UTF-8')>2000) throw new M07CanonicalException('M07_OTHER_PATCH_INVALID',422,'La descripción excede la longitud permitida.');
        if(isset($normalized['consumo'])&&!in_array($normalized['consumo'],['Si','No'],true)) throw new M07CanonicalException('M07_CONSUMPTION_INVALID',422,'Indica si todavía utilizas el medicamento: Sí o No.');
        if(isset($normalized['resultados'])&&!in_array($normalized['resultados'],['Funciono','No funciono','Tuve efectos adversos'],true)) throw new M07CanonicalException('M07_RESULT_INVALID',422,'El resultado no coincide con las opciones oficiales.');
        return $normalized;
    }

    private function mergeOtherPatch(array $current,array $patch): array
    {
        $next=$current;
        if(isset($patch['nombre'])){ $next['nombre']=$patch['nombre']; $next['nombre_normalizado']=mb_strtolower(preg_replace('/\s+/u',' ',$patch['nombre'])??$patch['nombre'],'UTF-8'); }
        if(isset($patch['dosis'])){ $next['dosis']=$patch['dosis']; $next['dosis_state']=self::CONFIRMED; }
        if(isset($patch['consumo'])){ $next['consumo']=$patch['consumo']; $next['consumo_state']=self::CONFIRMED; }
        if(isset($patch['resultados'])){
            $result=$patch['resultados'];
            if(($current['tolerabilidad_codigo']??null)==='PRESENTO_EFECTOS' && trim((string)($current['descripcion_efectos']??''))!=='' && $result!=='Tuve efectos adversos') throw new M07CanonicalException('M07_OTHER_DEPENDENT_CLEAR_CONFIRMATION_REQUIRED',409,'La corrección eliminaría una descripción de efectos adversos y requiere confirmación explícita.');
            $next['eficacia_codigo']=match($result){'Funciono'=>'FUNCIONO','No funciono'=>'NO_FUNCIONO',default=>null};
            $next['eficacia_state']=$result==='Tuve efectos adversos'?self::NOT_APPLICABLE:self::CONFIRMED;
            $next['tolerabilidad_codigo']=$result==='Tuve efectos adversos'?'PRESENTO_EFECTOS':null;
            $next['tolerabilidad_state']=$result==='Tuve efectos adversos'?self::CONFIRMED:self::NOT_APPLICABLE;
            if($result!=='Tuve efectos adversos'){ $next['descripcion_efectos']=null; $next['descripcion_efectos_state']=self::NOT_APPLICABLE; }
            elseif(trim((string)($next['descripcion_efectos']??''))===''){ $next['descripcion_efectos_state']=self::UNANSWERED; }
        }
        if(isset($patch['descripcion'])){
            if(($next['tolerabilidad_codigo']??null)!=='PRESENTO_EFECTOS') throw new M07CanonicalException('M07_DESCRIPTION_NOT_APPLICABLE',422,'La descripción solo corresponde a un resultado de efectos adversos.');
            $next['descripcion_efectos']=$patch['descripcion']; $next['descripcion_efectos_state']=self::CONFIRMED;
        }
        return $next;
    }

    private function otherPatchMatches(array $record,array $patch): bool
    {
        $map=['nombre'=>'name','dosis'=>'fields.dosis','consumo'=>'fields.consumo','resultados'=>'fields.resultados','descripcion'=>'tolerability.description'];
        foreach($patch as $field=>$value){ $actual=match($map[$field]??''){ 'name'=>$record['name']??null,'fields.dosis'=>$record['fields']['dosis']??null,'fields.consumo'=>$record['fields']['consumo']??null,'fields.resultados'=>$record['fields']['resultados']??null,'tolerability.description'=>$record['tolerability']['description']??null,default=>null}; if(trim((string)$actual)!==$value)return false; }
        return true;
    }

    private function lockAuthorizedPatient(int $patientId,int $clinicId): void
    {
        $patient=$this->db->prepare('SELECT id FROM pc_paciente WHERE id=:patient AND id_clinica=:clinic FOR UPDATE');
        $patient->execute([':patient'=>$patientId,':clinic'=>$clinicId]);
        if($patient->fetchColumn()===false) throw new M07CanonicalException('M07_CLINIC_DENIED',403,'El paciente no pertenece a la clínica solicitada.');
    }

    private function insertOtherOperation(string $key,int $patientId,int $clinicId,int $entryId,int $expectedVersion,string $requestHash,string $resultCode,string $action,string $afterHash,int $actorId,?string $beforeHash=null): int
    {
        $operation=$this->db->prepare('INSERT INTO pac_m07_operaciones (idempotency_key,id_paciente,id_clinica,ambito,target_type,target_id,expected_version,request_hash,result_code,action,provenance,before_hash,after_hash,actor_id,reason,created_at) VALUES (:key,:patient,:clinic,:scope,:type,:target,:expected,:request,:result,:action,:provenance,:before_hash,:after_hash,:actor,:reason,NOW())');
        $operation->execute([':key'=>$key,':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07_P09',':type'=>'OTHER',':target'=>$entryId,':expected'=>$expectedVersion,':request'=>$requestHash,':result'=>$resultCode,':action'=>$action,':provenance'=>'INTERVIEW',':before_hash'=>$beforeHash,':after_hash'=>$afterHash,':actor'=>$actorId,':reason'=>null]);
        return (int)$this->db->lastInsertId();
    }

    private function findOperation(int $patientId,string $key): ?array { $s=$this->db->prepare('SELECT * FROM pac_m07_operaciones WHERE id_paciente=:patient AND idempotency_key=:key LIMIT 1'); $s->execute([':patient'=>$patientId,':key'=>$key]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
    private function hashValues(array $row): string { unset($row['m07_updated_at']); return hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    private function assertIdempotencyKey(string $key): void { if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$key)) throw new M07CanonicalException('M07_IDEMPOTENCY_KEY_INVALID',422,'La clave de idempotencia no es válida.'); }
    private function uuidV4(): string { $bytes=random_bytes(16); $bytes[6]=chr((ord($bytes[6])&0x0f)|0x40); $bytes[8]=chr((ord($bytes[8])&0x3f)|0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4)); }
}
