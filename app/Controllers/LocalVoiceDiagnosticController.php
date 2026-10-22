<?php

namespace App\Controllers;

use App\Core\HttpMethod;
use App\Services\ClinicalSummarySecurity;
use App\Services\LocalDictationTicketService;
use App\Services\LocalVoiceDiagnosticPolicy;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class LocalVoiceDiagnosticController extends BaseController
{
    public function index(): void
    {
        $this->noStoreHeaders();
        $runtime = LocalVoiceDiagnosticPolicy::enabledRuntime();
        if ($runtime === null) { http_response_code(404); return; }
        $patientId = $this->authenticatedPatientId();
        if ($patientId === null) {
            header('Location: /historia-clinica/acceso', true, 302);
            return;
        }
        $csrf = ClinicalSummarySecurity::captureCsrfToken($patientId);
        if ($csrf === '') { http_response_code(403); return; }
        header("Content-Security-Policy: default-src 'self'; connect-src 'self' wss://clinica.test:8767; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: microphone=(self), camera=(), geolocation=()');
        $this->view('/paciente/diagnostico-voz.php', ['csrf' => $csrf]);
    }

    public function issueTicket(): void
    {
        $this->noStoreHeaders();
        header('Content-Type: application/json; charset=UTF-8');
        $runtime = LocalVoiceDiagnosticPolicy::enabledRuntime();
        if ($runtime === null || !LocalVoiceDiagnosticPolicy::validRequestOrigin($runtime)) {
            echo HttpMethod::jsonResponse(403, false, 'Diagnóstico local no disponible.');
            return;
        }
        $requestBody = file_get_contents('php://input', false, null, 0, 129);
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST'
            || strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? ''))) !== 'application/json'
            || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 128
            || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) !== 0
            || !is_string($requestBody) || $requestBody !== '') {
            echo HttpMethod::jsonResponse(400, false, 'Solicitud de diagnóstico no válida.');
            return;
        }
        $patientId = $this->authenticatedPatientId();
        if ($patientId === null) {
            echo HttpMethod::jsonResponse(401, false, 'Inicia sesión como paciente para continuar.');
            return;
        }
        $csrf = $_SERVER['HTTP_X_M03_CSRF'] ?? null;
        if (!ClinicalSummarySecurity::validCaptureCsrf($patientId, is_string($csrf) ? $csrf : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }
        $secret = @file_get_contents($runtime['ticket_key']);
        if (!is_string($secret) || strlen($secret) !== 32) {
            echo HttpMethod::jsonResponse(503, false, 'El diagnóstico de voz no está preparado.');
            return;
        }
        try {
            $jwt = (string)$_COOKIE['CLINICAJWT'];
            $issued = (new LocalDictationTicketService($secret))->issueDiagnostic($jwt, $runtime['origin']);
            echo HttpMethod::jsonResponse(200, true, 'Autorización local de diagnóstico lista.', '', [
                'ticket' => $issued['ticket'],
                'expires_at' => $issued['expires_at'],
                'websocket_url' => LocalVoiceDiagnosticPolicy::WEBSOCKET_URL,
            ]);
        } catch (\Throwable) {
            echo HttpMethod::jsonResponse(503, false, 'El diagnóstico de voz no está disponible.');
        }
    }

    /** Validate the application's signed patient session without opening PDO or reading patient records. */
    private function authenticatedPatientId(): ?int
    {
        $cookie = $_COOKIE['CLINICAJWT'] ?? null;
        $appKey = $_ENV['APP_KEY'] ?? getenv('APP_KEY');
        $appName = $_ENV['APP_NAME'] ?? getenv('APP_NAME');
        if (!is_string($cookie) || $cookie === '' || !is_string($appKey) || $appKey === '' || !is_string($appName) || $appName === '') return null;
        try {
            $claims = JWT::decode($cookie, new Key($appKey, 'HS256'));
        } catch (\Throwable) {
            return null;
        }
        $now = time();
        if (($claims->vista ?? null) !== 'historia-clinica'
            || ($claims->rol ?? null) !== 'Paciente'
            || ($claims->iss ?? null) !== $appName
            || !is_int($claims->id_usuario ?? null) || $claims->id_usuario < 1
            || !is_int($claims->id_clinica ?? null) || $claims->id_clinica < 1
            || !is_int($claims->exp ?? null) || $claims->exp <= $now
            || !is_int($claims->iat ?? null) || $claims->iat > $now) return null;
        return $claims->id_usuario;
    }

    private function noStoreHeaders(): void
    {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
