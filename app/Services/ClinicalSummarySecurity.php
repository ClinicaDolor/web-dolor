<?php
namespace App\Services;

final class ClinicalSummarySecurity
{
    public static function enabled(): bool
    {
        return self::flag('CLINICAL_AI_SUMMARY_ENABLED', false) || self::syntheticMode();
    }

    public static function syntheticMode(): bool
    {
        return self::flag('CLINICAL_AI_SYNTHETIC_MODE', false);
    }

    public static function externalEnabled(): bool
    {
        return self::flag('CLINICAL_AI_EXTERNAL_ENABLED', false);
    }

    public static function csrfToken(int $patientId): string
    {
        return hash_hmac('sha256', 'clinical-summary:' . $patientId, (string)($_ENV['APP_KEY'] ?? ''));
    }

    public static function validCsrf(int $patientId, ?string $token): bool
    {
        return is_string($token) && hash_equals(self::csrfToken($patientId), $token);
    }

    /** Token bound to the authenticated JWT cookie and patient scope. */
    public static function captureCsrfToken(int $patientId): string
    {
        $sessionCookie = (string)($_COOKIE['CLINICAJWT'] ?? '');
        if ($sessionCookie === '') return '';
        return hash_hmac('sha256', 'm03-capture:' . $patientId . ':' . hash('sha256', $sessionCookie), (string)($_ENV['APP_KEY'] ?? ''));
    }

    public static function validCaptureCsrf(int $patientId, ?string $token): bool
    {
        $expected = self::captureCsrfToken($patientId);
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    private static function flag(string $name, bool $default): bool
    {
        $value = $_ENV[$name] ?? getenv($name);
        if ($value === false || $value === null || $value === '') return $default;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
