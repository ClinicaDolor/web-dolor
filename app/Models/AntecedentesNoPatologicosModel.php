<?php
namespace App\Models;
use App\Config\Database;
use App\Services\ConversationalInterviewService;

class AntecedentesNoPatologicosModel{
private $bd;
private ?array $m03SemanticColumns = null;

private const M03_SEMANTIC_STATES = ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'];

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

public function __construct(?\PDO $db = null){
$this->bd = $db ?: Database::getInstance();
}

    private function obtenerContextoRespuesta($idPaciente, $idRespuesta, $idTema): ?array
    {
    $sql = "SELECT r.id, r.respuesta, p.tipo
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
    $idPaciente = (int)($data['idPaciente'] ?? 0);
    $idPregunta = (int)($data['idPregunta'] ?? 0);
    $idTema = (int)($data['idTema'] ?? 0);
    $detalle = isset($data['detalle']) && is_scalar($data['detalle']) ? trim((string)$data['detalle']) : '';
    $semanticState = strtoupper(trim((string)($data['estadoRespuesta'] ?? '')));
    $origin = strtoupper(trim((string)($data['origenRespuesta'] ?? 'PATIENT_DECLARATION')));
    $expected = array_key_exists('expectedRespuesta', $data) && is_scalar($data['expectedRespuesta'])
        ? (string)$data['expectedRespuesta']
        : null;

    if ($idPaciente < 1 || $idPregunta < 1 || $idTema < 1) {
        return ['resultado' => 400, 'mensaje' => 'Datos de respuesta no válidos.'];
    }

    $question = $this->bd->prepare(
        'SELECT id, id_tema, tipo FROM pac_preguntas_modulo_3 WHERE id = :id AND id_tema = :tema LIMIT 1'
    );
    $question->execute([':id' => $idPregunta, ':tema' => $idTema]);
    $questionData = $question->fetch(\PDO::FETCH_ASSOC);
    if (!$questionData) {
        return ['resultado' => 404, 'mensaje' => 'La pregunta no pertenece al tema autorizado.'];
    }
    $semanticStateAvailable = $this->m03SemanticStateAvailable();
    $detectedUnknownState = ConversationalInterviewService::classifyM03Unknown($detalle);
    if ($semanticState === '' && $detectedUnknownState !== null) {
        $semanticState = $detectedUnknownState;
    }
    if ($questionData['tipo'] === 'select' && !in_array($detalle, ['Si', 'No'], true)) {
        if ($semanticState !== 'UNKNOWN' && $semanticState !== 'NOT_REMEMBERED') {
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
    if ($semanticState === 'NEGATIVE' && ($questionData['tipo'] !== 'select' || $detalle !== 'No')) {
        return ['resultado' => 422, 'mensaje' => 'La respuesta negativa no corresponde a esta pregunta.'];
    }
    if (in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) && !$semanticStateAvailable && $detalle === '') {
        return ['resultado' => 409, 'mensaje' => 'La respuesta desconocida requiere su valor textual canónico.', 'code' => 'M03_TEXTUAL_UNKNOWN_VALUE_REQUIRED', 'persist' => false];
    }
    if ($semanticStateAvailable) {
        $semanticDetail = in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true) ? '' : $detalle;
        return $this->persistSemanticResponse($idPaciente, $idPregunta, $idTema, $semanticDetail, $semanticState, $origin, $expected);
    }
    if ($detalle === '') {
        return ['resultado' => 409, 'mensaje' => 'La instalación aún no admite respuestas sin valor clínico.', 'code' => 'M03_SEMANTIC_STATE_SCHEMA_REQUIRED', 'persist' => false];
    }

    if ($questionData['tipo'] === 'select' && !in_array($detalle, ['Si', 'No'], true)) {
        return ['resultado' => 400, 'mensaje' => 'La opción seleccionada no es válida.'];
    }

    $context = $this->bd->prepare(
        'SELECT id, respuesta FROM pac_respuestas_paciente_modulo_3
         WHERE id_paciente = :paciente AND id_pregunta = :pregunta LIMIT 1'
    );
    $context->execute([':paciente' => $idPaciente, ':pregunta' => $idPregunta]);
    $current = $context->fetch(\PDO::FETCH_ASSOC);

    if (!$current) {
        if ($expected !== null && $expected !== '') {
            return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Recarga la pregunta antes de continuar.'];
        }
        $this->antecedentesNoPatologicos($idPaciente, $idPregunta);
        $context->execute([':paciente' => $idPaciente, ':pregunta' => $idPregunta]);
        $current = $context->fetch(\PDO::FETCH_ASSOC);
    }
    if (!$current) {
        return ['resultado' => 500, 'mensaje' => 'No fue posible preparar la respuesta clínica.'];
    }

    $currentValue = (string)$current['respuesta'];
    if ($expected !== null && $currentValue !== $expected) {
        return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Recarga la pregunta antes de continuar.'];
    }

    if ($questionData['tipo'] === 'select' && $detalle === 'No') {
        $dependent = $this->bd->prepare(
            "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3
             WHERE id_paciente = :paciente AND id != :respuesta
               AND id_pregunta IN (SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema = :tema)
               AND TRIM(COALESCE(respuesta, '')) <> ''"
        );
        $dependent->execute([
            ':paciente' => $idPaciente,
            ':respuesta' => (int)$current['id'],
            ':tema' => $idTema,
        ]);
        if ((int)$dependent->fetchColumn() > 0) {
            return ['resultado' => 409, 'mensaje' => 'Este cambio puede limpiar respuestas dependientes. Continúa en el formulario tradicional para revisarlo.'];
        }
    }

    return $this->editarCuestionarioModulo([
        'idPaciente' => $idPaciente,
        'idRespuesta' => (int)$current['id'],
        'idPregunta' => $idPregunta,
        'idTema' => $idTema,
        'idTipo' => (string)$questionData['tipo'],
        'detalle' => $detalle,
        'expectedRespuesta' => $currentValue,
    ]);
    }

    /**
     * Persists a semantic M03 state atomically once the additive schema is
     * present. Unknown states intentionally keep respuesta empty; the state
     * column, origin and timestamp carry the patient's declaration.
     */
    private function persistSemanticResponse(int $patientId, int $questionId, int $themeId, string $detail, string $state, string $origin, ?string $expected): array
    {
    try {
    $this->bd->beginTransaction();
    $lock = $this->bd->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $lookup = $this->bd->prepare('SELECT id, respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = :patient AND id_pregunta = :question ORDER BY id ASC LIMIT 2' . $lock);
    $lookup->execute([':patient' => $patientId, ':question' => $questionId]);
    $rows = $lookup->fetchAll(\PDO::FETCH_ASSOC);
    if (count($rows) > 1) {
        $this->bd->rollBack();
        return ['resultado' => 409, 'mensaje' => 'Existen respuestas M03 duplicadas; la corrección requiere revisión autorizada.', 'code' => 'M03_DUPLICATE_RESPONSE_ROWS', 'persist' => false];
    }
    $current = $rows[0] ?? null;
    $currentValue = (string)($current['respuesta'] ?? '');
    if ($expected !== null && $currentValue !== $expected) {
        $this->bd->rollBack();
        return ['resultado' => 409, 'mensaje' => 'La respuesta cambió mientras se capturaba. Recarga la pregunta antes de continuar.', 'code' => 'M03_VERSION_CONFLICT', 'persist' => false];
    }
    if ($state === 'NEGATIVE') {
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
    $timestamp = gmdate('Y-m-d H:i:s');
    if ($current === null) {
        $insert = $this->bd->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta, estado_respuesta, origen_respuesta, estado_respuesta_at) VALUES (:question, :patient, :detail, :state, :origin, :state_at)');
        $insert->execute([':question' => $questionId, ':patient' => $patientId, ':detail' => $detail, ':state' => $state, ':origin' => $origin, ':state_at' => $timestamp]);
    } else {
        $update = $this->bd->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = :detail, estado_respuesta = :state, origen_respuesta = :origin, estado_respuesta_at = :state_at WHERE id = :id AND id_paciente = :patient AND id_pregunta = :question');
        $update->execute([':detail' => $detail, ':state' => $state, ':origin' => $origin, ':state_at' => $timestamp, ':id' => (int)$current['id'], ':patient' => $patientId, ':question' => $questionId]);
    }
    $this->bd->commit();
    return ['resultado' => 200, 'mensaje' => 'Respuesta M03 guardada.', 'persist' => true, 'semantic_state' => $state, 'readback' => false];
    } catch (\Throwable) {
    if ($this->bd->inTransaction()) $this->bd->rollBack();
    return ['resultado' => 500, 'mensaje' => 'No fue posible guardar la respuesta M03.', 'code' => 'M03_SEMANTIC_PERSISTENCE_FAILED', 'persist' => false];
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
    value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
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
