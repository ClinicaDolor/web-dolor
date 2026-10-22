<?php

namespace App\Controllers;

use App\Helpers\Sidebar;
use App\Middleware\AuthMiddleware;
use App\Models\AntecedentesNoPatologicosModel;
use App\Models\PacienteModel;
use App\Services\ClinicalSummarySecurity;
use App\Services\ConversationalInterviewService;
use App\Services\ConversationalM01Adapter;
use App\Services\ConversationalM02Adapter;
use App\Services\ConversationalM04Adapter;
use App\Services\ConversationalM05Adapter;
use App\Services\ConversationalM06Adapter;
use App\Services\ConversationalM07Adapter;
use App\Services\ConversationalM09Adapter;
use App\Services\ConversationalM03Adapter;
use App\Services\ConversationalM09MapAdapter;
use App\Services\ConversationalM08TreatmentAdapter;
use App\Services\LocalDictationTicketService;
use App\Services\ConversationalModuleFinalizationService;
use App\Core\HttpMethod;

final class PatientInterviewController extends BaseController
{
    public function __construct(private ?ConversationalInterviewService $conversation = null) {}

    public function index(): void
    {
        // La vista contiene estado clínico autenticado y código inline de la entrevista.
        // No debe quedar almacenada en cachés compartidas ni en el historial del navegador.
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $patientId = (int)$auth['id_usuario'];
        $patient = new PacienteModel($patientId);
        $sidebar = new Sidebar();
        (new SidebarController())->configureSidebar('PACIENTE', 'historia-clinica', $sidebar);
        $sidebar->setActivarItem('Entrevista Conversacional');

        $m03State = (new ConversationalM03Adapter())->read($patientId);
        $m01State = (new ConversationalM01Adapter())->read($patientId);
        $m02State = (new ConversationalM02Adapter())->read($patientId);
        $m04State = (new ConversationalM04Adapter())->read($patientId);
        $m05State = (new ConversationalM05Adapter())->read($patientId);
        $m06State = (new ConversationalM06Adapter())->read($patientId);
        $m07State = (new ConversationalM07Adapter())->read($patientId, (int)$auth['id_clinica'], 'patient');
        $m08State = (new ConversationalM08TreatmentAdapter())->read($patientId);
        $m09State = (new ConversationalM09Adapter())->read($patientId);
        $m09MapState = (new ConversationalM09MapAdapter())->read($patientId);
        $questions = $m03State['questions'];
        $conversationContract = $this->conversationService()->buildUnifiedContract([
            'M01' => $m01State,
            'M02' => $m02State,
            'M03' => $m03State,
            'M04' => $m04State,
            'M05' => $m05State,
            'M06' => $m06State,
            'M07' => $m07State,
            'M08' => $m08State,
            'M09' => $m09State,
            'M09Map' => $m09MapState,
        ]);
        $this->view('/paciente/entrevista.php', [
            'title' => 'Entrevista Conversacional',
            'datos' => $auth,
            'idPaciente' => $patientId,
            'nombre' => $patient->getNombreCompleto(),
            'questions' => $questions,
            'm03' => $m03State,
            'm01' => $m01State,
            'm02' => $m02State,
            'm04' => $m04State,
            'm05' => $m05State,
            'm06' => $m06State,
            'm07' => $m07State,
            'm08' => $m08State,
            'm09' => $m09State,
            'm09Map' => $m09MapState,
            'conversationContract' => $conversationContract,
            'csrf' => ClinicalSummarySecurity::captureCsrfToken($patientId),
            'sidebar' => $sidebar->render(),
        ]);
    }

    /** Read-only M02 answer review for the authenticated patient. */
    public function previousResponses(): void
    {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $patientId = (int)($auth['id_usuario'] ?? 0);
        $authGuard->assertPatientAccess($patientId, $auth);

        $sidebar = new Sidebar();
        (new SidebarController())->configureSidebar('PACIENTE', 'historia-clinica', $sidebar);
        $sidebar->setActivarItem('Entrevista Conversacional');

        $m02Adapter = new ConversationalM02Adapter();
        $m02State = $m02Adapter->read($patientId);
        $contract = $this->conversationService()->buildUnifiedContract(['M02' => $m02State]);
        $questions = $contract['modules']['M02']['questions'] ?? [];
        $recordsByDisease = [];
        foreach (($m02State['records'] ?? []) as $record) {
            if (is_array($record) && is_string($record['disease'] ?? null)) $recordsByDisease[$record['disease']] = $record;
        }

        $entries = [];
        foreach ($questions as $question) {
            $disease = (string)($question['question_id'] ?? '');
            $record = $recordsByDisease[$disease] ?? null;
            if (($question['module_id'] ?? null) !== 'M02'
                || ($question['response']['persisted'] ?? false) !== true
                || !is_array($record)
                || (int)($record['id'] ?? 0) < 1) {
                continue;
            }
            $structured = ($record['relatives'] ?? []) !== [] || ($record['invalid_relatives'] ?? []) !== [];
            $invalidStructured = ($record['invalid_relatives'] ?? []) !== [];
            $editBlockerCode = $m02Adapter->existingResponseEditBlockerCode($m02State, $record);
            $structuredEditable = $structured && !$invalidStructured && ($record['detail'] ?? null) === 'Si' && $editBlockerCode === null;
            $editable = $editBlockerCode === null;
            $answerInput = ($record['detail'] ?? null) === 'No'
                ? 'No'
                : (trim((string)($record['legacy_relative_text'] ?? '')) !== ''
                    ? (string)$record['legacy_relative_text']
                    : (($record['detail'] ?? null) === 'Si' ? '' : 'Sí'));
            $relativeLabels = [
                'madre' => 'Madre', 'padre' => 'Padre', 'hermana' => 'Hermana', 'hermano' => 'Hermano',
                'abuela' => 'Abuela', 'abuelo' => 'Abuelo', 'hija' => 'Hija', 'hijo' => 'Hijo', 'familia' => 'Otro familiar',
            ];
            $entries[] = [
                'stable_id' => (string)$question['stable_id'],
                'question' => (string)($question['original_question'] ?? $question['visible_text'] ?? ''),
                'disease' => $disease,
                'answer' => $this->formatM02CurrentAnswer($record),
                'answer_input' => $answerInput,
                'relatives' => array_values(array_filter($record['relatives'] ?? [], 'is_string')),
                'relative_options' => $relativeLabels,
                'structured_editable' => $structuredEditable,
                'diabetes_type' => (string)($record['diabetes_type'] ?? ''),
                'version' => (string)($record['version'] ?? ''),
                'editable' => $editable,
                'structured' => $structured,
                'edit_blocker' => match ($editBlockerCode) {
                    'M02_DATABASE_NOT_AUTHORIZED' => 'La edición no está autorizada en este entorno.',
                    'M02_RELATIVE_STORAGE_UNVERIFIED' => 'No se pudo verificar el almacenamiento de parentescos; esta respuesta se mantiene en solo lectura.',
                    'M02_QUESTION_NOT_SUPPORTED' => 'Esta pregunta histórica no tiene un editor M02 compatible en esta pantalla.',
                    'M02_DUPLICATE_DISEASE_ROWS' => 'Hay registros duplicados para esta pregunta. No se permite editar hasta aclarar cuál es el vigente.',
                    'M02_RESPONSE_NOT_PERSISTED' => 'Solo se pueden editar respuestas ya registradas.',
                    'M02_RESPONSE_INCONSISTENT' => 'Esta respuesta requiere revisión antes de editarse.',
                    'M02_STRUCTURED_RELATION_EDIT_UNSUPPORTED' => 'Esta respuesta conserva parentescos estructurados incompatibles con una edición segura y se mantiene en solo lectura.',
                    default => $structuredEditable ? 'Actualiza los parentescos seleccionados y la descripción opcional; se guardan juntos.' : '',
                },
            ];
        }

        $this->view('/paciente/respuestas-previas.php', [
            'title' => 'Respuestas previas',
            'datos' => $auth,
            'idPaciente' => $patientId,
            'm02Finalized' => (bool)($m02State['finalized'] ?? false),
            'entries' => $entries,
            'csrf' => ClinicalSummarySecurity::captureCsrfToken($patientId),
            'sidebar' => $sidebar->render(),
        ]);
    }

    /** Update an existing M02 answer or its legacy free-text component; never creates a row. */
    public function updatePreviousM02(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            echo HttpMethod::jsonResponse(405, false, 'Método no permitido.');
            return;
        }
        $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentType !== 'application/json' || $contentLength < 1 || $contentLength > 8192) {
            echo HttpMethod::jsonResponse(400, false, 'Solicitud de edición no válida.');
            return;
        }
        $raw = file_get_contents('php://input', false, null, 0, 8193);
        if (!is_string($raw) || strlen($raw) > 8192) {
            echo HttpMethod::jsonResponse(413, false, 'La solicitud excede el tamaño permitido.');
            return;
        }
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            echo HttpMethod::jsonResponse(400, false, 'Solicitud de edición no válida.');
            return;
        }
        $allowedKeys = ['stable_id', 'expected_version', 'answer', 'diabetes_type', 'relatives'];
        if (!is_array($data) || array_diff(array_keys($data), $allowedKeys) !== []) {
            echo HttpMethod::jsonResponse(422, false, 'Solicitud de edición no válida.');
            return;
        }

        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $patientId = (int)($auth['id_usuario'] ?? 0);
        $authGuard->assertPatientAccess($patientId, $auth);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? null;
        if (!ClinicalSummarySecurity::validCaptureCsrf($patientId, is_string($csrf) ? $csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }

        $stableId = is_string($data['stable_id'] ?? null) ? trim($data['stable_id']) : '';
        $expectedVersion = is_string($data['expected_version'] ?? null) ? $data['expected_version'] : '';
        $answer = is_string($data['answer'] ?? null) ? trim($data['answer']) : '';
        $submittedType = is_string($data['diabetes_type'] ?? null) ? trim($data['diabetes_type']) : '';
        if ($stableId === '' || strlen($stableId) > 180 || mb_strlen($answer, 'UTF-8') > 2000
            || !preg_match('/^[a-f0-9]{64}$/', $expectedVersion)) {
            echo HttpMethod::jsonResponse(422, false, 'La respuesta debe tener como máximo 2000 caracteres.');
            return;
        }

        $adapter = new ConversationalM02Adapter();
        $state = $adapter->read($patientId);
        $contract = $this->conversationService()->buildUnifiedContract(['M02' => $state]);
        $question = $contract['question_index'][$stableId] ?? null;
        if (!is_array($question) || ($question['module_id'] ?? null) !== 'M02'
            || ($question['response']['persisted'] ?? false) !== true) {
            echo HttpMethod::jsonResponse(404, false, 'La respuesta M02 ya no está disponible para edición. Recarga la página.');
            return;
        }
        $disease = (string)($question['question_id'] ?? '');
        $record = null;
        foreach (($state['records'] ?? []) as $candidate) {
            if (($candidate['disease'] ?? null) === $disease) { $record = $candidate; break; }
        }
        if (!is_array($record) || (int)($record['id'] ?? 0) < 1) {
            echo HttpMethod::jsonResponse(404, false, 'La respuesta M02 ya no está disponible para edición. Recarga la página.');
            return;
        }
        $structuredCurrent = ($record['relatives'] ?? []) !== [];
        $targetRelatives = null;
        if ($structuredCurrent) {
            $targetRelatives = ConversationalM02Adapter::normalizeStructuredRelatives($data['relatives'] ?? null);
            if ($targetRelatives === null || $targetRelatives === [] || ($record['detail'] ?? null) !== 'Si') {
                echo HttpMethod::jsonResponse(422, false, 'Selecciona al menos un parentesco válido para conservar la respuesta afirmativa.');
                return;
            }
        } elseif (array_key_exists('relatives', $data) || $answer === '') {
            echo HttpMethod::jsonResponse(422, false, 'Completa una respuesta M02 válida antes de guardar.');
            return;
        }
        $editBlocker = $adapter->existingResponseEditBlockerCode($state, $record);
        if ($editBlocker !== null) {
            echo HttpMethod::jsonResponse(409, false, 'Esta respuesta no cumple las condiciones de edición M02. No se modificó.');
            return;
        }
        if ($submittedType !== '' && $submittedType !== '__KEEP__'
            && ($disease !== 'Diabetes Mellitus' || !in_array($submittedType, ['Tipo 1', 'Tipo 2', 'Gestacional'], true))) {
            echo HttpMethod::jsonResponse(422, false, 'El tipo de diabetes seleccionado no es válido.');
            return;
        }
        $context = [
            'detail' => $record['detail'] ?? null,
            'relative_description' => $record['legacy_relative_text'] ?? '',
            'diabetes_type' => $record['diabetes_type'] ?? null,
        ];
        if ($structuredCurrent) {
            $proposal = [
                'kind' => 'm02_read_only_proposal',
                'disease' => $disease,
                'detail' => 'Si',
                'relative_description' => $answer,
                'relatives' => $targetRelatives,
                'diabetes_type' => (string)($record['diabetes_type'] ?? ''),
            ];
        } else {
            try {
                $proposal = $adapter->interpret($disease, $answer, $context);
            } catch (\InvalidArgumentException) {
                echo HttpMethod::jsonResponse(422, false, 'La respuesta no cumple el formato permitido para M02.');
                return;
            }
            if (($proposal['kind'] ?? null) !== 'm02_read_only_proposal') {
                echo HttpMethod::jsonResponse(422, false, (string)($proposal['clarification'] ?? 'Aclara la respuesta antes de guardar.'));
                return;
            }
        }
        if ($submittedType !== '' && $submittedType !== '__KEEP__') {
            if (($proposal['detail'] ?? null) !== 'Si') {
                echo HttpMethod::jsonResponse(422, false, 'El tipo de diabetes solo puede acompañar una respuesta afirmativa.');
                return;
            }
            $proposal['diabetes_type'] = $submittedType;
        }

        $result = $adapter->editExistingResponse($patientId, $disease, $proposal, $expectedVersion);
        $status = (int)($result['resultado'] ?? 500);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 500;
        $readback = ($result['readback'] ?? false) === true;
        $ok = $status === 200 && $readback && is_array($result['record'] ?? null);
        $extra = ['readback' => $readback];
        if ($ok) {
            $saved = $result['record'];
            $extra['answer'] = $this->formatM02CurrentAnswer($saved);
            $extra['diabetes_type'] = (string)($saved['diabetes_type'] ?? '');
            $extra['version'] = (string)($saved['version'] ?? '');
            $extra['relatives'] = array_values(array_filter($saved['relatives'] ?? [], 'is_string'));
        }
        echo HttpMethod::jsonResponse($status, $ok, (string)($result['mensaje'] ?? 'No fue posible actualizar la respuesta M02.'), '', $extra);
    }

    private function formatM02CurrentAnswer(array $record): string
    {
        $detail = (string)($record['detail'] ?? '');
        $legacyText = trim((string)($record['legacy_relative_text'] ?? ''));
        $relatives = array_values(array_filter(array_merge($record['relatives'] ?? [], $record['invalid_relatives'] ?? []), 'is_string'));
        $context = [];
        if ($legacyText !== '') $context[] = $legacyText;
        if ($relatives !== []) $context[] = 'parentescos registrados: ' . implode(', ', $relatives);
        if ($detail === 'No') return $context === [] ? 'No' : 'No — también se conserva ' . implode('; ', $context);
        if (in_array($detail, ['Si', 'Sí'], true)) {
            if ($context !== []) return 'Sí — ' . implode('; ', $context);
            return 'Sí';
        }
        $storedDetail = trim($detail) !== '' ? $detail : 'Respuesta registrada';
        return $context === [] ? $storedDetail : $storedDetail . ' — ' . implode('; ', $context);
    }

    /** Issues a one-use, 30-second ticket for local-only microphone streaming. */
    public function issueDictationTicket(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');

        $expectedOrigin = $_SERVER['CLINICA_VOSK_ORIGIN'] ?? null;
        $root = $_SERVER['CLINICA_VOSK_ROOT'] ?? null;
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $fetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
        $allowedHosts = [
            'https://clinica.test' => 'clinica.test',
            'https://clinica.test:8443' => 'clinica.test:8443',
        ];
        if (!is_string($expectedOrigin)
            || !isset($allowedHosts[$expectedOrigin])
            || !$https
            || $host !== $allowedHosts[$expectedOrigin]
            || $origin !== $expectedOrigin
            || $fetchSite !== 'same-origin'
            || !is_string($root)
            || $root === '') {
            echo HttpMethod::jsonResponse(403, false, 'Dictado local no disponible en este entorno.');
            return;
        }
        if (strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? ''))) !== 'application/json' || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
            echo HttpMethod::jsonResponse(400, false, 'Solicitud de dictado no válida.');
            return;
        }

        $raw = file_get_contents('php://input', false, null, 0, 4097);
        $data = json_decode(is_string($raw) ? $raw : '', true);
        if (!is_array($data) || count($data) !== 3) {
            echo HttpMethod::jsonResponse(400, false, 'Solicitud de dictado no válida.');
            return;
        }
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $stableId = is_string($data['stable_id'] ?? null) ? trim($data['stable_id']) : '';
        $contractVersion = is_string($data['contract_version'] ?? null) ? $data['contract_version'] : '';
        if ($patientId === false || $stableId === '' || $contractVersion === '') {
            echo HttpMethod::jsonResponse(422, false, 'Pregunta no disponible para dictado.');
            return;
        }

        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        if ((int)$auth['id_usuario'] !== (int)$patientId) {
            echo HttpMethod::jsonResponse(403, false, 'No autorizado.');
            return;
        }
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? null;
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_string($csrf) ? $csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }

        try {
            $contract = $this->buildInterviewContractForPatient((int)$patientId, (int)$auth['id_clinica']);
            $question = $contract['question_index'][$stableId] ?? null;
            $moduleId = is_array($question) ? (string)($question['module_id'] ?? '') : '';
            $module = $contract['modules'][$moduleId] ?? null;
            $responseStatus = is_array($question['response'] ?? null) ? (string)($question['response']['status'] ?? 'pending') : 'pending';
            $activeQuestion = is_array($module['conversation_state'] ?? null) ? ($module['conversation_state']['stable_id'] ?? null) : null;
            $pendingQuestionAuthorized = $responseStatus === 'pending'
                && is_string($activeQuestion)
                && hash_equals($activeQuestion, $stableId);
            $savedQuestionAuthorized = $moduleId === 'M02'
                && is_array($module)
                && $responseStatus === 'documented'
                && is_array($question['response'] ?? null)
                && ($question['response']['persisted'] ?? false) === true
                && $this->isEditableM02SavedQuestion((int)$patientId, $question, $module);
            if (!is_array($question)
                || !is_array($module)
                || $moduleId !== 'M02'
                || ($module['conversation_state']['state'] ?? '') !== 'ANSWERABLE'
                || ($module['state']['write_enabled'] ?? false) !== true
                || ($question['write_enabled'] ?? false) !== true
                || (!$pendingQuestionAuthorized && !$savedQuestionAuthorized)
                || !hash_equals((string)$contract['version'], $contractVersion)) {
                echo HttpMethod::jsonResponse(409, false, 'La pregunta ya no está disponible para dictado; revisa la entrevista.');
                return;
            }

            $rootPath = rtrim($root, "\\/");
            $keyPath = $rootPath . DIRECTORY_SEPARATOR . 'ticket.key';
            if (!is_file($keyPath) || is_link($keyPath) || filesize($keyPath) !== 32) {
                echo HttpMethod::jsonResponse(503, false, 'El dictado local no está preparado. Puedes responder por escrito.');
                return;
            }
            $secret = file_get_contents($keyPath);
            if (!is_string($secret) || strlen($secret) !== 32) {
                echo HttpMethod::jsonResponse(503, false, 'El dictado local no está preparado. Puedes responder por escrito.');
                return;
            }
            $jwt = is_string($_COOKIE['CLINICAJWT'] ?? null) ? $_COOKIE['CLINICAJWT'] : '';
            $issued = (new LocalDictationTicketService($secret))->issue((int)$patientId, $jwt, $stableId, $expectedOrigin);
            echo HttpMethod::jsonResponse(200, true, 'Autorización local de dictado lista.', '', [
                'ticket' => $issued['ticket'],
                'expires_at' => $issued['expires_at'],
                'websocket_url' => 'wss://clinica.test:8766/dictation',
                'contract_version' => $contract['version'],
            ]);
        } catch (\Throwable) {
            echo HttpMethod::jsonResponse(503, false, 'El dictado local no está disponible. Puedes responder por escrito.');
        }
    }

    private function buildInterviewContractForPatient(int $patientId, int $clinicId): array
    {
        return $this->conversationService()->buildUnifiedContract([
            'M01' => (new ConversationalM01Adapter())->read($patientId),
            'M02' => (new ConversationalM02Adapter())->read($patientId),
            'M03' => (new ConversationalM03Adapter())->read($patientId),
            'M04' => (new ConversationalM04Adapter())->read($patientId),
            'M05' => (new ConversationalM05Adapter())->read($patientId),
            'M06' => (new ConversationalM06Adapter())->read($patientId),
            'M07' => (new ConversationalM07Adapter())->read($patientId, $clinicId, 'patient'),
            'M08' => (new ConversationalM08TreatmentAdapter())->read($patientId),
            'M09' => (new ConversationalM09Adapter())->read($patientId),
            'M09Map' => (new ConversationalM09MapAdapter())->read($patientId),
        ]);
    }

    /** A previously saved M02 answer may use dictation only when the same read model permits editing it. */
    private function isEditableM02SavedQuestion(int $patientId, array $question, array $module): bool
    {
        if ($patientId < 1
            || ($question['module_id'] ?? null) !== 'M02'
            || !is_scalar($question['question_id'] ?? null)
            || ($module['state']['write_enabled'] ?? false) !== true
            || ($module['state']['finalized'] ?? true) === true
            || ($module['conversation_state']['state'] ?? '') !== 'ANSWERABLE') {
            return false;
        }

        $disease = (string)$question['question_id'];
        $state = (new ConversationalM02Adapter())->read($patientId);
        if (($state['write_enabled'] ?? false) !== true || ($state['finalized'] ?? true) === true) return false;

        foreach (($state['records'] ?? []) as $record) {
            if (!is_array($record) || (string)($record['disease'] ?? '') !== $disease) continue;
            if ((int)($record['id'] ?? 0) < 1 || ($record['status'] ?? '') !== 'documented') return false;
            $legacyText = (string)($record['legacy_relative_text'] ?? $record['relative_description'] ?? '');
            $structured = ($record['relatives'] ?? []) !== [] || ($record['invalid_relatives'] ?? []) !== [];
            return !$structured || (($record['detail'] ?? '') === 'Si' && $legacyText !== '');
        }

        return false;
    }

    public function capture(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($patientId === false) {
            echo HttpMethod::jsonResponse(422, false, 'Paciente no válido.');
            return;
        }

        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess($patientId, $auth);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }

        $data['idPaciente'] = (int)$patientId;
        $data['idTema'] = filter_var($data['idTema'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $result = (new ConversationalM03Adapter())->capture((int)$patientId, [
            'kind' => 'm03_proposal', 'question_id' => (int)($data['idPregunta'] ?? 0), 'theme_id' => (int)$data['idTema'],
            'structured_value' => is_scalar($data['detalle'] ?? null) ? (string)$data['detalle'] : '',
            'semantic_state' => is_scalar($data['estadoRespuesta'] ?? null) ? (string)$data['estadoRespuesta'] : '',
        ], is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null);
        $result = $this->finalizeAfterCapture((int)$patientId, 3, $result);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 400, 403, 404, 409, 422, 500], true)) {
            $status = 422;
        }
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar la respuesta.', '', [
            'version' => $result['version'] ?? null,
            'readback' => $result['readback'] ?? false,
            'semantic_state' => $result['semantic_state'] ?? null,
            'code' => $result['code'] ?? null,
            'finalized' => $result['finalized'] ?? false,
        ]);
    }

    public function captureDirect(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $questionId = filter_var($data['idPregunta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $value = is_scalar($data['detalle'] ?? null) ? trim((string)$data['detalle']) : '';
        $saveAndContinue = filter_var($data['saveAndContinue'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($patientId === false || $questionId === false || $value === '' || !$saveAndContinue) {
            echo HttpMethod::jsonResponse(422, false, 'La captura directa requiere una acción explícita de guardar y continuar.', '', ['code' => 'DIRECT_EXPLICIT_SAVE_REQUIRED']);
            return;
        }

        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }

        $directMode = strtolower(is_scalar($data['directMode'] ?? null) ? (string)$data['directMode'] : 'numeric');
        $adapter = new ConversationalM03Adapter();
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $result = $directMode === 'literal'
            ? $adapter->captureDirectText((int)$patientId, (int)$questionId, $value, $expectedVersion)
            : $adapter->captureDirectNumeric((int)$patientId, (int)$questionId, $value, $expectedVersion);
        $result = $this->finalizeAfterCapture((int)$patientId, 3, $result);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 404, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar la respuesta.', '', [
            'mode' => $result['mode'] ?? null,
            'code' => $result['code'] ?? null,
            'readback' => $result['readback'] ?? false,
            'next_question_id' => $result['next_question_id'] ?? null,
            'version' => $result['version'] ?? null,
            'finalized' => $result['finalized'] ?? false,
        ]);
    }

    public function interpret(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $questionId = filter_var($data['idPregunta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $module = strtoupper((string)($data['modulo'] ?? 'M03'));
        $field = is_scalar($data['campo'] ?? null) ? (string)$data['campo'] : '';
        $utterance = is_scalar($data['utterance'] ?? null) ? trim((string)$data['utterance']) : '';
        $treatmentId = filter_var($data['tratamiento_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($patientId === false || (($module === 'M03' || $module === 'M05' || $module === 'M07') && $questionId === false) || ($module === 'M08' && $treatmentId === false) || (($module === 'M01' || $module === 'M07') && $field === '') || !in_array($module, ['M01', 'M02', 'M03', 'M04', 'M05', 'M06', 'M07', 'M08', 'M09'], true) || $utterance === '') {
            echo HttpMethod::jsonResponse(422, false, 'Indica una respuesta antes de continuar.');
            return;
        }

        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }

        try {
            if ($module === 'M01') {
                $proposal = (new ConversationalM01Adapter())->interpretField($field, $utterance);
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M01 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            if ($module === 'M02') {
                $disease = is_scalar($data['enfermedad'] ?? null) ? (string)$data['enfermedad'] : '';
                $context = is_array($data['context'] ?? null) ? $data['context'] : [];
                $proposal = $this->conversationService()->interpretM02($disease, $utterance, $context);
                $m02Adapter = new ConversationalM02Adapter();
                $m02State = $m02Adapter->read((int)$patientId);
                $proposal['write_enabled'] = (bool)$m02State['write_enabled'];
                $proposal['direct_save'] = ($proposal['kind'] ?? '') === 'm02_read_only_proposal'
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
                $proposal['replacement_confirmation_required'] = $proposal['direct_save']
                    && $m02Adapter->replacementConfirmationRequired($m02State, $disease, $proposal);
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M02 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal, 'context' => $proposal['context'] ?? null]);
                return;
            }
            if ($module === 'M04') {
                $proposal = (new ConversationalM04Adapter())->interpret($utterance);
                $m04State = (new ConversationalM04Adapter())->read((int)$patientId);
                $proposal['write_enabled'] = (bool)$m04State['write_enabled'];
                $proposal['direct_save'] = in_array(($proposal['kind'] ?? ''), ['m04_proposal', 'm04_negative_proposal'], true)
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M04 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            if ($module === 'M05') {
                $adapter = new ConversationalM05Adapter();
                $proposal = isset($data['enfermedad_id']) ? $adapter->interpretV2((int)$data['enfermedad_id'], $utterance) : $adapter->interpret((int)$questionId, $utterance);
                $m05State = (new ConversationalM05Adapter())->read((int)$patientId);
                $proposal['write_enabled'] = (bool)($m05State['write_enabled'] && ($proposal['target'] ?? 'v1') === 'v1' || $m05State['write_enabled'] && ($proposal['target'] ?? '') === 'v2');
                $proposal['direct_save'] = in_array(($proposal['kind'] ?? ''), ['m05_proposal', 'm05_v2_proposal'], true)
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M05 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            if ($module === 'M06') {
                $context = is_array($data['context'] ?? null) ? $data['context'] : [];
                $activeField = is_scalar($data['campo'] ?? null) ? trim((string)$data['campo']) : null;
                $proposal = $this->conversationService()->interpretM06($utterance, $context, $activeField);
                $m06State = (new ConversationalM06Adapter())->read((int)$patientId);
                $proposal['write_enabled'] = (bool)$m06State['write_enabled'];
                $proposal['direct_save'] = ($proposal['kind'] ?? '') === 'm06_proposal'
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M06 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            if ($module === 'M07') {
                $proposal = (new ConversationalM07Adapter())->interpret((int)$questionId, $field, $utterance);
                $m07State = (new ConversationalM07Adapter())->read((int)$patientId, (int)$auth['id_clinica'], 'patient');
                $proposal['write_enabled'] = (bool)$m07State['write_enabled'] && ($proposal['can_confirm'] ?? false);
                $proposal['can_confirm'] = (bool)($proposal['can_confirm'] ?? false) && $proposal['write_enabled'];
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M07 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            if ($module === 'M08') {
                $adapter = new ConversationalM08TreatmentAdapter();
                $proposal = $adapter->interpret((int)$patientId, (int)$treatmentId, $utterance);
                $m08State = $adapter->read((int)$patientId);
                $proposal['write_enabled'] = (bool)$m08State['write_enabled'];
                $proposal['direct_save'] = ($proposal['kind'] ?? '') === 'm08_treatment_proposal'
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M08 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            if ($module === 'M09') {
                $proposal = (new ConversationalM09Adapter())->interpret($utterance);
                $m09State = (new ConversationalM09Adapter())->read((int)$patientId);
                $proposal['write_enabled'] = (bool)$m09State['write_enabled'];
                $proposal['direct_save'] = ($proposal['kind'] ?? '') === 'm09_proposal'
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M09 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            if ($module === 'M03') {
                $proposal = $this->conversationService()->interpret((int)$patientId, (int)$questionId, $utterance);
                $m03State = (new ConversationalM03Adapter())->read((int)$patientId);
                $proposal['write_enabled'] = (bool)$m03State['write_enabled'];
                $proposal['direct_save'] = ($proposal['kind'] ?? '') === 'm03_proposal'
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
                echo HttpMethod::jsonResponse(200, true, 'Propuesta M03 preparada; no se guardó ningún dato.', '', ['proposal' => $proposal]);
                return;
            }
            $proposal = $this->conversationService()->interpret((int)$patientId, (int)$questionId, $utterance);
            echo HttpMethod::jsonResponse(200, true, 'Interpretación preparada; aún no se guardó la respuesta.', '', ['proposal' => $proposal]);
        } catch (\DomainException $e) {
            echo HttpMethod::jsonResponse(404, false, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            echo HttpMethod::jsonResponse(422, false, $e->getMessage());
        } catch (\Throwable) {
            echo HttpMethod::jsonResponse(503, false, 'No fue posible interpretar la respuesta. Inténtalo de nuevo.');
        }
    }

    private function conversationService(): ConversationalInterviewService
    {
        if ($this->conversation !== null) return $this->conversation;

        $trace = null;
        $traceEnabled = (string)($_ENV['CLINICAL_AI_TRACE'] ?? getenv('CLINICAL_AI_TRACE') ?: '') === '1'
            && ClinicalSummarySecurity::syntheticMode()
            && ClinicalSummarySecurity::externalEnabled();
        $traceFile = (string)($_ENV['CLINICAL_AI_TRACE_FILE'] ?? getenv('CLINICAL_AI_TRACE_FILE') ?: '');
        if ($traceEnabled && $traceFile !== '') {
            $trace = static function (array $event) use ($traceFile): void {
                $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
                @file_put_contents($traceFile, $line, FILE_APPEND | LOCK_EX);
            };
        }

        $traceId = (string)($_ENV['CLINICAL_AI_TRACE_ID'] ?? getenv('CLINICAL_AI_TRACE_ID') ?: '');
        return $this->conversation = new ConversationalInterviewService(null, null, $trace, $traceId !== '' ? $traceId : null);
    }

    public function captureM02(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $disease = is_scalar($data['enfermedad'] ?? null) ? trim((string)$data['enfermedad']) : '';
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        $fastNo = filter_var($data['fastNo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $finalize = filter_var($data['finalize'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($patientId === false || (!$finalize && ($disease === '' || (!$fastNo && !is_array($data['proposal'] ?? null))))) {
            echo HttpMethod::jsonResponse(422, false, 'Propuesta M02 no válida.');
            return;
        }
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }
        if ($finalize) {
            $finalization = (new ConversationalModuleFinalizationService())->finalizeIfComplete((int)$patientId, 2);
            $finalized = ($finalization['finalized'] ?? false) === true;
            $readback = ($finalization['readback'] ?? false) === true;
            $resultCode = (int)($finalization['resultado'] ?? 500);
            $ok = $resultCode === 200 && $finalized && $readback;
            if (!$ok && $resultCode === 200) $resultCode = 409;
            if (!in_array($resultCode, [200, 403, 409, 422, 500, 503], true)) $resultCode = 422;
            $extra = [
                'finalized' => $finalized,
                'finalization_readback' => $readback,
                'readback' => $readback,
                'finalization_code' => (string)($finalization['code'] ?? ''),
            ];
            if ($ok) {
                $extra['next_url'] = (new \App\Models\PacienteModulosModelo())->siguienteModuloPendiente((int)$patientId, 2) ?? '/historia-clinica';
            }
            echo HttpMethod::jsonResponse($resultCode, $ok, (string)($finalization['mensaje'] ?? 'No fue posible finalizar M02.'), '', $extra);
            return;
        }
        $proposal = $fastNo
            ? ['kind' => 'm02_read_only_proposal', 'disease' => $disease, 'detail' => 'No', 'relative' => null, 'diabetes_type' => null, 'fast_no' => true]
            : $data['proposal'];
        $result = (new ConversationalM02Adapter())->capture((int)$patientId, $disease, $proposal, $expectedVersion);
        $result = $this->finalizeAfterCapture((int)$patientId, 2, $result);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500, 503], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M02.', '', ['code' => $result['code'] ?? null, 'record' => $result['record'] ?? null, 'readback' => $result['readback'] ?? false, 'finalized' => $result['finalized'] ?? false]);
    }

    public function captureM04(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        $intent = is_scalar($data['intent'] ?? null) ? trim((string)$data['intent']) : '';
        $proposal = is_array($data['proposal'] ?? null) ? $data['proposal'] : null;
        if ($patientId === false || ($proposal === null && $intent !== 'FINISH_ADDITIONAL_SURGERIES') || ($intent !== '' && $intent !== 'FINISH_ADDITIONAL_SURGERIES')) {
            echo HttpMethod::jsonResponse(422, false, 'Propuesta M04 no válida.');
            return;
        }
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $adapter = new ConversationalM04Adapter();
        $allowFinalization = $intent === 'FINISH_ADDITIONAL_SURGERIES'
            || (($proposal['kind'] ?? null) === 'm04_negative_proposal');
        $result = $intent === 'FINISH_ADDITIONAL_SURGERIES'
            ? $adapter->finish((int)$patientId, $expectedVersion)
            : $adapter->capture((int)$patientId, $proposal, $expectedVersion);
        $result = $this->finalizeAfterCapture((int)$patientId, 4, $result, $allowFinalization);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M04.', '', [
            'record' => $result['record'] ?? null,
            'negative_declaration' => $result['negative_declaration'] ?? null,
            'version' => $result['version'] ?? null,
            'readback' => $result['readback'] ?? false,
            'request_finalization' => $result['request_finalization'] ?? false,
            'finish_intent' => $result['finish_intent'] ?? null,
            'codigo' => $result['codigo'] ?? null,
            'finalized' => $result['finalized'] ?? false,
            'finalization_attempted' => $result['finalization_attempted'] ?? false,
            'finalization_readback' => $result['finalization_readback'] ?? false,
        ]);
    }

    public function captureM05(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if ($patientId === false || !is_array($data['proposal'] ?? null)) { echo HttpMethod::jsonResponse(422, false, 'Propuesta M05 no válida.'); return; }
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) { echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.'); return; }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $adapter = new ConversationalM05Adapter();
        $result = (($data['proposal']['target'] ?? '') === 'v2')
            ? $adapter->captureV2((int)$patientId, $data['proposal'], $expectedVersion)
            : $adapter->capture((int)$patientId, $data['proposal'], $expectedVersion);
        $result = $this->finalizeAfterCapture((int)$patientId, 5, $result);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M05.', '', ['record' => $result['record'] ?? null, 'version' => $result['version'] ?? null, 'readback' => $result['readback'] ?? false, 'next_question' => $result['next_question'] ?? null, 'next_disease' => $result['next_disease'] ?? null, 'finalized' => $result['finalized'] ?? false]);
    }

    public function captureM06(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true); $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        $intent = is_scalar($data['intent'] ?? null) ? trim((string)$data['intent']) : '';
        $allowedIntents = ['FINISH_NO_CURRENT_MEDICATION', 'FINISH_ADDITIONAL_MEDICATIONS'];
        $proposal = is_array($data['proposal'] ?? null) ? $data['proposal'] : null;
        $omission = is_array($data['omission'] ?? null) ? $data['omission'] : null;
        $hasOmission = $omission !== null;
        if ($patientId === false
            || ($proposal === null && !$hasOmission && !in_array($intent, $allowedIntents, true))
            || ($intent !== '' && !in_array($intent, $allowedIntents, true))
            || ($hasOmission && ($proposal !== null || $intent !== ''))) {
            echo HttpMethod::jsonResponse(422, false, 'Propuesta o intención M06 no válida.');
            return;
        }
        $authGuard = new AuthMiddleware('historia-clinica'); $auth = $authGuard->authPermisos(); $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) { echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.'); return; }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        if ($hasOmission) {
            $recordId = filter_var($omission['recordId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $field = is_scalar($omission['field'] ?? null) ? trim((string)$omission['field']) : '';
            $state = is_scalar($omission['state'] ?? null) ? strtoupper(trim((string)$omission['state'])) : '';
            if ($recordId === false || $field === '' || !in_array($state, ['UNKNOWN', 'SKIPPED'], true)) {
                echo HttpMethod::jsonResponse(422, false, 'El estado de omisión M06 no es válido.');
                return;
            }
            $result = (new ConversationalM06Adapter())->captureOmission((int)$patientId, (int)$recordId, $field, $state, $expectedVersion);
            $status = (int)($result['resultado'] ?? 422);
            if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
            echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar el estado M06.', '', [
                'record' => $result['record'] ?? null,
                'omissions' => $result['omissions'] ?? [],
                'omission_state' => $state,
                'version' => $result['version'] ?? null,
                'readback' => $result['readback'] ?? false,
                'next_action' => $result['next_action'] ?? null,
                'next_field' => $result['next_field'] ?? null,
                'pending_record' => $result['pending_record']['id'] ?? null,
                'missing' => $result['missing'] ?? [],
            ]);
            return;
        }
        if ($intent !== '') {
            $adapter = new ConversationalM06Adapter();
            $state = $adapter->read((int)$patientId);
            if ($expectedVersion !== null && !hash_equals((string)($state['version'] ?? ''), $expectedVersion)) {
                echo HttpMethod::jsonResponse(409, false, 'M06 cambió mientras se revisaba. Recarga antes de continuar.', '', ['code' => 'M06_VERSION_CONFLICT']);
                return;
            }
            $records = is_array($state['records'] ?? null) ? $state['records'] : [];
            if ($intent === 'FINISH_NO_CURRENT_MEDICATION' && $records !== []) {
                echo HttpMethod::jsonResponse(409, false, 'La declaración inicial de ausencia sólo puede realizarse antes de registrar medicación.', '', ['code' => 'M06_INITIAL_NEGATIVE_NOT_APPLICABLE']);
                return;
            }
            if ($intent === 'FINISH_ADDITIONAL_MEDICATIONS' && $records === []) {
                echo HttpMethod::jsonResponse(409, false, 'Registra al menos un medicamento antes de finalizar esta captura.', '', ['code' => 'M06_NO_MEDICATION_TO_FINISH']);
                return;
            }
            $finalization = (new ConversationalModuleFinalizationService())->finalizeIfComplete(
                (int)$patientId,
                6,
                $intent === 'FINISH_NO_CURRENT_MEDICATION'
            );
            $status = (int)($finalization['resultado'] ?? 500);
            $finalized = ($finalization['finalized'] ?? false) === true;
            $readback = ($finalization['readback'] ?? false) === true;
            $ok = $status === 200 && $finalized && $readback;
            if ($status === 200 && !$ok) $status = 409;
            if (!in_array($status, [200, 403, 409, 422, 500, 503], true)) $status = 422;
            echo HttpMethod::jsonResponse($status, $ok, (string)($finalization['mensaje'] ?? 'No fue posible finalizar M06.'), '', [
                'version' => $state['version'] ?? null,
                'readback' => $readback,
                'finalized' => $finalized,
                'finalization_attempted' => $finalization['finalization_attempted'] ?? false,
                'finalization_readback' => $readback,
                'code' => $finalization['code'] ?? null,
                'pending_fields' => $finalization['pending_fields'] ?? [],
                'intent' => $intent,
            ]);
            return;
        }

        // Saving a medication is deliberately separate from module closure.
        // The patient must be able to add another medication before choosing
        // the explicit FINISH_ADDITIONAL_MEDICATIONS intent.
        if (is_array($proposal)) {
            $recordId = filter_var($data['recordId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($recordId !== false && $recordId !== null) $proposal['record_id'] = (int)$recordId;
            $proposal['create_new'] = filter_var($data['createNew'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $result = (new ConversationalM06Adapter())->capture((int)$patientId, $proposal, $expectedVersion);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M06.', '', [
            'record' => $result['record'] ?? null,
            'version' => $result['version'] ?? null,
            'readback' => $result['readback'] ?? false,
            'finalized' => false,
            'next_action' => $result['next_action'] ?? null,
            'pending_record' => $result['pending_record']['id'] ?? null,
            'missing' => $result['missing'] ?? [],
        ]);
    }

    public function captureM07(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if ($patientId === false || !is_array($data['items'] ?? null) || $data['items'] === []) {
            echo HttpMethod::jsonResponse(422, false, 'La captura M07 requiere respuestas explícitas.');
            return;
        }
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $result = (new ConversationalM07Adapter())->captureBatch((int)$patientId, $data['items'], $expectedVersion);
        // M07 capture must not implicitly trigger module finalization. Its
        // activation and clinical completion policy remain separately gated.
        $result = $this->finalizeAfterCapture((int)$patientId, 7, $result, false);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M07.', '', [
            'code' => $result['code'] ?? null,
            'version' => $result['version'] ?? null,
            'pending_items' => $result['pending_items'] ?? null,
            'readback' => $result['readback'] ?? null,
            'record' => $result['record'] ?? null,
            'finalized' => $result['finalized'] ?? false,
            'finalization_readback' => $result['finalization_readback'] ?? false,
        ]);
    }

    public function captureM09(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if ($patientId === false || !is_array($data['proposal'] ?? null)) { echo HttpMethod::jsonResponse(422, false, 'Propuesta M09 no válida.'); return; }
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) { echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.'); return; }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $result = (new ConversationalM09Adapter())->capture((int)$patientId, $data['proposal'], $expectedVersion);
        $result = $this->finalizeAfterCapture((int)$patientId, 9, $result);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M09.', '', ['evaluation' => $result['evaluation'] ?? null, 'version' => $result['version'] ?? null, 'finalized' => $result['finalized'] ?? false]);
    }

    public function captureM08(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if ($patientId === false || !is_array($data['proposal'] ?? null)) { echo HttpMethod::jsonResponse(422, false, 'Propuesta M08 no válida.'); return; }
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) { echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.'); return; }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $result = (new ConversationalM08TreatmentAdapter())->capture((int)$patientId, $data['proposal'], $expectedVersion);
        $result = $this->finalizeAfterCapture((int)$patientId, 8, $result);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M08.', '', ['item' => $result['item'] ?? null, 'version' => $result['version'] ?? null, 'finalized' => $result['finalized'] ?? false]);
    }

    public function captureM09Map(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $orientation = is_scalar($data['orientation'] ?? null) ? (string)$data['orientation'] : '';
        $image = is_scalar($data['image'] ?? null) ? (string)$data['image'] : '';
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if ($patientId === false || !in_array($orientation, ['frente', 'espalda'], true) || $image === '') { echo HttpMethod::jsonResponse(422, false, 'Confirmación de mapa no válida.'); return; }
        $authGuard = new AuthMiddleware('historia-clinica');
        $auth = $authGuard->authPermisos();
        $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) { echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.'); return; }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $result = (new ConversationalM09MapAdapter())->capture((int)$patientId, $orientation, $image, $expectedVersion);
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar el mapa.', '', ['orientation' => $result['orientation'] ?? null, 'image' => $result['image'] ?? null, 'version' => $result['version'] ?? null]);
    }

    private function finalizeAfterCapture(int $patientId, int $moduleId, array $result, bool $allowFinalization = true): array
    {
        if ((int)($result['resultado'] ?? 422) !== 200) return $result;
        if ($moduleId === 7 && (int)($result['pending_items'] ?? 1) > 0) {
            return array_merge($result, ['finalized' => false, 'finalization_attempted' => false, 'finalization_readback' => false]);
        }
        if (!$allowFinalization) {
            return array_merge($result, [
                'finalized' => false,
                'finalization_attempted' => false,
            ]);
        }
        $finalization = (new ConversationalModuleFinalizationService())->finalizeIfComplete($patientId, $moduleId);
        if ((int)($finalization['resultado'] ?? 500) !== 200) {
            if ($moduleId === 7) {
                return array_merge($result, [
                    'finalized' => false,
                    'finalization_attempted' => true,
                    'finalization_readback' => false,
                    'finalization' => $finalization,
                ]);
            }
            return array_merge($result, [
                'resultado' => (int)($finalization['resultado'] ?? 500),
                'mensaje' => (string)($finalization['mensaje'] ?? 'No fue posible finalizar el módulo.'),
                'finalized' => false,
                'finalization_attempted' => true,
                'finalization' => $finalization,
            ]);
        }
        return array_merge($result, [
            'finalized' => (bool)($finalization['finalized'] ?? false),
            'finalization_attempted' => true,
            'finalization_readback' => (bool)($finalization['readback'] ?? false),
            'finalization' => $finalization,
        ]);
    }
}
