<?php

namespace App\Services;

use PDO;

/**
 * Resolves the existing module-finalization contract for conversational writes.
 * It fails closed when the source cannot be read or the module is unsupported.
 */
final class ClinicalWriteEligibilityPolicy
{
    public function __construct(private PDO $db) {}

    public function evaluate(int $patientId, int $moduleId): array
    {
        if ($patientId < 1 || $moduleId < 1 || $moduleId > 9) {
            return ['editable' => false, 'reason' => 'INVALID_SCOPE'];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM pac_historia_clinica_finalizar
                 WHERE id_modulo = :module AND id_paciente = :patient'
            );
            $stmt->execute([':module' => $moduleId, ':patient' => $patientId]);
            $finalized = (int)$stmt->fetchColumn() > 0;
            return [
                'editable' => !$finalized,
                'finalized' => $finalized,
                'reason' => $finalized ? 'MODULE_FINALIZED' : null,
                'source' => 'pac_historia_clinica_finalizar',
            ];
        } catch (\Throwable) {
            return ['editable' => false, 'finalized' => null, 'reason' => 'FINALIZATION_STATE_UNAVAILABLE'];
        }
    }
}
