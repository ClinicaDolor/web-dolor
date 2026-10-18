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
    public function __construct(private bool $enabled = false) {}

    public function allows(PDO $db): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
            return $database !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
