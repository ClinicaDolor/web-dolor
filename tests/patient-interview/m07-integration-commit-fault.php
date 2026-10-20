<?php

declare(strict_types=1);

/** Test-only, one-shot commit fault; no trigger, DDL, or application failpoint. */
final class M07IntegrationOneShotCommitFailurePdo extends PDO
{
    private bool $failNextCommit = true;

    public function commit(): bool
    {
        if ($this->failNextCommit) {
            $this->failNextCommit = false;
            throw new PDOException('Synthetic one-shot commit failure.');
        }

        return parent::commit();
    }
}
