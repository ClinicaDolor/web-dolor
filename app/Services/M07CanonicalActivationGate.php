<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Explicit opt-in gate for the shared M07 service.
 *
 * The application does not construct this gate with true.  Synthetic tests
 * may opt in after their own database and patient guards have passed.  No
 * environment variable or host name can open the gate implicitly.
 */
final class M07CanonicalActivationGate
{
    private const DATABASE = 'clinica_dolor_demo_20261010_final';
    private const PATIENT_CLINICS = [9301 => 9101, 9302 => 9102];

    public function __construct(
        private bool $enabled = false,
        private bool $syntheticScopeOnly = false,
    ) {}

    /**
     * Build the application gate only from trusted process configuration.
     * The isolated Apache process must opt in explicitly; request data and
     * Dotenv values never enable the gate. This factory does not change the
     * default local runtime, whose opt-in is absent and therefore remains shut.
     */
    public static function fromTrustedServerConfiguration(): self
    {
        $trustedProcess = PHP_SAPI === 'apache2handler'
            && getenv('M07_HTTP_ISOLATED') === '1'
            && getenv('M07_LOCAL_CANONICAL_ENABLED') === '1'
            && getenv('APP_ENV') === 'testing'
            && getenv('CLINICAL_AI_SYNTHETIC_MODE') === '1'
            && (string)($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1'
            && (string)($_SERVER['SERVER_PORT'] ?? '') === '8117'
            && (string)($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1';

        return new self($trustedProcess, $trustedProcess);
    }

    public function allows(PDO $db): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
            return $database === self::DATABASE;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Restrict the explicitly configured isolated runtime to approved fixtures. */
    public function allowsPatientScope(int $patientId, int $clinicId, string $role): bool
    {
        if (!$this->syntheticScopeOnly) return true;
        if (!$this->enabled) return false;

        return strtolower(trim($role)) === 'patient'
            && (self::PATIENT_CLINICS[$patientId] ?? null) === $clinicId;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function requiresSyntheticEnvironment(): bool
    {
        return $this->syntheticScopeOnly;
    }
}
