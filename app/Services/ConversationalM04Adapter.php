<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** M04 conversational adapter. Reading and interpretation are safe; writing is gated. */
final class ConversationalM04Adapter
{
    private const NEGATIVE_STATE_TABLE = 'pc_antecedentes_quirurgicos_estado';

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
        $negativeStateAvailable = $this->tableExists($db, self::NEGATIVE_STATE_TABLE);
        $negativeDeclaration = $negativeStateAvailable
            ? $this->readNegativeDeclaration($db, $patientId)
            : null;
        $hasRecordedSurgeries = (bool)array_filter(
            $records,
            static fn(array $record): bool => trim((string)($record['cirugia'] ?? '')) !== ''
        );
        $responseState = ($negativeDeclaration['estado'] ?? null) === 'NEGATIVE_DECLARED'
            ? 'NEGATIVE_DECLARED'
            : ($hasRecordedSurgeries ? 'PROCEDURES_DOCUMENTED' : 'PENDING');
        $versionRows = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'], 'fecha' => (string)($row['fecha'] ?? ''),
            'cirugia' => (string)($row['cirugia'] ?? ''), 'observaciones' => (string)($row['observaciones'] ?? ''),
        ], $records);
        $versionRows['negative_declaration'] = $negativeDeclaration;

        return [
            'key' => 'M04',
            'title' => 'Antecedentes quirúrgicos',
            'records' => $records,
            'has_recorded_surgeries' => $hasRecordedSurgeries,
            'response_state' => $responseState,
            'negative_state_available' => $negativeStateAvailable,
            'negative_declaration' => $negativeDeclaration,
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
        $unknownDate = preg_match('/\b(?:no\s+(?:recuerdo|s[eé])|desconozco)\b[^.!?]{0,80}\b(?:cu[aá]ndo|fecha|año|mes)\b/u', $normalized) === 1;
        $negative = preg_match('/\b(no|nunca|ninguna|ningún|sin cirug[ií]as?)\b/u', $normalized) === 1;
        // "Nunca me han operado" contains the positive token "operado" but
        // is an explicit negative answer. A contradictory sentence such as
        // "No, me operaron de la rodilla" must remain a clarification, not a
        // silent negative.
        $standaloneNegative = preg_match('/^\s*(?:no|nunca|ninguna|ning[uú]n)\s*[.!?]?\s*$/u', $normalized) === 1;
        $explicitNegative = $standaloneNegative || preg_match('/\b(?:no|nunca|ninguna|ningún|sin)\b(?:[^.!?]{0,40})\b(?:operad[oa]s?|operaron|cirug[ií]as?|intervenciones?)\b/u', $normalized) === 1;
        $contradictoryPositive = preg_match('/\b(?:no|nunca)\b(?:[^.!?]{0,25})\bme\s+operaron\b/u', $normalized) === 1;
        $positive = preg_match('/\b(s[ií]|operad[oa]|cirug[ií]a|intervenci[oó]n|operaci[oó]n|apendicectom[ií]a|me operaron)\b/u', $normalized) === 1;
        if ($unknownDate && !$positive) {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification',
                'surgery' => null, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'clarification' => 'Indica si tuviste una cirugía. Si la fecha no se recuerda, se conservará sin inventar día ni mes.',
            ];
        }
        if ($contradictoryPositive && !$unknownDate) {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification',
                'surgery' => null, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'clarification' => 'La respuesta contiene una negación y una afirmación de cirugía. Confirma cuál de las dos respuestas deseas conservar.',
            ];
        }
        if ($explicitNegative && !$contradictoryPositive && !$unknownDate) {
            return [
                'mode' => 'deterministic', 'kind' => 'm04_negative_proposal',
                'surgery' => null, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => true, 'direct_save' => true,
                'semantic_state' => 'NEGATIVE_DECLARED',
                'origin' => 'PATIENT_DECLARATION',
                'message' => 'Respuesta negativa explícita preparada para guardarse sin crear un registro quirúrgico.',
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

        $surgery = $this->extractSurgery($normalized);
        if ($surgery === null || $surgery === '') {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification',
                'surgery' => null, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'clarification' => '¿Qué cirugía te realizaron?',
            ];
        }
        [$date, $temporalObservation, $temporalInvalid] = $this->temporalEvidence($normalized);
        if ($unknownDate) {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification',
                'surgery' => $surgery, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'clarification' => 'No se guardará una fecha que no recuerdas. Indica una fecha exacta, un año/mes aproximado o confirma la cirugía después de aclarar ese dato.',
            ];
        }
        if ($temporalInvalid) {
            return [
                'mode' => 'deterministic', 'kind' => 'clarification',
                'surgery' => $surgery, 'date' => null, 'observations' => null,
                'requires_confirmation' => false, 'can_confirm' => false,
                'clarification' => 'La fecha indicada no es válida. Proporciona una fecha real o confirma que sólo recuerdas el año, el mes o un tiempo aproximado.',
            ];
        }
        $observations = $temporalObservation;
        $proposal = [
            'mode' => 'deterministic', 'kind' => 'm04_proposal',
            'surgery' => $surgery, 'date' => $date, 'observations' => $observations,
            'requires_confirmation' => false, 'can_confirm' => false, 'direct_save' => true,
            'message' => 'Respuesta M04 validada para guardado directo.',
        ];
        if ($date === null && $observations !== null) {
            $proposal['temporal_original'] = $utterance;
        }
        return $proposal;
    }

    public function capture(int $patientId, array $proposal, ?string $expectedVersion): array
    {
        $kind = (string)($proposal['kind'] ?? '');
        if ($patientId < 1 || !in_array($kind, ['m04_proposal', 'm04_negative_proposal'], true)) {
            return ['resultado' => 422, 'mensaje' => 'La propuesta M04 no es válida.'];
        }
        $isNegative = $kind === 'm04_negative_proposal';
        $surgery = trim((string)($proposal['surgery'] ?? ''));
        $date = trim((string)($proposal['date'] ?? ''));
        $observations = array_key_exists('observations', $proposal) && $proposal['observations'] !== null
            ? trim((string)$proposal['observations']) : null;
        $temporalOriginal = trim((string)($proposal['temporal_original'] ?? ''));
        if ($observations !== null && $observations !== '' && $temporalOriginal !== '' && !str_contains($observations, 'Expresión original:')) {
            $observations = rtrim($observations, '.') . '. Expresión original: "' . $temporalOriginal . '".';
        }
        if (!$isNegative && ($surgery === '' || ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)))) {
            return ['resultado' => 422, 'mensaje' => 'La cirugía o la fecha M04 no son válidas.'];
        }
        $db = $this->db ?: Database::getInstance();
        $lockName = 'clinica_m04_capture_' . $patientId;
        $isMysql = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
        $lockAcquired = false;
        if ($isMysql) {
            $lock = $db->prepare('SELECT GET_LOCK(:name, 5)');
            $lock->execute([':name' => $lockName]);
            $lockAcquired = (int)$lock->fetchColumn() === 1;
            if (!$lockAcquired) return ['resultado' => 409, 'mensaje' => 'No fue posible confirmar M04; inténtalo de nuevo.'];
        }
        try {
            $state = $this->read($patientId);
            if (!$state['write_enabled']) return ['resultado' => 409, 'mensaje' => 'M04 está finalizado o su estado editable no está disponible.'];
            if ($expectedVersion !== null && !hash_equals((string)$state['version'], $expectedVersion)) {
                return ['resultado' => 409, 'mensaje' => 'M04 cambió mientras se revisaba. Recarga antes de confirmar.'];
            }

            if ($isNegative) {
                if (empty($state['negative_state_available'])) {
                    return ['resultado' => 409, 'codigo' => 'M04_NEGATIVE_STATE_SCHEMA_REQUIRED', 'mensaje' => 'M04 aún no tiene disponible el estado durable para registrar una respuesta negativa. La pregunta permanece pendiente.'];
                }
                if (!empty($state['has_recorded_surgeries'])) {
                    return ['resultado' => 409, 'codigo' => 'M04_NEGATIVE_CONFLICTING_SURGERY_RECORDS', 'mensaje' => 'M04 tiene cirugías registradas. No se puede confirmar «No» sin una rectificación autorizada.'];
                }
                $clinicId = $this->patientClinicId($db, $patientId);
                if ($clinicId === null) {
                    return ['resultado' => 409, 'codigo' => 'M04_PATIENT_SCOPE_UNAVAILABLE', 'mensaje' => 'No fue posible comprobar la clínica autorizada para M04.'];
                }
                $currentDeclaration = is_array($state['negative_declaration'] ?? null) ? $state['negative_declaration'] : null;
                if (($currentDeclaration['estado'] ?? null) === 'NEGATIVE_DECLARED') {
                    $readback = $this->read($patientId);
                    return [
                        'resultado' => 200,
                        'mensaje' => 'La declaración negativa M04 ya estaba guardada.',
                        'negative_declaration' => $readback['negative_declaration'],
                        'version' => $readback['version'],
                        'readback' => true,
                    ];
                }
                $db->beginTransaction();
                $now = gmdate('Y-m-d H:i:s');
                if ($currentDeclaration !== null) {
                    $update = $db->prepare(
                        'UPDATE ' . self::NEGATIVE_STATE_TABLE . '
                         SET id_clinica=:clinic, estado=:state, origen=:origin, version=version+1,
                             actualizado_en=:updated
                         WHERE id_paciente=:patient'
                    );
                    $update->execute([
                        ':clinic' => $clinicId, ':state' => 'NEGATIVE_DECLARED',
                        ':origin' => 'PATIENT_DECLARATION', ':updated' => $now, ':patient' => $patientId,
                    ]);
                } else {
                    $insert = $db->prepare(
                        'INSERT INTO ' . self::NEGATIVE_STATE_TABLE . '
                         (id_paciente, id_clinica, estado, origen, version, declarado_en, actualizado_en)
                         VALUES (:patient, :clinic, :state, :origin, 1, :declared, :updated)'
                    );
                    $insert->execute([
                        ':patient' => $patientId, ':clinic' => $clinicId,
                        ':state' => 'NEGATIVE_DECLARED', ':origin' => 'PATIENT_DECLARATION',
                        ':declared' => $now, ':updated' => $now,
                    ]);
                }
                $db->commit();
                $readback = $this->read($patientId);
                if (($readback['negative_declaration']['estado'] ?? null) !== 'NEGATIVE_DECLARED') {
                    throw new \RuntimeException('M04 negative readback failed');
                }
                return [
                    'resultado' => 200,
                    'mensaje' => 'Declaración negativa M04 guardada.',
                    'negative_declaration' => $readback['negative_declaration'],
                    'version' => $readback['version'],
                    'readback' => true,
                ];
            }

            foreach ($state['records'] as $existingRecord) {
                $existingSurgery = trim((string)($existingRecord['cirugia'] ?? ''));
                $existingDate = trim((string)($existingRecord['fecha'] ?? ''));
                $existingObservations = trim((string)($existingRecord['observaciones'] ?? ''));
                if ($existingSurgery === $surgery
                    && $existingDate === $date
                    && $existingObservations === (string)($observations ?? '')) {
                    return [
                        'resultado' => 409,
                        'codigo' => 'M04_DUPLICATE_SURGERY',
                        'mensaje' => 'La misma cirugía ya está registrada para este paciente.',
                    ];
                }
            }

            $model = new \App\Models\AntecedentesQuirurgicos($db);
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
            if (is_array($state['negative_declaration'] ?? null)
                && ($state['negative_declaration']['estado'] ?? null) === 'NEGATIVE_DECLARED') {
                $now = gmdate('Y-m-d H:i:s');
                $supersede = $db->prepare(
                    'UPDATE ' . self::NEGATIVE_STATE_TABLE . '
                     SET estado=:state, origen=:origin, version=version+1, actualizado_en=:updated
                     WHERE id_paciente=:patient AND estado=:negative'
                );
                $supersede->execute([
                    ':state' => 'SUPERSEDED_BY_SURGERY', ':origin' => 'PATIENT_CORRECTION',
                    ':updated' => $now, ':patient' => $patientId, ':negative' => 'NEGATIVE_DECLARED',
                ]);
            }
            $db->commit();
            $readback = $this->read($patientId);
            return ['resultado'=>200, 'mensaje'=>'Antecedente quirúrgico M04 guardado.', 'record'=>$this->findRecord($patientId, $rowId), 'version'=>$readback['version'], 'readback'=>true];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado'=>422, 'mensaje'=>'No fue posible guardar el antecedente quirúrgico M04.'];
        } finally {
            if ($isMysql && $lockAcquired) {
                $release = $db->prepare('SELECT RELEASE_LOCK(:name)');
                $release->execute([':name'=>$lockName]);
            }
        }
    }

    /**
     * Finishes M04 only after an explicit patient intent.  The persisted
     * state, rather than the button label or a client-side counter, decides
     * whether this is the end of additional surgeries or the negative history
     * declaration for a patient with no surgery records.
     */
    public function finish(int $patientId, ?string $expectedVersion): array
    {
        if ($patientId < 1) {
            return ['resultado' => 422, 'mensaje' => 'Paciente M04 no válido.'];
        }

        $state = $this->read($patientId);
        if (empty($state['write_enabled'])) {
            return ['resultado' => 409, 'mensaje' => 'M04 está finalizado o su estado editable no está disponible.'];
        }
        if ($expectedVersion !== null && !hash_equals((string)$state['version'], $expectedVersion)) {
            return ['resultado' => 409, 'mensaje' => 'M04 cambió mientras se revisaba. Recarga antes de confirmar.'];
        }

        if (!empty($state['has_recorded_surgeries'])) {
            return [
                'resultado' => 200,
                'mensaje' => 'M04 listo para finalizar después de registrar las cirugías.',
                'version' => $state['version'],
                'readback' => true,
                'request_finalization' => true,
                'finish_intent' => 'FINISH_ADDITIONAL_SURGERIES',
            ];
        }

        if (($state['negative_declaration']['estado'] ?? null) === 'NEGATIVE_DECLARED') {
            return [
                'resultado' => 200,
                'mensaje' => 'La declaración negativa M04 está lista para finalizar.',
                'negative_declaration' => $state['negative_declaration'],
                'version' => $state['version'],
                'readback' => true,
                'request_finalization' => true,
                'finish_intent' => 'NEGATIVE_HISTORY',
            ];
        }

        $negative = $this->capture($patientId, [
            'kind' => 'm04_negative_proposal',
            'semantic_state' => 'NEGATIVE_DECLARED',
            'origin' => 'PATIENT_DECLARATION',
        ], $expectedVersion);
        if ((int)($negative['resultado'] ?? 422) !== 200) {
            return $negative;
        }
        return array_merge($negative, [
            'request_finalization' => true,
            'finish_intent' => 'NEGATIVE_HISTORY',
        ]);
    }

    private function tableExists(PDO $db, string $table): bool
    {
        try {
            $driver = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'sqlite') {
                $statement = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:table");
            } else {
                $statement = $db->prepare(
                    'SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = :table'
                );
            }
            $statement->execute([':table' => $table]);
            return (int)$statement->fetchColumn() === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function readNegativeDeclaration(PDO $db, int $patientId): ?array
    {
        try {
            $statement = $db->prepare(
                'SELECT s.id, s.id_paciente, s.id_clinica, s.estado, s.origen,
                        s.version, s.declarado_en, s.actualizado_en
                 FROM ' . self::NEGATIVE_STATE_TABLE . ' s
                 INNER JOIN pc_paciente p ON p.id = s.id_paciente AND p.id_clinica = s.id_clinica
                 WHERE s.id_paciente = :patient
                 ORDER BY s.id DESC
                 LIMIT 1'
            );
            $statement->execute([':patient' => $patientId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) return null;
            return [
                'id' => (int)$row['id'], 'id_paciente' => (int)$row['id_paciente'],
                'id_clinica' => (int)$row['id_clinica'], 'estado' => (string)$row['estado'],
                'origen' => (string)$row['origen'], 'version' => (int)$row['version'],
                'declarado_en' => (string)($row['declarado_en'] ?? ''),
                'actualizado_en' => (string)($row['actualizado_en'] ?? ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function patientClinicId(PDO $db, int $patientId): ?int
    {
        try {
            $statement = $db->prepare('SELECT id_clinica FROM pc_paciente WHERE id=:patient LIMIT 1');
            $statement->execute([':patient' => $patientId]);
            $clinicId = $statement->fetchColumn();
            return $clinicId === false ? null : (int)$clinicId;
        } catch (\Throwable) {
            return null;
        }
    }

    private function findRecord(int $patientId, int $rowId): array
    {
        foreach ($this->read($patientId)['records'] as $record) if ((int)$record['id'] === $rowId) return $record;
        throw new \RuntimeException('M04_RECORD_NOT_FOUND');
    }

    /**
     * Extracts only the surgery name. Temporal clauses are deliberately
     * excluded so that "rodilla en 2019" is never persisted as the surgery.
     */
    private function extractSurgery(string $normalized): ?string
    {
        $patterns = [
            '/\b(?:me\s+)?operaron\s+(?:de|por|para)\s+(.+?)(?=\s+(?:en|el|durante|hace|desde\s+hace|pero\s+no|y\s+no)\b|[,.!?]|$)/u',
            '/\b(?:me\s+)?operaron\s+(.+?)(?=\s+(?:en|el|durante|hace|desde\s+hace|pero\s+no|y\s+no)\b|[,.!?]|$)/u',
            '/\b(?:cirug[ií]a|intervenci[oó]n|operaci[oó]n)\s+(?:de|por|para)\s+(.+?)(?=\s+(?:en|el|durante|hace|desde\s+hace|pero\s+no|y\s+no)\b|[,.!?]|$)/u',
            '/\b(?:me\s+)?hicieron\s+(?:una\s+|un\s+)?(.+?)(?=\s+(?:en|el|durante|hace|desde\s+hace|pero\s+no|y\s+no)\b|[,.!?]|$)/u',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $normalized, $match)) continue;
            $candidate = trim($match[1]);
            if ($candidate === '' || preg_match('/^(?:en|el|durante|hace|desde)\b/u', $candidate)) return null;
            return $candidate;
        }
        return null;
    }

    /**
     * Keeps the precision expressed by the patient. The legacy DATE column
     * accepts an exact date or NULL; partial/relative dates therefore remain
     * in the existing observations field instead of being rounded to day 1.
     */
    private function temporalEvidence(string $normalized): array
    {
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
        ];
        if (preg_match('/\b(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\s+de\s+((?:19|20)\d{2})\b/u', $normalized, $match)) {
            $day = (int)$match[1];
            $month = $months[$match[2]];
            $year = (int)$match[3];
            if (checkdate($month, $day, $year)) return [sprintf('%04d-%02d-%02d', $year, $month, $day), null, false];
            return [null, null, true];
        }
        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-]((?:19|20)\d{2})\b/u', $normalized, $match)) {
            $day = (int)$match[1];
            $month = (int)$match[2];
            $year = (int)$match[3];
            if (checkdate($month, $day, $year)) return [sprintf('%04d-%02d-%02d', $year, $month, $day), null, false];
            return [null, null, true];
        }
        if (preg_match('/\b((?:19|20)\d{2})-(\d{1,2})-(\d{1,2})\b/u', $normalized, $match)) {
            $year = (int)$match[1];
            $month = (int)$match[2];
            $day = (int)$match[3];
            if (checkdate($month, $day, $year)) return [sprintf('%04d-%02d-%02d', $year, $month, $day), null, false];
            return [null, null, true];
        }
        if (preg_match('/\b(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\s+de\s+((?:19|20)\d{2})\b/u', $normalized, $match)) {
            return [null, 'Mes y año referidos: ' . $match[1] . ' de ' . $match[2], false];
        }
        if (preg_match('/\bhace\s+([^,.!?]+)/u', $normalized, $match)) {
            return [null, 'Tiempo referido: ' . trim($match[1]), false];
        }
        if (preg_match('/\b((?:19|20)\d{2})\b/u', $normalized, $match)) {
            return [null, 'Año referido: ' . $match[1], false];
        }
        return [null, null, false];
    }
}
