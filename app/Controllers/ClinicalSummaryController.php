<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ClinicalSummarySecurity;
use App\Services\ClinicalSummaryService;
use App\Services\ClinicalDetailService;
use App\Services\ClinicalSupplementService;
use App\Services\ClinicalNarrativeService;

final class ClinicalSummaryController extends BaseController
{
    public function generate($idPaciente): void
    {
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store, no-cache, must-revalidate');
        try {
            $id=filter_var($idPaciente,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if ($id===false) throw new \RuntimeException('PATIENT_INVALID');
            $auth=new AuthMiddleware('clinica'); $session=$auth->authPermisos();
            if (strcasecmp((string)($session['rol']??''),'Doctor')!==0) throw new \RuntimeException('ROLE_FORBIDDEN');
            $auth->assertPatientAccess($id,$session);
            $token=$_SERVER['HTTP_X_CLINICAL_SUMMARY_CSRF'] ?? ($_POST['csrf_token'] ?? null);
            if (!ClinicalSummarySecurity::validCsrf($id,$token)) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'CSRF_INVALID']); return; }
            $result=(new ClinicalSummaryService())->generate($id);
            $result['clinical_detail']=(new ClinicalDetailService())->build($id);
            $result['clinical_supplement']=(new ClinicalSupplementService())->forPatient($id);
            $result['clinical_narrative']=(new ClinicalNarrativeService())->build($result['clinical_detail'], $result['clinical_supplement']);
            unset($result['snapshot']);
            echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) { if (http_response_code() < 400) http_response_code(403); echo json_encode(['ok'=>false,'error'=>preg_replace('/[^A-Z0-9_]+/','_',strtoupper($e->getMessage() ?: 'SUMMARY_ERROR'))]); }
    }
}
