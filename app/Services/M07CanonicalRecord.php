<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Shared read DTO for catalogued and non-catalogued M07 entries.
 * It is deliberately independent from either the P09 renderer or the
 * conversational view.
 */
final class M07CanonicalRecord
{
    public function __construct(
        public readonly string $source,
        public readonly int $id,
        public readonly int $patientId,
        public readonly int $clinicId,
        public readonly int $themeId,
        public readonly ?int $questionId,
        public readonly string $name,
        public readonly array $fields,
        public readonly array $efficacy,
        public readonly array $tolerability,
        public readonly array $legacyProjection,
        public readonly int $version,
        public readonly string $status,
        public readonly ?string $pendingReason = null,
        public readonly ?string $entryKey = null,
        /** Canonical field states are retained even when their legacy value is empty. */
        public readonly array $states = [],
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'id' => $this->id,
            'patient_id' => $this->patientId,
            'clinic_id' => $this->clinicId,
            'theme_id' => $this->themeId,
            'question_id' => $this->questionId,
            'name' => $this->name,
            'fields' => $this->fields,
            'efficacy' => $this->efficacy,
            'tolerability' => $this->tolerability,
            'legacy_projection' => $this->legacyProjection,
            'version' => $this->version,
            'status' => $this->status,
            'pending_reason' => $this->pendingReason,
            'entry_key' => $this->entryKey,
            'states' => $this->states,
        ];
    }
}
