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
        $this->gate = $gate ?? new M07CanonicalActivationGate(false);
    }

    private M07CanonicalActivationGate $gate;

    public function active(): bool
    {
        return $this->gate->allows($this->db) && $this->schemaAvailable();
    }

    /** Read-only unified DTO. It never creates initialized legacy rows. */
    public function read(int $patientId, int $clinicId, string $role): array
    {
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
        $payload = [
            'key' => 'M07',
            'records' => array_map(static fn(M07CanonicalRecord $record): array => $record->toArray(), $records),
            'other_medications' => $other,
            'finalization_enabled' => false,
        ];
        $payload['version'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $payload['pending_items'] = count(array_filter($records, static fn(M07CanonicalRecord $record): bool => $record->status !== 'complete'));
        return $payload;
    }

    /** Alias that makes the shared DTO intent explicit to callers. */
    public function readUnified(int $patientId, int $clinicId, string $role): array
    {
        return $this->read($patientId, $clinicId, $role);
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

        $requestHash = hash('sha256', json_encode([$patientId, $clinicId, $questionId, $patch, $fieldMask, $expectedVersion, $provenance, $draft], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
        try {
            $rows = $this->lockedCatalogRows($patientId, $questionId);
            if (count($rows) > 1) throw new M07CanonicalException('M07_DUPLICATE_RESPONSE', 409, 'Existen respuestas duplicadas; no se modificó el expediente.');
            $current = $rows[0] ?? $this->emptyCatalogValues();
            $currentVersion = $rows === [] ? 0 : (int)($current['m07_version'] ?? 1);
            if ($currentVersion !== $expectedVersion) throw new M07CanonicalException('M07_VERSION_CONFLICT', 409, 'La versión M07 está obsoleta.');
            if ($draft) $this->assertDraftBaseline($current);

            $values = $this->mergePatch($current, $patch, $fieldMask, $draft, $confirmClear);
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
            return ['resultado' => 200, 'code' => $draft ? 'M07_DRAFT_SAVED' : 'M07_CONFIRMED', 'operation_id' => $operationId, 'record' => $this->readOneCatalog($patientId, $clinicId, $role, $questionId)];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($exception instanceof M07CanonicalException) throw $exception;
            throw new M07CanonicalException('M07_ATOMIC_ROLLBACK', 422, 'No fue posible guardar M07; no se aplicaron cambios parciales.', $exception);
        }
    }

    /** Create an independent “Otro” entry; the name is never put in M06. */
    public function createOtherMedication(array $scope, int $themeId, string $name, int $actorId, string $idempotencyKey): array
    {
        $patientId = (int)($scope['patient_id'] ?? 0); $clinicId = (int)($scope['clinic_id'] ?? 0); $role = (string)($scope['role'] ?? '');
        $this->assertScope($patientId, $clinicId, $role, true); $this->requireWrite(); $this->assertIdempotencyKey($idempotencyKey);
        $name = trim($name); if ($name === '' || mb_strlen($name, 'UTF-8') > 255) throw new M07CanonicalException('M07_OTHER_NAME_INVALID', 422, 'El nombre de Otro no es válido.');
        $theme = $this->db->prepare('SELECT id FROM pac_temas_modulo_7 WHERE id=:theme'); $theme->execute([':theme'=>$themeId]); if (!$theme->fetchColumn()) throw new M07CanonicalException('M07_THEME_INVALID', 422, 'La categoría M07 no es válida.');
        $entryKey = $this->uuidV4(); $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? $name, 'UTF-8');
        $hash = hash('sha256', json_encode([$patientId,$clinicId,$themeId,$name,$entryKey], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare('INSERT INTO pac_m07_medicamentos_no_catalogados
                (entry_key,id_paciente,id_clinica,ambito,id_tema,nombre,nombre_normalizado,estado_registro,
                 respuesta_state,dosis_state,consumo_state,eficacia_state,tolerabilidad_state,descripcion_efectos_state,
                 version,provenance,created_at,updated_at,created_by,updated_by)
                VALUES (:entry,:patient,:clinic,:scope,:theme,:name,:normalized,:status,:rs,:ds,:cs,:es,:ts,:efs,1,:prov,NOW(),NOW(),:created_by,:updated_by)');
            $insert->execute([':entry'=>$entryKey,':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07_P09',':theme'=>$themeId,':name'=>$name,':normalized'=>$normalized,':status'=>'ACTIVO',':rs'=>self::UNANSWERED,':ds'=>self::HISTORICAL,':cs'=>self::HISTORICAL,':es'=>self::HISTORICAL,':ts'=>self::HISTORICAL,':efs'=>self::HISTORICAL,':prov'=>'INTERVIEW',':created_by'=>$actorId,':updated_by'=>$actorId]);
            $id = (int)$this->db->lastInsertId();
            $op = $this->db->prepare('INSERT INTO pac_m07_operaciones (idempotency_key,id_paciente,id_clinica,ambito,target_type,target_id,expected_version,request_hash,result_code,action,provenance,after_hash,actor_id,created_at) VALUES (:key,:patient,:clinic,:scope,:type,:id,0,:hash,:result,:action,:prov,:after,:actor,NOW())');
            $op->execute([':key'=>$idempotencyKey,':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07_P09',':type'=>'OTHER',':id'=>$id,':hash'=>$hash,':result'=>'M07_OTHER_CREATED',':action'=>'CREATE_OTHER',':prov'=>'INTERVIEW',':after'=>$hash,':actor'=>$actorId]);
            $opId=(int)$this->db->lastInsertId(); $this->db->commit();
            return ['resultado'=>200,'code'=>'M07_OTHER_CREATED','operation_id'=>$opId,'entry_key'=>$entryKey,'id'=>$id];
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); if ($exception instanceof M07CanonicalException) throw $exception; throw new M07CanonicalException('M07_ATOMIC_ROLLBACK',422,'No fue posible crear el medicamento adicional.',$exception); }
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
            $table=$this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?,?)'); $table->execute(['pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica']);
            if ((int)$table->fetchColumn() !== 3) return false;
            $columns=$this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name IN (' . implode(',', array_fill(0,count(self::CATALOG_COLUMNS),'?')) . ')'); $columns->execute(array_merge(['pac_respuestas_paciente_modulo_7'],self::CATALOG_COLUMNS)); return (int)$columns->fetchColumn()===count(self::CATALOG_COLUMNS);
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

    private function readOther(int $patientId,int $clinicId,string $role): array
    { $s=$this->db->prepare('SELECT * FROM pac_m07_medicamentos_no_catalogados WHERE id_paciente=:patient AND id_clinica=:clinic AND ambito=:scope ORDER BY id'); $s->execute([':patient'=>$patientId,':clinic'=>$clinicId,':scope'=>'M07_P09']); return array_map(static fn(array $r): array=>['source'=>'OTHER','id'=>(int)$r['id'],'patient_id'=>(int)$r['id_paciente'],'clinic_id'=>(int)$r['id_clinica'],'theme_id'=>(int)$r['id_tema'],'question_id'=>null,'name'=>$r['nombre'],'fields'=>['respuesta'=>$r['respuesta'],'dosis'=>$r['dosis'],'consumo'=>$r['consumo']],'efficacy'=>['code'=>$r['eficacia_codigo'],'state'=>$r['eficacia_state']],'tolerability'=>['code'=>$r['tolerabilidad_codigo'],'state'=>$r['tolerabilidad_state'],'description'=>$r['descripcion_efectos'],'description_state'=>$r['descripcion_efectos_state']],'states'=>['respuesta'=>$r['respuesta_state']??self::UNANSWERED,'dosis'=>$r['dosis_state']??self::HISTORICAL,'consumo'=>$r['consumo_state']??self::HISTORICAL,'eficacia'=>$r['eficacia_state']??self::HISTORICAL,'tolerabilidad'=>$r['tolerabilidad_state']??self::HISTORICAL,'efectos_descripcion'=>$r['descripcion_efectos_state']??self::HISTORICAL],'legacy_projection'=>['provenance'=>$r['provenance']],'version'=>(int)$r['version'],'status'=>$r['estado_registro']==='ACTIVO'?'pending':'inactive','pending_reason'=>$r['estado_registro']==='ACTIVO'?'UNANSWERED':null,'entry_key'=>$r['entry_key']],$s->fetchAll(PDO::FETCH_ASSOC)); }

    private function findOperation(int $patientId,string $key): ?array { $s=$this->db->prepare('SELECT * FROM pac_m07_operaciones WHERE id_paciente=:patient AND idempotency_key=:key LIMIT 1'); $s->execute([':patient'=>$patientId,':key'=>$key]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
    private function hashValues(array $row): string { unset($row['m07_updated_at']); return hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    private function assertIdempotencyKey(string $key): void { if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$key)) throw new M07CanonicalException('M07_IDEMPOTENCY_KEY_INVALID',422,'La clave de idempotencia no es válida.'); }
    private function uuidV4(): string { $bytes=random_bytes(16); $bytes[6]=chr((ord($bytes[6])&0x0f)|0x40); $bytes[8]=chr((ord($bytes[8])&0x3f)|0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4)); }
}
