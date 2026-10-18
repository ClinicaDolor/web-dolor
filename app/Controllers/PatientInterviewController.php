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
            'nativeSttEnabled' => ClinicalSummarySecurity::nativeSpeechEnabled(),
            'sidebar' => $sidebar->render(),
        ]);
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
                $m02State = (new ConversationalM02Adapter())->read((int)$patientId);
                $proposal['write_enabled'] = (bool)$m02State['write_enabled'];
                $proposal['direct_save'] = ($proposal['kind'] ?? '') === 'm02_read_only_proposal'
                    && ($proposal['direct_save'] ?? false) === true
                    && $proposal['write_enabled'];
                $proposal['can_confirm'] = $proposal['direct_save'];
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
                $proposal = $this->conversationService()->interpretM06($utterance, $context);
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
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M02.', '', ['record' => $result['record'] ?? null, 'finalized' => $result['finalized'] ?? false]);
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
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M05.', '', ['record' => $result['record'] ?? null, 'version' => $result['version'] ?? null, 'next_question' => $result['next_question'] ?? null, 'next_disease' => $result['next_disease'] ?? null, 'finalized' => $result['finalized'] ?? false]);
    }

    public function captureM06(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true); $data = is_array($data) ? $data : [];
        $patientId = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if ($patientId === false || !is_array($data['proposal'] ?? null)) { echo HttpMethod::jsonResponse(422, false, 'Propuesta M06 no válida.'); return; }
        $authGuard = new AuthMiddleware('historia-clinica'); $auth = $authGuard->authPermisos(); $authGuard->assertPatientAccess((int)$patientId, $auth);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$patientId, is_scalar($csrf) ? (string)$csrf : null)) { echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.'); return; }
        $expectedVersion = is_scalar($data['expectedVersion'] ?? null) ? (string)$data['expectedVersion'] : null;
        $result = (new ConversationalM06Adapter())->capture((int)$patientId, $data['proposal'], $expectedVersion); $result = $this->finalizeAfterCapture((int)$patientId, 6, $result); $status=(int)($result['resultado']??422); if(!in_array($status,[200,403,409,422,500],true))$status=422;
        echo HttpMethod::jsonResponse($status,$status===200,$result['mensaje']??'No fue posible guardar M06.','',['record'=>$result['record']??null,'version'=>$result['version']??null,'finalized'=>$result['finalized']??false]);
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
        $status = (int)($result['resultado'] ?? 422);
        if (!in_array($status, [200, 403, 409, 422, 500], true)) $status = 422;
        echo HttpMethod::jsonResponse($status, $status === 200, $result['mensaje'] ?? 'No fue posible guardar M07.', '', [
            'code' => $result['code'] ?? null,
            'version' => $result['version'] ?? null,
            'pending_items' => $result['pending_items'] ?? null,
            'readback' => $result['readback'] ?? null,
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
        if (!$allowFinalization) {
            return array_merge($result, [
                'finalized' => false,
                'finalization_attempted' => false,
            ]);
        }
        $finalization = (new ConversationalModuleFinalizationService())->finalizeIfComplete($patientId, $moduleId);
        if ((int)($finalization['resultado'] ?? 500) !== 200) {
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
