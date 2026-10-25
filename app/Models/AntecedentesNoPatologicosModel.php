<?php
namespace App\Models;
use App\Config\Database;
use App\Services\ConversationalInterviewService;
use App\Services\M03TobaccoResponsePolicy;

class AntecedentesNoPatologicosModel{
private $bd;
private ?\Closure $diagnostic = null;
private bool $diagnosticFailure = false;
private ?array $m03SemanticColumns = null;

public const M03_Q5_NOT_STOPPED_DETAIL = 'No he dejado de fumar';

private const M03_SEMANTIC_STATES = ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'];

    private static function isM03Q5NotStoppedResponse(int $questionId, int $themeId, string $detail, string $state): bool
    {
        return $questionId === 5
            && $themeId === 1
            && $detail === self::M03_Q5_NOT_STOPPED_DETAIL
            && strtoupper(trim($state)) === 'NEGATIVE';
    }

    /**
     * Canonical textual fallback for installations that only have the legacy
     * respuesta column. These are the only uncertainty values that may be
     * persisted without the additive semantic columns.
     */
    public static function canonicalM03UnknownValue(string $state): ?string
    {
    return match (strtoupper(trim($state))) {
        'UNKNOWN' => 'No lo sé',
        'NOT_REMEMBERED' => 'No recuerdo',
        default => null,
    };
    }

    /**
     * Recognizes only the exact canonical values on readback. Arbitrary
     * historical free text is never reclassified as an uncertainty state.
     */
    public static function canonicalM03UnknownState(string $value): ?string
    {
    $value = trim($value);
    return match ($value) {
        'No lo sé' => 'UNKNOWN',
        'No recuerdo' => 'NOT_REMEMBERED',
        default => null,
    };
    }

public function __construct(?\PDO $db = null, ?callable $diagnostic = null){
$this->bd = $db ?: Database::getInstance();
$this->diagnostic = $diagnostic === null ? null : \Closure::fromCallable($diagnostic);
}

/** Optional stage tracing for a guarded synthetic service diagnostic. */
private function traceM03(string $event): void
{
    if ($this->diagnostic === null) return;
    try {
        ($this->diagnostic)($event);
    } catch (\Throwable) {
        // Diagnostic output must never change the clinical persistence result.
        $this->diagnosticFailure = true;
    }
}

    private function obtenerContextoRespuesta($idPaciente, $idRespuesta, $idTema): ?array
    {
    $sql = "SELECT r.id, r.id_pregunta, r.respuesta, p.tipo
            FROM pac_respuestas_paciente_modulo_3 r
            INNER JOIN pac_preguntas_modulo_3 p ON p.id = r.id_pregunta
            WHERE r.id = :id_respuesta
              AND r.id_paciente = :id_paciente
              AND p.id_tema = :id_tema
            LIMIT 1";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([
    ':id_respuesta' => (int)$idRespuesta,
    ':id_paciente' => (int)$idPaciente,
    ':id_tema' => (int)$idTema
    ]);

    $contexto = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $contexto ?: null;
    }

    private function respuestaPerteneceAlContexto($idPaciente, $idRespuesta, $idTema): bool
    {
    return $this->obtenerContextoRespuesta($idPaciente, $idRespuesta, $idTema) !== null;
    }

    /**
     * Returns the additive M03 columns visible to this connection. The
     * application remains compatible with the pre-migration schema and never
     * attempts a write merely to detect capability.
     */
    public function m03SemanticColumns(): array
    {
    if ($this->m03SemanticColumns !== null) return $this->m03SemanticColumns;
    if (!isset($this->bd)) return $this->m03SemanticColumns = [];
    try {
    $stmt = $this->bd->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pac_respuestas_paciente_modulo_3'");
    $columns = array_map(static fn(array $row): string => (string)$row['column_name'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    $this->m03SemanticColumns = array_fill_keys($columns, true);
    } catch (\Throwable) {
    try {
    $stmt = $this->bd->query('PRAGMA table_info(pac_respuestas_paciente_modulo_3)');
    $columns = array_map(static fn(array $row): string => (string)($row['name'] ?? ''), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    $this->m03SemanticColumns = array_fill_keys(array_filter($columns), true);
    } catch (\Throwable) {
    $this->m03SemanticColumns = [];
    }
    }
    return $this->m03SemanticColumns;
    }

    public function m03SemanticStateAvailable(): bool
    {
    $columns = $this->m03SemanticColumns();
    return isset($columns['estado_respuesta'], $columns['origen_respuesta'], $columns['estado_respuesta_at']);
    }

    /** Returns a safe capacity error code, or null when q1 detail fits the active schema. */
    private function m03TobaccoDetailStorageError(string $detail): ?string
    {
    if (mb_strlen($detail, 'UTF-8') > M03TobaccoResponsePolicy::MAX_RESPONSE_LENGTH) return 'M03_TOBACCO_DETAIL_TOO_LONG';
    try {
        $driver = (string)$this->bd->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->bd->prepare("SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS max_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pac_respuestas_paciente_modulo_3' AND column_name = 'respuesta' LIMIT 1");
            $stmt->execute();
            $column = $stmt->fetch(\PDO::FETCH_ASSOC);
            $type = strtolower((string)($column['data_type'] ?? ''));
            $capacity = isset($column['max_length']) ? (int)$column['max_length'] : 0;
            if (!in_array($type, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], true) || $capacity < 1) {
                return 'M03_TOBACCO_DETAIL_SCHEMA_UNVERIFIED';
            }
        } elseif ($driver === 'sqlite') {
            $stmt = $this->bd->query('PRAGMA table_info(pac_respuestas_paciente_modulo_3)');
            $column = null;
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $candidate) {
                if ((string)($candidate['name'] ?? '') === 'respuesta') { $column = $candidate; break; }
            }
            if (!is_array($column)) return 'M03_TOBACCO_DETAIL_SCHEMA_UNVERIFIED';
            $type = strtolower(trim((string)($column['type'] ?? '')));
            if (preg_match('/^(?:text|tinytext|mediumtext|longtext)$/', $type) === 1) {
                $capacity = M03TobaccoResponsePolicy::MAX_RESPONSE_LENGTH;
            } elseif (preg_match('/^(?:varchar|char)\s*\(\s*(\d+)\s*\)$/', $type, $match) === 1) {
                $capacity = (int)$match[1];
            } else {
                return 'M03_TOBACCO_DETAIL_SCHEMA_UNVERIFIED';
            }
        } else {
            return 'M03_TOBACCO_DETAIL_SCHEMA_UNVERIFIED';
        }
        return mb_strlen($detail, 'UTF-8') <= $capacity ? null : 'M03_TOBACCO_DETAIL_COLUMN_TOO_SMALL';
    } catch (\Throwable) {
        return 'M03_TOBACCO_DETAIL_SCHEMA_UNVERIFIED';
    }
    }

    /** q1 uses an atomic, patient-scoped upsert and never clears dependent rows. */
    private function persistM03TobaccoResponse(int $patientId, int $questionId, int $themeId, string $detail, string $state, string $origin, ?string $expected, ?int $expectedResponseId, bool $semanticColumns): array
    {
    $this->traceM03('model.persist.begin');
    $driver = (string)$this->bd->getAttribute(\PDO::ATTR_DRIVER_NAME);
    $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';
    try {
        $this->traceM03('model.persist.transaction.begin');
        $this->bd->beginTransaction();
        $this->traceM03('model.persist.transaction.started');
        $patientSql = 'SELECT id FROM pc_paciente WHERE id = :patient LIMIT 1' . $lock;
        $this->traceM03('model.persist.patient_lock.begin');
        $patient = $this->bd->prepare($patientSql);
        $patient->execute([':patient' => $patientId]);
        $this->traceM03('model.persist.patient_lock.end');
        if ($patient->fetchColumn() === false) {
            $this->bd->rollBack();
            return ['resultado' => 404, 'mensaje' => 'El paciente autorizado no está disponible.', 'code' => 'M03_PATIENT_NOT_FOUND', 'persist' => false];
        }

        $this->traceM03('model.persist.question_lookup.begin');
        $question = $this->bd->prepare('SELECT id, id_tema, tipo FROM pac_preguntas_modulo_3 WHERE id = :question AND id_tema = :theme LIMIT 1');
        $question->execute([':question' => $questionId, ':theme' => $themeId]);
        $questionData = $question->fetch(\PDO::FETCH_ASSOC);
        $this->traceM03('model.persist.question_lookup.end');
        if (!$questionData || !M03TobaccoResponsePolicy::isQuestion($questionData)) {
            $this->bd->rollBack();
            return ['resultado' => 404, 'mensaje' => 'La pregunta no pertenece al tema autorizado.', 'code' => 'M03_TOBACCO_QUESTION_INVALID', 'persist' => false];
        }

        $this->traceM03('model.persist.response_lock.begin');
        $lookup = $this->bd->prepare('SELECT id, respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = :patient AND id_pregunta = :question ORDER BY id ASC LIMIT 2' . $lock);
        $lookup->execute([':patient' => $patientId, ':question' => $questionId]);
        $rows = $lookup->fetchAll(\PDO::FETCH_ASSOC);
        $this->traceM03('model.persist.response_lock.end');
        if (count($rows) > 1) {
            $this->bd->rollBack();
            return ['resultado' => 409, 'mensaje' => 'Existen respuestas duplicadas; se requiere revisión autorizada.', 'code' => 'M03_DUPLICATE_RESPONSE_ROWS', 'persist' => false];
        }
        $current = $rows[0] ?? null;
        $currentValue = (string)($current['respuesta'] ?? '');
        if ($expectedResponseId !== null
            && ($current === null || (int)($current['id'] ?? 0) !== $expectedResponseId)) {
            $this->bd->rollBack();
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. Recarga la pregunta antes de continuar.', 'code' => 'M03_RESPONSE_ID_CONFLICT', 'persist' => false];
        }
        if ($expected !== null && $currentValue !== $expected) {
            $this->bd->rollBack();
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Recarga la pregunta antes de continuar.', 'code' => 'M03_VERSION_CONFLICT', 'persist' => false];
        }
        if ($state === 'NEGATIVE') {
            $dependent = $this->bd->prepare("SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = :patient AND id_pregunta IN (SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema = :theme AND id <> :question) AND TRIM(COALESCE(respuesta, '')) <> ''");
            $dependent->execute([':patient' => $patientId, ':theme' => $themeId, ':question' => $questionId]);
            if ((int)$dependent->fetchColumn() > 0) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'Hay respuestas dependientes que deben conservarse; la negativa no se guardó.', 'code' => 'M03_DEPENDENCY_REVIEW_REQUIRED', 'persist' => false];
            }
        }

        $storedDetail = $semanticColumns && in_array($state, ['UNKNOWN', 'NOT_REMEMBERED'], true) ? '' : $detail;
        if ($current === null) {
            if ($expected !== null && $expected !== '') {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Recarga la pregunta antes de continuar.', 'code' => 'M03_VERSION_CONFLICT', 'persist' => false];
            }
            $this->traceM03('model.persist.insert.begin');
            if ($semanticColumns) {
                $stmt = $this->bd->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta, estado_respuesta, origen_respuesta, estado_respuesta_at) VALUES (:question, :patient, :detail, :state, :origin, :state_at)');
                $stmt->execute([':question' => $questionId, ':patient' => $patientId, ':detail' => $storedDetail, ':state' => $state, ':origin' => $origin, ':state_at' => gmdate('Y-m-d H:i:s')]);
            } else {
                $stmt = $this->bd->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta) VALUES (:question, :patient, :detail)');
                $stmt->execute([':question' => $questionId, ':patient' => $patientId, ':detail' => $storedDetail]);
            }
            $responseId = (int)$this->bd->lastInsertId();
            $this->traceM03('model.persist.insert.end');
        } else {
            $responseId = (int)$current['id'];
            $this->traceM03('model.persist.update.begin');
            if ($semanticColumns) {
                $stmt = $this->bd->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = :detail, estado_respuesta = :state, origen_respuesta = :origin, estado_respuesta_at = :state_at WHERE id = :id AND id_paciente = :patient AND id_pregunta = :question');
                $stmt->execute([':detail' => $storedDetail, ':state' => $state, ':origin' => $origin, ':state_at' => gmdate('Y-m-d H:i:s'), ':id' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            } else {
                $stmt = $this->bd->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = :detail WHERE id = :id AND id_paciente = :patient AND id_pregunta = :question');
                $stmt->execute([':detail' => $storedDetail, ':id' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            }
            if ($stmt->rowCount() > 1) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta no pudo actualizarse de forma inequívoca.', 'code' => 'M03_TOBACCO_UPDATE_AMBIGUOUS', 'persist' => false];
            }
            $this->traceM03('model.persist.update.end');
        }

        $this->traceM03('model.persist.readback.begin');
        $readbackSql = 'SELECT respuesta' . ($semanticColumns ? ', estado_respuesta, origen_respuesta' : '') . ' FROM pac_respuestas_paciente_modulo_3 WHERE id = :id AND id_paciente = :patient AND id_pregunta = :question LIMIT 1';
        $readbackStmt = $this->bd->prepare($readbackSql);
        $readbackStmt->execute([':id' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
        $readback = $readbackStmt->fetch(\PDO::FETCH_ASSOC);
        $this->traceM03('model.persist.readback.end');
        if (!is_array($readback)
            || (string)$readback['respuesta'] !== $storedDetail
            || ($semanticColumns && ((string)$readback['estado_respuesta'] !== $state || (string)$readback['origen_respuesta'] !== $origin))) {
            $this->bd->rollBack();
            return ['resultado' => 500, 'mensaje' => 'No fue posible verificar la respuesta guardada.', 'code' => 'M03_READBACK_FAILED', 'persist' => false];
        }
        $this->traceM03('model.persist.commit.begin');
        $this->bd->commit();
        $this->traceM03('model.persist.commit.end');
        return ['resultado' => 200, 'mensaje' => 'Respuesta de tabaquismo guardada.', 'persist' => true, 'semantic_state' => $state, 'readback' => false];
    } catch (\Throwable) {
        $this->traceM03('model.persist.exception');
        if ($this->bd->inTransaction()) $this->bd->rollBack();
        return ['resultado' => 500, 'mensaje' => 'No fue posible guardar la respuesta de tabaquismo.', 'code' => 'M03_TOBACCO_PERSISTENCE_FAILED', 'persist' => false];
    }
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerModulos(){
    $stmt = $this->bd->query("SELECT id, tema FROM pac_temas_modulo_3 ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    // Se indexa el arreglo por id para facilitar el acceso
    $modulos[$row['id']] = $row['tema'];
    }
    return $modulos;
    }

    
    public function obtenerNameModulos($idTema){
    $stmt = $this->bd->prepare("SELECT tema FROM pac_temas_modulo_3 WHERE id = :id_tema ORDER BY id ASC");
    $stmt->execute([':id_tema' => (int)$idTema]);
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $tema = $row['tema'];
    }
    return $tema;
    }

    /**
     * Reads the shared M03 catalog without creating response rows.
     * The conversational screen must not persist anything before confirmation.
     */
    public function obtenerPreguntasEntrevista(int $idPaciente, int $idTema = 1): array
    {
    $semantic = $this->m03SemanticColumns();
    $stateSelect = isset($semantic['estado_respuesta']) ? 'r.estado_respuesta' : 'NULL';
    $originSelect = isset($semantic['origen_respuesta']) ? 'r.origen_respuesta' : 'NULL';
    $stateAtSelect = isset($semantic['estado_respuesta_at']) ? 'r.estado_respuesta_at' : 'NULL';
    $sql = "SELECT p.id AS idPregunta, p.id_tema AS idTema, p.pregunta, p.tipo,
                   r.id AS idRespuesta, COALESCE(r.respuesta, '') AS respuesta,
                   {$stateSelect} AS estadoRespuesta,
                   {$originSelect} AS origenRespuesta,
                   {$stateAtSelect} AS estadoRespuestaAt
            FROM pac_preguntas_modulo_3 p
            LEFT JOIN pac_respuestas_paciente_modulo_3 r
              ON r.id_pregunta = p.id AND r.id_paciente = :id_paciente";
    $parameters = [':id_paciente' => $idPaciente];
    if ($idTema > 0) {
        $sql .= ' WHERE p.id_tema = :id_tema';
        $parameters[':id_tema'] = $idTema;
    }
    $sql .= ' ORDER BY p.id ASC';
    $stmt = $this->bd->prepare($sql);
    $stmt->execute($parameters);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Confirms and persists one conversational answer through the existing M03 model.
     * A missing response row is created only after the user has confirmed the answer.
     */
    public function capturarRespuestaEntrevista(array $data): array
    {
    $this->traceM03('model.capture.begin');
    $idPaciente = (int)($data['idPaciente'] ?? 0);
    $idPregunta = (int)($data['idPregunta'] ?? 0);
    $idTema = (int)($data['idTema'] ?? 0);
    $detalle = isset($data['detalle']) && is_scalar($data['detalle']) ? trim((string)$data['detalle']) : '';
    $semanticState = strtoupper(trim((string)($data['estadoRespuesta'] ?? '')));
    $origin = strtoupper(trim((string)($data['origenRespuesta'] ?? 'PATIENT_DECLARATION')));
    $expected = array_key_exists('expectedRespuesta', $data) && is_scalar($data['expectedRespuesta'])
        ? (string)$data['expectedRespuesta']
        : null;
    $expectedResponseId = filter_var($data['expectedResponseId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $expectedResponseId = $expectedResponseId === false ? null : (int)$expectedResponseId;

    if ($idPaciente < 1 || $idPregunta < 1 || $idTema < 1) {
        return ['resultado' => 400, 'mensaje' => 'Datos de respuesta no válidos.'];
    }

    $this->traceM03('model.capture.question_lookup.begin');
    $question = $this->bd->prepare(
        'SELECT id, id_tema, tipo FROM pac_preguntas_modulo_3 WHERE id = :id AND id_tema = :tema LIMIT 1'
    );
    $question->execute([':id' => $idPregunta, ':tema' => $idTema]);
    $questionData = $question->fetch(\PDO::FETCH_ASSOC);
    $this->traceM03('model.capture.question_lookup.end');
    if (!$questionData) {
        return ['resultado' => 404, 'mensaje' => 'La pregunta no pertenece al tema autorizado.'];
    }
    $this->traceM03('model.capture.semantic_schema.begin');
    $semanticStateAvailable = $this->m03SemanticStateAvailable();
    $this->traceM03('model.capture.semantic_schema.end');
    $isTobaccoQuestion = M03TobaccoResponsePolicy::isQuestion($questionData + ['idPregunta' => $idPregunta, 'idTema' => $idTema]);
    $tobaccoDecision = null;
    if ($isTobaccoQuestion) {
        $tobaccoDecision = $detalle === '' && in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true)
            ? $semanticState
            : M03TobaccoResponsePolicy::classify($detalle);
        $decisionState = M03TobaccoResponsePolicy::semanticState($tobaccoDecision);
        if ($decisionState === null) {
            return ['resultado' => 422, 'mensaje' => 'Necesito una respuesta clara de sí, no, no lo sé o no lo recuerdo. No se guardó ningún cambio.', 'code' => 'M03_TOBACCO_CLARIFICATION_REQUIRED', 'persist' => false];
        }
        if ($semanticState !== '' && $semanticState !== $decisionState) {
            return ['resultado' => 422, 'mensaje' => 'La respuesta no coincide con su estado semántico. No se guardó ningún cambio.', 'code' => 'M03_TOBACCO_STATE_MISMATCH', 'persist' => false];
        }
        $semanticState = $decisionState;
        if (in_array($tobaccoDecision, [M03TobaccoResponsePolicy::UNKNOWN, M03TobaccoResponsePolicy::NOT_REMEMBERED], true)) {
            $detalle = $semanticStateAvailable ? '' : M03TobaccoResponsePolicy::storedValue($detalle);
        } else {
            $detalle = M03TobaccoResponsePolicy::storedValue($detalle);
            if ($tobaccoDecision === M03TobaccoResponsePolicy::AFFIRMATIVE && !M03TobaccoResponsePolicy::isSimpleAffirmative($detalle)) {
                $storageError = $this->m03TobaccoDetailStorageError($detalle);
                if ($storageError !== null) return ['resultado' => 409, 'mensaje' => 'El almacenamiento actual no permite verificar que los detalles de la respuesta caben con seguridad. No se guardó ningún cambio.', 'code' => $storageError, 'persist' => false];
            }
        }
    }
    $detectedUnknownState = $isTobaccoQuestion
        ? (in_array($tobaccoDecision, [M03TobaccoResponsePolicy::UNKNOWN, M03TobaccoResponsePolicy::NOT_REMEMBERED], true) ? $tobaccoDecision : null)
        : ConversationalInterviewService::classifyM03Unknown($detalle);
    if (!$isTobaccoQuestion && $semanticState === '' && $detectedUnknownState !== null) {
        $semanticState = $detectedUnknownState;
    }
    if ($questionData['tipo'] === 'select' && !in_array($detalle, ['Si', 'No'], true)) {
        if (!$isTobaccoQuestion && $semanticState !== 'UNKNOWN' && $semanticState !== 'NOT_REMEMBERED') {
            return ['resultado' => 400, 'mensaje' => 'La opción seleccionada no es válida.'];
        }
    }
    if ($semanticState === '') {
        $semanticState = ($questionData['tipo'] === 'select' && $detalle === 'No') ? 'NEGATIVE' : 'KNOWN';
    }
    if (!in_array($semanticState, self::M03_SEMANTIC_STATES, true) || $origin !== 'PATIENT_DECLARATION') {
        return ['resultado' => 422, 'mensaje' => 'El estado semántico de la respuesta no es válido.'];
    }
    if (in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) && $detalle !== '') {
        if ($detectedUnknownState !== $semanticState) {
            return ['resultado' => 422, 'mensaje' => 'UNKNOWN y NOT_REMEMBERED no pueden contener un valor clínico inventado.'];
        }
        $detalle = self::canonicalM03UnknownValue($semanticState) ?? $detalle;
    }
    if (in_array($semanticState, ['KNOWN', 'NEGATIVE'], true) && $detalle === '') {
        return ['resultado' => 422, 'mensaje' => 'La respuesta requiere un valor clínico.'];
    }
    $q5NotStopped = $questionData['tipo'] === 'input'
        && self::isM03Q5NotStoppedResponse($idPregunta, $idTema, $detalle, $semanticState);
    if ($semanticState === 'NEGATIVE'
        && !$q5NotStopped
        && ($questionData['tipo'] !== 'select' || $detalle !== 'No')) {
        return ['resultado' => 422, 'mensaje' => 'La respuesta negativa no corresponde a esta pregunta.'];
    }
    if (in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) && !$semanticStateAvailable && $detalle === '') {
        return ['resultado' => 409, 'mensaje' => 'La respuesta desconocida requiere su valor textual canónico.', 'code' => 'M03_TEXTUAL_UNKNOWN_VALUE_REQUIRED', 'persist' => false];
    }
    if ($isTobaccoQuestion) {
        $this->traceM03('model.capture.tobacco_persist.dispatch');
        return $this->persistM03TobaccoResponse($idPaciente, $idPregunta, $idTema, $detalle, $semanticState, $origin, $expected, $expectedResponseId, $semanticStateAvailable);
    }
    if ($semanticStateAvailable) {
        $semanticDetail = in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) ? '' : $detalle;
        return $this->persistSemanticResponse($idPaciente, $idPregunta, $idTema, $semanticDetail, $semanticState, $origin, $expected, $expectedResponseId);
    }
    if ($detalle === '') {
        return ['resultado' => 409, 'mensaje' => 'La instalación aún no admite respuestas sin valor clínico.', 'code' => 'M03_SEMANTIC_STATE_SCHEMA_REQUIRED', 'persist' => false];
    }

    if ($questionData['tipo'] === 'select' && !in_array($detalle, ['Si', 'No'], true)) {
        return ['resultado' => 400, 'mensaje' => 'La opción seleccionada no es válida.'];
    }

    return $this->persistLegacyM03Response($idPaciente, $idPregunta, $idTema, $detalle, $semanticState, $expected, $expectedResponseId);
    }

    /**
     * Updates one already-persisted M03 answer for the separate previous-responses
     * screen. This is deliberately update-only: it never inserts a missing row,
     * changes module finalization, or clears dependent answers.
     */
    public function actualizarRespuestaM03Existente(array $data): array
    {
        $patientId = (int)($data['idPaciente'] ?? 0);
        $questionId = (int)($data['idPregunta'] ?? 0);
        $themeId = (int)($data['idTema'] ?? 0);
        $responseId = (int)($data['idRespuesta'] ?? 0);
        $detail = is_string($data['detalle'] ?? null) ? trim($data['detalle']) : '';
        $state = strtoupper(trim((string)($data['estadoRespuesta'] ?? '')));
        $origin = strtoupper(trim((string)($data['origenRespuesta'] ?? '')));
        $expectedValue = is_string($data['expectedRespuesta'] ?? null) ? $data['expectedRespuesta'] : null;
        $expectedState = strtoupper(trim((string)($data['expectedEstadoRespuesta'] ?? '')));
        $expectedOrigin = is_string($data['expectedOrigenRespuesta'] ?? null) ? $data['expectedOrigenRespuesta'] : null;
        $expectedStateAt = is_string($data['expectedEstadoRespuestaAt'] ?? null) ? $data['expectedEstadoRespuestaAt'] : null;

        if ($patientId < 1 || $questionId < 1 || $themeId < 1 || $responseId < 1
            || $expectedValue === null || !in_array($state, self::M03_SEMANTIC_STATES, true)
            || $origin !== 'PATIENT_DECLARATION' || mb_strlen($detail, 'UTF-8') > 2000) {
            return ['resultado' => 422, 'mensaje' => 'La solicitud de edición M03 no es válida.', 'code' => 'M03_PREVIOUS_EDIT_INVALID'];
        }

        $columns = $this->m03SemanticColumns();
        $semanticColumnNames = ['estado_respuesta', 'origen_respuesta', 'estado_respuesta_at'];
        $semanticColumnCount = count(array_intersect($semanticColumnNames, array_keys($columns)));
        if ($semanticColumnCount !== 0 && $semanticColumnCount !== count($semanticColumnNames)) {
            return ['resultado' => 409, 'mensaje' => 'El estado semántico M03 no puede verificarse con el esquema actual. No se modificó la respuesta.', 'code' => 'M03_SEMANTIC_SCHEMA_INCOMPLETE', 'persist' => false];
        }
        $semanticAvailable = $semanticColumnCount === count($semanticColumnNames);
        $driver = (string)$this->bd->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';

        try {
            $this->bd->beginTransaction();
            $patient = $this->bd->prepare('SELECT id FROM pc_paciente WHERE id=:patient LIMIT 1' . $lock);
            $patient->execute([':patient' => $patientId]);
            if ($patient->fetchColumn() === false) {
                $this->bd->rollBack();
                return ['resultado' => 404, 'mensaje' => 'El paciente autorizado no está disponible.', 'code' => 'M03_PATIENT_NOT_FOUND', 'persist' => false];
            }

            $questionStmt = $this->bd->prepare('SELECT id,id_tema,tipo,pregunta FROM pac_preguntas_modulo_3 WHERE id=:question AND id_tema=:theme LIMIT 1');
            $questionStmt->execute([':question' => $questionId, ':theme' => $themeId]);
            $question = $questionStmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($question) || !in_array((string)$question['tipo'], ['select', 'input'], true)) {
                $this->bd->rollBack();
                return ['resultado' => 404, 'mensaje' => 'La pregunta no pertenece al tema M03 autorizado.', 'code' => 'M03_PREVIOUS_QUESTION_MISMATCH', 'persist' => false];
            }

            $stateSelect = $semanticAvailable ? ',estado_respuesta,origen_respuesta,estado_respuesta_at' : '';
            $rows = $this->bd->prepare('SELECT id,id_pregunta,id_paciente,respuesta' . $stateSelect . ' FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=:patient AND id_pregunta=:question ORDER BY id ASC LIMIT 2' . $lock);
            $rows->execute([':patient' => $patientId, ':question' => $questionId]);
            $matches = $rows->fetchAll(\PDO::FETCH_ASSOC);
            if (count($matches) !== 1 || (int)$matches[0]['id'] !== $responseId) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta dejó de ser única o cambió. Recarga antes de editar.', 'code' => count($matches) > 1 ? 'M03_DUPLICATE_RESPONSE_ROWS' : 'M03_RESPONSE_ID_CONFLICT', 'persist' => false];
            }
            $current = $matches[0];
            $currentValue = (string)($current['respuesta'] ?? '');
            $currentStoredState = $semanticAvailable ? strtoupper(trim((string)($current['estado_respuesta'] ?? ''))) : '';
            $currentOrigin = $semanticAvailable && isset($current['origen_respuesta']) ? (string)$current['origen_respuesta'] : null;
            $currentStateAt = $semanticAvailable && isset($current['estado_respuesta_at']) ? (string)$current['estado_respuesta_at'] : null;
            $projectedState = $semanticAvailable
                ? $currentStoredState
                : (self::canonicalM03UnknownState($currentValue) ?? (trim($currentValue) === '' ? 'UNANSWERED' : 'LEGACY_UNCLASSIFIED'));
            if ($currentValue !== $expectedValue || $projectedState !== $expectedState
                || $currentOrigin !== $expectedOrigin || $currentStateAt !== $expectedStateAt) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. No se sobrescribieron cambios concurrentes.', 'code' => 'M03_PREVIOUS_VERSION_CONFLICT', 'persist' => false];
            }
            if ($semanticAvailable && !in_array($currentStoredState, self::M03_SEMANTIC_STATES, true)) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'El estado semántico registrado requiere revisión antes de editar.', 'code' => 'M03_PREVIOUS_STATE_INCONSISTENT', 'persist' => false];
            }

            $type = (string)$question['tipo'];
            $q5NotStopped = self::isM03Q5NotStoppedResponse($questionId, $themeId, $detail, $state);
            if ($state === 'UNKNOWN' || $state === 'NOT_REMEMBERED') {
                $canonical = self::canonicalM03UnknownValue($state);
                if (($semanticAvailable && $detail !== '') || (!$semanticAvailable && $detail !== $canonical)) {
                    $this->bd->rollBack();
                    return ['resultado' => 422, 'mensaje' => 'El estado desconocido no puede incluir un valor clínico inventado.', 'code' => 'M03_PREVIOUS_UNKNOWN_VALUE_INVALID', 'persist' => false];
                }
            } elseif ($state === 'NEGATIVE') {
                if (!$q5NotStopped && ($type !== 'select' || $detail !== 'No')) {
                    $this->bd->rollBack();
                    return ['resultado' => 422, 'mensaje' => 'La respuesta negativa no corresponde a esta pregunta.', 'code' => 'M03_PREVIOUS_NEGATIVE_INVALID', 'persist' => false];
                }
            } elseif ($detail === '' || ConversationalInterviewService::classifyM03Unknown($detail) !== null) {
                $this->bd->rollBack();
                return ['resultado' => 422, 'mensaje' => 'La respuesta requiere un dato conocido o un estado explícito de incertidumbre.', 'code' => 'M03_PREVIOUS_KNOWN_VALUE_INVALID', 'persist' => false];
            }
            $detailedTobaccoAffirmative = M03TobaccoResponsePolicy::isQuestion($question + ['idPregunta' => $questionId, 'idTema' => $themeId])
                && $state === 'KNOWN'
                && M03TobaccoResponsePolicy::classify($detail) === M03TobaccoResponsePolicy::AFFIRMATIVE;
            if ($type === 'select' && !in_array($state, ['UNKNOWN', 'NOT_REMEMBERED'], true)
                && !(($state === 'KNOWN' && $detail === 'Si') || ($state === 'NEGATIVE' && $detail === 'No') || $detailedTobaccoAffirmative)) {
                $this->bd->rollBack();
                return ['resultado' => 422, 'mensaje' => 'Selecciona una opción válida para esta pregunta.', 'code' => 'M03_PREVIOUS_SELECT_INVALID', 'persist' => false];
            }
            if (M03TobaccoResponsePolicy::isQuestion($question + ['idPregunta' => $questionId, 'idTema' => $themeId])) {
                $decision = $detail === '' && in_array($state, ['UNKNOWN', 'NOT_REMEMBERED'], true)
                    ? $state
                    : M03TobaccoResponsePolicy::classify($detail);
                if (M03TobaccoResponsePolicy::semanticState($decision) !== $state) {
                    $this->bd->rollBack();
                    return ['resultado' => 422, 'mensaje' => 'La respuesta no coincide con su estado semántico.', 'code' => 'M03_PREVIOUS_TOBACCO_STATE_INVALID', 'persist' => false];
                }
            }
            if (ConversationalInterviewService::isM03BloodRhQuestion($question + ['idTema' => $themeId])
                && preg_match('/^\s*(?:no|nunca)\s*[.!?]*\s*$/iu', $detail) === 1) {
                $this->bd->rollBack();
                return ['resultado' => 422, 'mensaje' => '“No” no es un valor válido para grupo sanguíneo o factor Rh.', 'code' => 'M03_PREVIOUS_BLOOD_RH_INVALID', 'persist' => false];
            }

            if ($state === 'NEGATIVE' && \App\Services\ConversationalM03Adapter::isThemeParentQuestion($themeId, $questionId)) {
                $dependentStateSelect = $semanticAvailable ? ',r.estado_respuesta' : '';
                $dependent = $this->bd->prepare('SELECT r.id,r.respuesta' . $dependentStateSelect . ' FROM pac_respuestas_paciente_modulo_3 r INNER JOIN pac_preguntas_modulo_3 p ON p.id=r.id_pregunta WHERE r.id_paciente=:patient AND p.id_tema=:theme AND p.id<>:question ORDER BY r.id ASC' . $lock);
                $dependent->execute([':patient' => $patientId, ':theme' => $themeId, ':question' => $questionId]);
                foreach ($dependent->fetchAll(\PDO::FETCH_ASSOC) as $dependentRow) {
                    $dependentValue = trim((string)($dependentRow['respuesta'] ?? ''));
                    $dependentState = $semanticAvailable ? strtoupper(trim((string)($dependentRow['estado_respuesta'] ?? ''))) : '';
                    if ($dependentValue !== '' || in_array($dependentState, self::M03_SEMANTIC_STATES, true)) {
                        $this->bd->rollBack();
                        return ['resultado' => 409, 'mensaje' => 'No se puede cambiar a No porque hay respuestas dependientes que deben conservarse.', 'code' => 'M03_DEPENDENCY_REVIEW_REQUIRED', 'persist' => false];
                    }
                }
            }

            $storedDetail = $semanticAvailable && in_array($state, ['UNKNOWN', 'NOT_REMEMBERED'], true) ? '' : $detail;
            if ($currentValue === $storedDetail
                && (!$semanticAvailable || ($currentStoredState === $state && $currentOrigin === $origin))) {
                $this->bd->commit();
                return ['resultado' => 200, 'mensaje' => 'La respuesta ya contiene ese valor.', 'persist' => false, 'no_op' => true, 'response_id' => $responseId, 'readback' => true, 'semantic_state' => $state, 'readback_value' => $storedDetail];
            }

            if ($semanticAvailable) {
                $update = $this->bd->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta=:detail,estado_respuesta=:state,origen_respuesta=:origin,estado_respuesta_at=:state_at WHERE id=:response AND id_paciente=:patient AND id_pregunta=:question');
                $update->execute([':detail' => $storedDetail, ':state' => $state, ':origin' => $origin, ':state_at' => gmdate('Y-m-d H:i:s'), ':response' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            } else {
                $update = $this->bd->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta=:detail WHERE id=:response AND id_paciente=:patient AND id_pregunta=:question');
                $update->execute([':detail' => $storedDetail, ':response' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            }
            if ($update->rowCount() !== 1) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'No fue posible actualizar exclusivamente el registro seleccionado.', 'code' => 'M03_PREVIOUS_UPDATE_CONFLICT', 'persist' => false];
            }

            $readbackSelect = 'SELECT id,respuesta' . ($semanticAvailable ? ',estado_respuesta,origen_respuesta,estado_respuesta_at' : '') . ' FROM pac_respuestas_paciente_modulo_3 WHERE id=:response AND id_paciente=:patient AND id_pregunta=:question LIMIT 1';
            $readbackStmt = $this->bd->prepare($readbackSelect);
            $readbackStmt->execute([':response' => $responseId, ':patient' => $patientId, ':question' => $questionId]);
            $saved = $readbackStmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($saved) || (int)$saved['id'] !== $responseId || (string)$saved['respuesta'] !== $storedDetail
                || ($semanticAvailable && ((string)$saved['estado_respuesta'] !== $state || (string)$saved['origen_respuesta'] !== $origin))) {
                $this->bd->rollBack();
                return ['resultado' => 500, 'mensaje' => 'La lectura posterior no confirmó la respuesta actualizada.', 'code' => 'M03_PREVIOUS_READBACK_FAILED', 'persist' => false];
            }
            $this->bd->commit();
            return ['resultado' => 200, 'mensaje' => 'Respuesta M03 actualizada.', 'persist' => true, 'response_id' => $responseId, 'readback' => true, 'readback_value' => (string)$saved['respuesta'], 'semantic_state' => $state];
        } catch (\Throwable) {
            if ($this->bd->inTransaction()) $this->bd->rollBack();
            return ['resultado' => 500, 'mensaje' => 'No fue posible actualizar la respuesta M03.', 'code' => 'M03_PREVIOUS_UPDATE_FAILED', 'persist' => false];
        }
    }

    /**
     * Persists a semantic M03 state atomically once the additive schema is
     * present. Unknown states intentionally keep respuesta empty; the state
     * column, origin and timestamp carry the patient's declaration.
     */
    private function persistSemanticResponse(int $patientId, int $questionId, int $themeId, string $detail, string $state, string $origin, ?string $expected, ?int $expectedResponseId): array
    {
    try {
    $this->bd->beginTransaction();
    $lock = $this->bd->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $lookup = $this->bd->prepare('SELECT id, respuesta, estado_respuesta, origen_respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = :patient AND id_pregunta = :question ORDER BY id ASC LIMIT 2' . $lock);
    $lookup->execute([':patient' => $patientId, ':question' => $questionId]);
    $rows = $lookup->fetchAll(\PDO::FETCH_ASSOC);
    if (count($rows) > 1) {
        $this->bd->rollBack();
        return ['resultado' => 409, 'mensaje' => 'Existen respuestas M03 duplicadas; la corrección requiere revisión autorizada.', 'code' => 'M03_DUPLICATE_RESPONSE_ROWS', 'persist' => false];
    }
    $current = $rows[0] ?? null;
    $currentValue = (string)($current['respuesta'] ?? '');
    if (($current === null && $expectedResponseId !== null)
        || ($current !== null && $expectedResponseId !== null && (int)$current['id'] !== $expectedResponseId)
        || ($current !== null && $expectedResponseId === null)) {
        $this->bd->rollBack();
        return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. Actualiza la pregunta antes de continuar.', 'code' => 'M03_RESPONSE_ID_CONFLICT', 'persist' => false];
    }
    if ($expected !== null && $currentValue !== $expected) {
        $this->bd->rollBack();
        return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Recarga la pregunta antes de continuar.', 'code' => 'M03_VERSION_CONFLICT', 'persist' => false];
    }
    if ($state === 'NEGATIVE' && !self::isM03Q5NotStoppedResponse($questionId, $themeId, $detail, $state)) {
        $dependent = $this->bd->prepare(
            "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3
             WHERE id_paciente = :patient AND id <> :response
               AND id_pregunta IN (SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema = :theme)
               AND TRIM(COALESCE(respuesta, '')) <> ''"
        );
        $dependent->execute([':patient' => $patientId, ':response' => (int)($current['id'] ?? 0), ':theme' => $themeId]);
        if ((int)$dependent->fetchColumn() > 0) {
            $this->bd->rollBack();
            return ['resultado' => 409, 'mensaje' => 'Este cambio puede limpiar respuestas dependientes. Continúa en el formulario tradicional para revisarlo.', 'code' => 'M03_DEPENDENCY_REVIEW_REQUIRED', 'persist' => false];
        }
    }
    if ($current !== null
        && $currentValue === $detail
        && strtoupper(trim((string)($current['estado_respuesta'] ?? ''))) === $state
        && (string)($current['origen_respuesta'] ?? '') === $origin) {
        $responseId = (int)$current['id'];
        $this->bd->commit();
        return ['resultado' => 200, 'mensaje' => 'La respuesta ya estaba guardada; no se realizaron cambios.', 'persist' => false, 'no_op' => true, 'semantic_state' => $state, 'response_id' => $responseId];
    }
    $timestamp = gmdate('Y-m-d H:i:s');
    if ($current === null) {
        if ($expectedResponseId !== null) {
            $this->bd->rollBack();
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba.', 'code' => 'M03_RESPONSE_ID_CONFLICT', 'persist' => false];
        }
        $insert = $this->bd->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta, estado_respuesta, origen_respuesta, estado_respuesta_at) VALUES (:question, :patient, :detail, :state, :origin, :state_at)');
        $insert->execute([':question' => $questionId, ':patient' => $patientId, ':detail' => $detail, ':state' => $state, ':origin' => $origin, ':state_at' => $timestamp]);
    } else {
        $update = $this->bd->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = :detail, estado_respuesta = :state, origen_respuesta = :origin, estado_respuesta_at = :state_at WHERE id = :id AND id_paciente = :patient AND id_pregunta = :question AND respuesta = :expected');
        $update->execute([':detail' => $detail, ':state' => $state, ':origin' => $origin, ':state_at' => $timestamp, ':id' => (int)$current['id'], ':patient' => $patientId, ':question' => $questionId, ':expected' => $currentValue]);
        if ($update->rowCount() !== 1) {
            $this->bd->rollBack();
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. No se sobrescribieron cambios concurrentes.', 'code' => 'M03_VERSION_CONFLICT', 'persist' => false];
        }
    }
    $this->bd->commit();
    return ['resultado' => 200, 'mensaje' => 'Respuesta M03 guardada.', 'persist' => true, 'semantic_state' => $state, 'readback' => false];
    } catch (\Throwable) {
    if ($this->bd->inTransaction()) $this->bd->rollBack();
    return ['resultado' => 500, 'mensaje' => 'No fue posible guardar la respuesta M03.', 'code' => 'M03_SEMANTIC_PERSISTENCE_FAILED', 'persist' => false];
    }
    }

    /** Patient-scoped, version-checked persistence for installations without semantic columns. */
    private function persistLegacyM03Response(int $patientId, int $questionId, int $themeId, string $detail, string $state, ?string $expected, ?int $expectedResponseId): array
    {
        try {
            $this->bd->beginTransaction();
            $lock = $this->bd->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $patient = $this->bd->prepare('SELECT id FROM pc_paciente WHERE id = :patient LIMIT 1' . $lock);
            $patient->execute([':patient' => $patientId]);
            if ($patient->fetchColumn() === false) {
                $this->bd->rollBack();
                return ['resultado' => 404, 'mensaje' => 'El paciente autorizado no está disponible.', 'code' => 'M03_PATIENT_NOT_FOUND', 'persist' => false];
            }

            $lookup = $this->bd->prepare('SELECT id, respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = :patient AND id_pregunta = :question ORDER BY id ASC LIMIT 2' . $lock);
            $lookup->execute([':patient' => $patientId, ':question' => $questionId]);
            $rows = $lookup->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) > 1) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'Existen respuestas M03 duplicadas; se requiere revisión autorizada.', 'code' => 'M03_DUPLICATE_RESPONSE_ROWS', 'persist' => false];
            }
            $current = $rows[0] ?? null;
            $currentValue = (string)($current['respuesta'] ?? '');
            if (($current === null && $expectedResponseId !== null)
                || ($current !== null && $expectedResponseId !== null && (int)$current['id'] !== $expectedResponseId)
                || ($current !== null && $expectedResponseId === null)) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. Actualiza la pregunta antes de continuar.', 'code' => 'M03_RESPONSE_ID_CONFLICT', 'persist' => false];
            }
            if ($expected !== null && $currentValue !== $expected) {
                $this->bd->rollBack();
                return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Actualiza la pregunta antes de continuar.', 'code' => 'M03_VERSION_CONFLICT', 'persist' => false];
            }
            if ($state === 'NEGATIVE' && !self::isM03Q5NotStoppedResponse($questionId, $themeId, $detail, $state)) {
                $dependent = $this->bd->prepare("SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = :patient AND id <> :response AND id_pregunta IN (SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema = :theme) AND TRIM(COALESCE(respuesta, '')) <> ''");
                $dependent->execute([':patient' => $patientId, ':response' => (int)($current['id'] ?? 0), ':theme' => $themeId]);
                if ((int)$dependent->fetchColumn() > 0) {
                    $this->bd->rollBack();
                    return ['resultado' => 409, 'mensaje' => 'Este cambio puede afectar respuestas dependientes. No se guardó ningún cambio.', 'code' => 'M03_DEPENDENCY_REVIEW_REQUIRED', 'persist' => false];
                }
            }
            if ($current !== null && $currentValue === $detail) {
                $this->bd->commit();
                return ['resultado' => 200, 'mensaje' => 'La respuesta ya estaba guardada; no se realizaron cambios.', 'persist' => false, 'no_op' => true, 'response_id' => (int)$current['id']];
            }
            if ($current === null) {
                $insert = $this->bd->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta) VALUES (:question, :patient, :detail)');
                $insert->execute([':question' => $questionId, ':patient' => $patientId, ':detail' => $detail]);
                $responseId = (int)$this->bd->lastInsertId();
            } else {
                $update = $this->bd->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = :detail WHERE id = :id AND id_paciente = :patient AND id_pregunta = :question AND respuesta = :expected');
                $update->execute([':detail' => $detail, ':id' => (int)$current['id'], ':patient' => $patientId, ':question' => $questionId, ':expected' => $currentValue]);
                if ($update->rowCount() !== 1) {
                    $this->bd->rollBack();
                    return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se editaba. No se sobrescribieron cambios concurrentes.', 'code' => 'M03_VERSION_CONFLICT', 'persist' => false];
                }
                $responseId = (int)$current['id'];
            }
            $this->bd->commit();
            return ['resultado' => 200, 'mensaje' => 'Respuesta M03 guardada.', 'persist' => true, 'response_id' => $responseId];
        } catch (\Throwable) {
            if ($this->bd->inTransaction()) $this->bd->rollBack();
            return ['resultado' => 500, 'mensaje' => 'No fue posible guardar la respuesta M03.', 'code' => 'M03_LEGACY_PERSISTENCE_FAILED', 'persist' => false];
        }
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerPreguntasModulos(){
    $stmt = $this->bd->query("SELECT id FROM pac_preguntas_modulo_3 ORDER BY id ASC");
    $idPreguntas = [];
        
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $idPreguntas[] = $row['id'];
    }

    return $idPreguntas;
    }

    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoANP($idPaciente, $idTema){
    $result = '';

    $stmt = $this->bd->prepare("SELECT
    pac_respuestas_paciente_modulo_3.id AS idRespuesta,
    pac_preguntas_modulo_3.id AS idPregunta,
    pac_temas_modulo_3.tema,
    pac_preguntas_modulo_3.pregunta, 
    pac_preguntas_modulo_3.tipo, 
    COALESCE(pac_respuestas_paciente_modulo_3.respuesta, '') AS respuesta
    FROM pac_temas_modulo_3 
    INNER JOIN pac_preguntas_modulo_3 ON pac_preguntas_modulo_3.id_tema = pac_temas_modulo_3.id 
    INNER JOIN pac_respuestas_paciente_modulo_3 ON pac_preguntas_modulo_3.id = pac_respuestas_paciente_modulo_3.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_3.id_paciente = '".$idPaciente."' AND pac_temas_modulo_3.id = '".$idTema."' ORDER BY pac_temas_modulo_3.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $modulos = $this->obtenerNameModulos($idTema);


    if (!empty($preguntas)) {
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center" colspan="3">'.$modulos.'</th>
    </tr>
    
    <tr>
    <th class="text-center" width="20px">#</th>
    <th class="text-start">Pregunta</th>
    <th class="text-center">Respuesta</th>
    </tr>';
    
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];

    $result .= '    
    <tr>
    <td class="text-center">'.$num.'</td>
    <td class="text-start">'.$preguntaPC.'</td>
    <td class="text-center">'.$respuesta.'</td>
    </tr>';
    
    $num++;
    endforeach;

    $result .= '</table>';
    }


    return $result;
    }


    public function mostrarPreguntasM3($idPaciente, $idRol, $idTema){
    $result = '';
    $escapeAttribute = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

    $stmt = $this->bd->prepare("SELECT
    pac_respuestas_paciente_modulo_3.id AS idRespuesta,
    pac_preguntas_modulo_3.id AS idPregunta,
    pac_temas_modulo_3.tema,
    pac_preguntas_modulo_3.pregunta, 
    pac_preguntas_modulo_3.tipo, 
    COALESCE(pac_respuestas_paciente_modulo_3.respuesta, '') AS respuesta
    FROM pac_temas_modulo_3 
    INNER JOIN pac_preguntas_modulo_3 ON pac_preguntas_modulo_3.id_tema = pac_temas_modulo_3.id 
    LEFT JOIN pac_respuestas_paciente_modulo_3
      ON pac_preguntas_modulo_3.id = pac_respuestas_paciente_modulo_3.id_pregunta
     AND pac_respuestas_paciente_modulo_3.id_paciente = :id_paciente
    WHERE pac_temas_modulo_3.id = :id_tema
    ORDER BY pac_temas_modulo_3.id ASC");
    $stmt->execute([
    ':id_paciente' => (int)$idPaciente,
    ':id_tema' => (int)$idTema
    ]);
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
    if($idRol == "Paciente"){
    $modulos = $this->obtenerModulos();
    $temaIds = array_keys($modulos);
    $temaIndex = array_search((int)$idTema, array_map('intval', $temaIds), true);
    $temaIndex = ($temaIndex === false) ? -1 : $temaIndex;
    $prevId = $temaIndex > 0 ? (int)$temaIds[$temaIndex - 1] : null;
    $nextId = ($temaIndex >= 0 && $temaIndex < count($temaIds) - 1)
        ? (int)$temaIds[$temaIndex + 1]
        : null;
    $prevNombre = $prevId !== null ? $modulos[$prevId] : null;
    $nextNombre = $nextId !== null ? $modulos[$nextId] : null;

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $temaModulo = $pregunta['tema'];
    $idRespuesta = (int)($pregunta['idRespuesta'] ?? 0);
    $idPregunta = (int)$pregunta['idPregunta'];
    $domKey = $idRespuesta > 0 ? (string)$idRespuesta : 'q' . $idPregunta;
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $tipo = $pregunta['tipo'];
    $respuestaGuardada = $respuesta == 0 ? '' : (string)$respuesta;
    $affirmativeSelected = $idPregunta === M03TobaccoResponsePolicy::QUESTION_ID && (int)$idTema === M03TobaccoResponsePolicy::THEME_ID
        ? M03TobaccoResponsePolicy::isAffirmative($respuestaGuardada)
        : $respuestaGuardada === 'Si';

                    
    // La primera pregunta se muestra activa
    $claseActiva = ($index == 0) ? 'active' : '';      
    $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$domKey.'" data-pregunta="'.$escapeAttribute($preguntaPC).'" data-pregunta-id="'.$idPregunta.'" data-rol="'.$escapeAttribute($idRol).'" data-tema="'.$idTema.'">';
                   
    
    $result .= '<div class="text-secondary fw-bold mb-1 p05-question-habit-label">Nombre de la conducta o hábito:</div>';
    $result .= '<h4 class="mb-3 p05-question-habit-name">'.$temaModulo.'</h4>';
    $result .= '<div id="seccion'.$domKey.'" data-autoplay="false" class="p05-question-prompt sectionQuestion mt-3 mb-1">';
    // $result .= '<img src="'.RUTA_IMAGES.'/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion'.$idRespuesta.'">';
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>'.$preguntaPC.'</b></h8>
    </div>';

        
    // Mostrar el tipo de pregunta (select o input)
    if ($tipo == "select") {
    $result .= '
    <div class="d-flex flex-wrap gap-2">
    <div class="text-center d-flex flex-column align-items-center">
    <input id="p05-respuesta-si-'.$domKey.'" type="radio" name="p05_respuesta_'.$domKey.'" data-value="Si" class="form-check-input custom-checkbox mb-2" aria-label="Sí"
    data-p05-control="true" data-p05-patient="'.$escapeAttribute($idPaciente).'" data-p05-response="'.$escapeAttribute($idRespuesta).'" data-p05-question-id="'.$idPregunta.'" data-p05-theme="'.$escapeAttribute($idTema).'" data-p05-type="'.$escapeAttribute($tipo).'" data-p05-role="'.$escapeAttribute($idRol).'" data-p05-saved-value="'.$escapeAttribute($respuestaGuardada).'"
    value="Si" '.($affirmativeSelected ? 'checked' : '').'>
    <label for="p05-respuesta-si-'.$domKey.'"><h5 class="text-secondary">Sí</h5></label>
    </div>
        
    <div class="text-center d-flex flex-column align-items-center">
    <input id="p05-respuesta-no-'.$domKey.'" type="radio" name="p05_respuesta_'.$domKey.'" data-value="No" class="form-check-input custom-checkbox mb-2" aria-label="No"
    data-p05-control="true" data-p05-patient="'.$escapeAttribute($idPaciente).'" data-p05-response="'.$escapeAttribute($idRespuesta).'" data-p05-question-id="'.$idPregunta.'" data-p05-theme="'.$escapeAttribute($idTema).'" data-p05-type="'.$escapeAttribute($tipo).'" data-p05-role="'.$escapeAttribute($idRol).'" data-p05-saved-value="'.$escapeAttribute($respuestaGuardada).'" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
    <label for="p05-respuesta-no-'.$domKey.'"><h5 class="text-secondary">No</h5></label>
    </div>
    </div>';
        
    } else {
    $result .= '<input type="text" class="form-control" value="' . $escapeAttribute($respuestaGuardada) . '" placeholder="Ingresa aquí tu respuesta..." data-p05-control="true" data-p05-patient="'.$escapeAttribute($idPaciente).'" data-p05-response="'.$escapeAttribute($idRespuesta).'" data-p05-question-id="'.$idPregunta.'" data-p05-theme="'.$escapeAttribute($idTema).'" data-p05-type="'.$escapeAttribute($tipo).'" data-p05-role="'.$escapeAttribute($idRol).'" data-p05-saved-value="'.$escapeAttribute($respuestaGuardada).'">';
    }
                    
    //----- Botones de navegación -----
    $result .= '<div class="mt-3 d-flex justify-content-between">';
    // Botón "Anterior"
    if ($index == 0) {
    if($prevId !== null){
    $result .= '<button class="btn btn-success" onclick="anteriorPregunta(' . $idTema . ')"><i data-feather="chevron-left"></i>'.$prevNombre.'</button>';
    } else {
    $result .= '<div></div>';
    }
    } else {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPregunta(' . $idTema . ')"><i data-feather="chevron-left"></i> Anterior</button>';
    }
      
    $respuestaLista = !($tipo == "select" && trim((string) $respuesta) === '');
    if (!$respuestaLista) {
    $result .= '<button type="button" class="btn btn-primary patient-nonpathological-next" data-p05-next data-p05-theme="'.$escapeAttribute($idTema).'" disabled aria-disabled="true" title="Selecciona una respuesta para continuar"><span>Siguiente</span> <i data-feather="chevron-right"></i></button>';
    } elseif ($index < count($preguntas) - 1) {
    if($index == 0 && $tipo == "select" && $respuesta == "No"){
    // Botón para ir a la última sección
    if($nextId !== null){
    $result .= '<button type="button" class="btn btn-success" data-p05-next-theme="'.$escapeAttribute($nextNombre).'" data-p05-next-theme-id="'.$escapeAttribute($nextId).'">'.$nextNombre.' <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button type="button" class="btn btn-success" data-p05-finalize>Comentarios <i data-feather="message-circle"></i></button>';
    }
    } else {
    // Botón normal para la siguiente pregunta
    $result .= '<button type="button" class="btn btn-primary patient-nonpathological-next" data-p05-next data-p05-theme="'.$escapeAttribute($idTema).'">Siguiente <i data-feather="chevron-right"></i></button>';
    }
    } else {
    // Al final del módulo, se comprueba si existe un siguiente módulo
    if($nextId !== null){
    $result .= '<button type="button" class="btn btn-success" data-p05-next-theme="'.$escapeAttribute($nextNombre).'" data-p05-next-theme-id="'.$escapeAttribute($nextId).'">'.$nextNombre.' <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button type="button" class="btn btn-success" data-p05-finalize>Comentarios <i data-feather="message-circle"></i></button>';
    }
    }

    $result .= '</div>';  // Cierre contenedor de botones
    $result .= '</div>';  // Cierre contenedor de la pregunta
                    
    $num++;
    endforeach;
    }
    
    } else {
    
    $modulos = $this->obtenerNameModulos($idTema);
    
    $result .= '    
    
    <div class="alert alert-success text-center" role="alert">
    '.$modulos.'
    </div>

    <div class="table-responsive mb-3">
    <table class="table table-striped" id="table_enfermedades">
    <thead>
    <tr>
    <th class="text-center align-middle" width="40px">#</th>
    <th class="text-start align-middle" >Pregunta</th>
    <th class="text-start align-middle text-center" width="280px">Respuesta</th>
    </tr>
    </thead>
    <tbody>';
    
    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $tipo = $pregunta['tipo'];

    // Mostrar el tipo de pregunta (select o input)
    if ($tipo == "select") {
    $elementotd = '<select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\',\''.$idRol.'\',1)">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($respuesta == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($respuesta == 'No' ? 'selected' : '') . '>No</option>
    </select>';
    
    } else {

    $deshabilitarInput = $this->validarFormato($idPaciente,$idTema);
    $elementotd = '<input type="text" class="form-control text-center" ' . $deshabilitarInput . ' value="' . ($respuesta == 0 ? '' : $respuesta) . '" placeholder="Ingresa aquí la respuesta..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\',\''.$idRol.'\')">';
    }
    
    $result .= ' <tr> 
    <td class="text-center align-middle">'.$num.'</td>';
    
    $result .= '<td class="text-start align-middle">'.$preguntaPC.'</td>
    <td class="text-start align-middle">'.$elementotd.'</td>
    </tr>';
                
    $num++;
    endforeach;
    }
    
    $result .= '</tbody>
    </table>
    </div>';
    
    }
    
    return $result;
    }


    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function validarFormato($idPaciente, $idTema) {
    $ocultartb = "";

    $stmt = $this->bd->prepare("SELECT
    pac_respuestas_paciente_modulo_3.id AS idRespuesta, 
    pac_temas_modulo_3.tema,
    pac_preguntas_modulo_3.pregunta, 
    pac_preguntas_modulo_3.tipo, 
    pac_respuestas_paciente_modulo_3.respuesta 
    FROM pac_temas_modulo_3 
    INNER JOIN pac_preguntas_modulo_3 ON pac_preguntas_modulo_3.id_tema = pac_temas_modulo_3.id 
    INNER JOIN pac_respuestas_paciente_modulo_3 ON pac_preguntas_modulo_3.id = pac_respuestas_paciente_modulo_3.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_3.id_paciente = :id_paciente
      AND pac_temas_modulo_3.id = :id_tema
      AND pac_preguntas_modulo_3.tipo = 'select'
    ORDER BY pac_temas_modulo_3.id ASC");
    $stmt->execute([
    ':id_paciente' => (int)$idPaciente,
    ':id_tema' => (int)$idTema
    ]);
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($preguntas as $index => $pregunta) :
    $respuesta = $pregunta['respuesta'];
    
    if($respuesta == "No" || $respuesta == ""){
    $ocultartb = "disabled";
    }

    endforeach;

    return $ocultartb;
    }

    
    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function antecedentesNoPatologicos($idPaciente, $idPregunta) {
    $sql = "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = ? AND id_pregunta = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $idPregunta]);
    $numero = $stmt->fetchColumn();
        
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta) 
    VALUES (?, ?, '')";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute([$idPregunta, $idPaciente]);
    }
    }

    public function editarCuestionarioDato($idPaciente, $idRespuesta, $idTema, $detalle){

    if (!$this->respuestaPerteneceAlContexto($idPaciente, $idRespuesta, $idTema)) {
    return array('resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.');
    }

    $sql = "UPDATE pac_respuestas_paciente_modulo_3
            SET respuesta = :detalle
            WHERE id = :id_respuesta
              AND id_paciente = :id_paciente
              AND id_pregunta IN (SELECT p.id FROM pac_preguntas_modulo_3 p WHERE p.id_tema = :id_tema)";
    $stmt = $this->bd->prepare($sql);

    if ($stmt->execute([
    ':id_respuesta' => (int)$idRespuesta,
    ':id_paciente' => (int)$idPaciente,
    ':id_tema' => (int)$idTema,
    ':detalle' => (string)$detalle
    ])) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la respuesta correctamente!');
                    
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la respuesta!');
    }
    
    }


    public function editarCuestionarioSelect($idPaciente,$idRespuesta,$idTema){

    if (!$this->respuestaPerteneceAlContexto($idPaciente, $idRespuesta, $idTema)) {
    return array('resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.');
    }

    // Si la respuesta es "NO", actualizamos la respuesta a 'NO' y las demás respuestas a vacío
    $sql1 = "UPDATE pac_respuestas_paciente_modulo_3 r
    INNER JOIN pac_preguntas_modulo_3 p ON p.id = r.id_pregunta
    SET r.respuesta = 'No'
    WHERE r.id = :id_respuesta
      AND r.id_paciente = :id_paciente
      AND p.id_tema = :id_tema";
    $stmt1 = $this->bd->prepare($sql1);
    $stmt1->bindParam(':id_respuesta', $idRespuesta);
    $stmt1->bindParam(':id_paciente', $idPaciente);
    $stmt1->bindParam(':id_tema', $idTema);

    $sql2 = "UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = '' 
    WHERE id_pregunta IN (SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema = :id_tema) AND id_paciente = :id_paciente AND id != :id_respuesta";
    $stmt2 = $this->bd->prepare($sql2);
    $stmt2->bindParam(':id_tema', $idTema);
    $stmt2->bindParam(':id_paciente', $idPaciente);
    $stmt2->bindParam(':id_respuesta', $idRespuesta);

    $this->bd->beginTransaction();
    if ($stmt1->execute()) {
    if ($stmt2->execute()) {
    $this->bd->commit(); // Confirmar cambios
    return array('resultado' => 200, 'mensaje' => '¡Respuestas actualizadas exitosamente!');
        
    } else {
    $this->bd->rollback(); // Deshacer cambios
    return array('resultado' => 401, 'mensaje' => '¡Error al actualizar la respuesta en stmt2!');
    }

    } else {
    $this->bd->rollback(); // Deshacer cambios
    return array('resultado' => 401, 'mensaje' => '¡Error al actualizar la respuesta en stmt1!');
    }
    

    }

    public function editarCuestionarioModulo($data) {

    $idPaciente = (int)($data['idPaciente'] ?? 0);
    $idRespuesta = (int)($data['idRespuesta'] ?? 0);
    $idPregunta = (int)($data['idPregunta'] ?? 0);
    $idTema = (int)($data['idTema'] ?? 0);
    if ($idPaciente < 1 || $idRespuesta < 0 || $idPregunta < 1 || $idTema < 1 || !isset($data['detalle'], $data['idTipo'])) {
    return array('resultado' => 400, 'mensaje' => 'Datos de respuesta no válidos.');
    }

    if ($idRespuesta < 1) {
    $question = $this->bd->prepare('SELECT id, tipo FROM pac_preguntas_modulo_3 WHERE id = :idPregunta AND id_tema = :idTema LIMIT 1');
    $question->execute([':idPregunta' => $idPregunta, ':idTema' => $idTema]);
    $questionData = $question->fetch(\PDO::FETCH_ASSOC);
    if (!$questionData || (string)$questionData['tipo'] !== (string)$data['idTipo']) {
        return array('resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.');
    }
    $this->antecedentesNoPatologicos($idPaciente, $idPregunta);
    $contextLookup = $this->bd->prepare('SELECT id FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = :idPaciente AND id_pregunta = :idPregunta LIMIT 1');
    $contextLookup->execute([':idPaciente' => $idPaciente, ':idPregunta' => $idPregunta]);
    $idRespuesta = (int)$contextLookup->fetchColumn();
    }
    $contexto = $this->obtenerContextoRespuesta($idPaciente, $idRespuesta, $idTema);
    if ($contexto === null) {
    return array('resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.');
    }
    if ($contexto['tipo'] !== $data['idTipo']) {
    return array('resultado' => 400, 'mensaje' => 'Tipo de respuesta no válido.');
    }
    if (array_key_exists('expectedRespuesta', $data)
        && (string)$contexto['respuesta'] !== (string)$data['expectedRespuesta']) {
    return array('resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Recarga la pregunta antes de continuar.');
    }

    // The traditional binary control has no field for q1's conversational
    // details. A repeated affirmative choice must not collapse that detail to
    // the legacy literal "Si".
    if ($data['idTipo'] === 'select'
        && (int)($contexto['id_pregunta'] ?? 0) === M03TobaccoResponsePolicy::QUESTION_ID
        && $idTema === M03TobaccoResponsePolicy::THEME_ID
        && (string)$data['detalle'] === 'Si'
        && M03TobaccoResponsePolicy::isAffirmative((string)$contexto['respuesta'])
        && !M03TobaccoResponsePolicy::isSimpleAffirmative((string)$contexto['respuesta'])) {
        return ['resultado' => 200, 'mensaje' => 'La respuesta afirmativa y sus detalles se conservaron.'];
    }
    
    //---------- Verificamos si el tipo es "input" ----------
    if ($data['idTipo'] == 'input') {
    // Llamamos a la función que actualiza el dato y capturamos el resultado
    $resultado = $this->editarCuestionarioDato($idPaciente, $idRespuesta, $idTema, $data['detalle']);
    return $resultado;
    }

    //---------- Verificamos si el tipo es "select" ----------
    if ($data['idTipo'] == 'select') {

    if ($data['detalle'] == 'No') {
    $resultado = $this->editarCuestionarioSelect($idPaciente, $idRespuesta, $idTema);
    } elseif ($data['detalle'] == 'Si') {
    $resultado = $this->editarCuestionarioDato($idPaciente, $idRespuesta, $idTema, $data['detalle']);
    } else {
    return array('resultado' => 400, 'mensaje' => 'Detalle no válido');
    }
        
    }
    
    return $resultado ?? array('resultado' => 400, 'mensaje' => 'Tipo de respuesta no válido.');
    }
    
}
