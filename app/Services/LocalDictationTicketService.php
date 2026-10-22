<?php

namespace App\Services;

/** Short-lived, single-use credentials for the loopback-only local STT gateway. */
final class LocalDictationTicketService
{
    public const AUDIENCE = 'clinica-vosk-local-v1';
    public const DIAGNOSTIC_AUDIENCE = 'clinica-vosk-diagnostic-v1';
    public const DIAGNOSTIC_PURPOSE = 'local_voice_diagnostic';
    public const DIAGNOSTIC_ORIGIN = 'https://clinica.test:8443';
    public const TTL_SECONDS = 30;
    private const STABLE_ID_PATTERN = '/^M02(?::[A-Za-z0-9_-]+){1,4}$/D';
    private const ALLOWED_ORIGINS = ['https://clinica.test', 'https://clinica.test:8443'];

    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) !== 32) {
            throw new \InvalidArgumentException('DICTATION_TICKET_KEY_INVALID');
        }
    }

    public function issue(int $patientId, string $jwt, string $stableId, string $origin, ?int $now = null): array
    {
        if ($patientId < 1 || $jwt === '' || !preg_match(self::STABLE_ID_PATTERN, $stableId) || !in_array($origin, self::ALLOWED_ORIGINS, true)) {
            throw new \InvalidArgumentException('DICTATION_TICKET_CLAIMS_INVALID');
        }
        $issuedAt = $now ?? time();
        $claims = [
            'v' => 1,
            'aud' => self::AUDIENCE,
            'pid' => $patientId,
            'sid' => hash_hmac('sha256', $jwt, $this->secret),
            'question' => $stableId,
            'origin' => $origin,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::TTL_SECONDS,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $payload = self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $this->secret, true);
        return ['ticket' => $payload . '.' . self::base64UrlEncode($signature), 'expires_at' => $claims['exp']];
    }

    /** Diagnostic-only ticket: deliberately contains no patient or question identifier. */
    public function issueDiagnostic(string $jwt, string $origin, ?int $now = null): array
    {
        if ($jwt === '' || $origin !== self::DIAGNOSTIC_ORIGIN) {
            throw new \InvalidArgumentException('DICTATION_DIAGNOSTIC_CLAIMS_INVALID');
        }
        $issuedAt = $now ?? time();
        $claims = [
            'v' => 1,
            'aud' => self::DIAGNOSTIC_AUDIENCE,
            'purpose' => self::DIAGNOSTIC_PURPOSE,
            'sid' => hash_hmac('sha256', $jwt, $this->secret),
            'origin' => $origin,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::TTL_SECONDS,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $payload = self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $this->secret, true);
        return ['ticket' => $payload . '.' . self::base64UrlEncode($signature), 'expires_at' => $claims['exp']];
    }

    /** Test/debug verifier. Production gateway independently verifies the same HMAC envelope. */
    public function verify(string $ticket, string $origin, ?int $now = null): array
    {
        $now ??= time();
        if (strlen($ticket) > 4096 || substr_count($ticket, '.') !== 1) {
            throw new \UnexpectedValueException('DICTATION_TICKET_INVALID');
        }
        [$payload, $encodedSignature] = explode('.', $ticket, 2);
        $signature = self::base64UrlDecode($encodedSignature);
        $expected = hash_hmac('sha256', $payload, $this->secret, true);
        if (!hash_equals($expected, $signature)) {
            throw new \UnexpectedValueException('DICTATION_TICKET_INVALID');
        }
        $claims = json_decode(self::base64UrlDecode($payload), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($claims)
            || ($claims['v'] ?? null) !== 1
            || ($claims['aud'] ?? null) !== self::AUDIENCE
            || ($claims['origin'] ?? null) !== $origin
            || !is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > $now
            || $claims['exp'] <= $now
            || $claims['exp'] <= $claims['iat']
            || $claims['exp'] - $claims['iat'] > self::TTL_SECONDS
            || !is_int($claims['pid'] ?? null)
            || $claims['pid'] < 1
            || !is_string($claims['question'] ?? null)
            || !preg_match(self::STABLE_ID_PATTERN, $claims['question'])
            || !is_string($claims['sid'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $claims['sid'])
            || !is_string($claims['jti'] ?? null)
            || !preg_match('/^[a-f0-9]{32}$/D', $claims['jti'])) {
            throw new \UnexpectedValueException('DICTATION_TICKET_INVALID');
        }
        return $claims;
    }

    /** Strict verifier used by focused tests; the gateway independently verifies the envelope. */
    public function verifyDiagnostic(string $ticket, string $origin, ?int $now = null): array
    {
        $now ??= time();
        if (strlen($ticket) > 4096 || substr_count($ticket, '.') !== 1 || $origin !== self::DIAGNOSTIC_ORIGIN) {
            throw new \UnexpectedValueException('DICTATION_DIAGNOSTIC_TICKET_INVALID');
        }
        [$payload, $encodedSignature] = explode('.', $ticket, 2);
        $signature = self::base64UrlDecode($encodedSignature);
        $expected = hash_hmac('sha256', $payload, $this->secret, true);
        if (!hash_equals($expected, $signature)) {
            throw new \UnexpectedValueException('DICTATION_DIAGNOSTIC_TICKET_INVALID');
        }
        $claims = json_decode(self::base64UrlDecode($payload), true, 16, JSON_THROW_ON_ERROR);
        $expectedKeys = ['v', 'aud', 'purpose', 'sid', 'origin', 'iat', 'exp', 'jti'];
        if (!is_array($claims)
            || array_diff(array_keys($claims), $expectedKeys) !== []
            || array_diff($expectedKeys, array_keys($claims)) !== []
            || ($claims['v'] ?? null) !== 1
            || ($claims['aud'] ?? null) !== self::DIAGNOSTIC_AUDIENCE
            || ($claims['purpose'] ?? null) !== self::DIAGNOSTIC_PURPOSE
            || ($claims['origin'] ?? null) !== $origin
            || !is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > $now
            || $claims['exp'] <= $now
            || $claims['exp'] <= $claims['iat']
            || $claims['exp'] - $claims['iat'] > self::TTL_SECONDS
            || !is_string($claims['sid'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $claims['sid'])
            || !is_string($claims['jti'] ?? null)
            || !preg_match('/^[a-f0-9]{32}$/D', $claims['jti'])) {
            throw new \UnexpectedValueException('DICTATION_DIAGNOSTIC_TICKET_INVALID');
        }
        return $claims;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
            throw new \UnexpectedValueException('DICTATION_TICKET_INVALID');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new \UnexpectedValueException('DICTATION_TICKET_INVALID');
        }
        return $decoded;
    }
}
