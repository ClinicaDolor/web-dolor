<?php

declare(strict_types=1);

namespace App\Services;

final class M07CanonicalException extends \RuntimeException
{
    public function __construct(
        public readonly string $codeName,
        public readonly int $httpStatus,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function toArray(): array
    {
        return ['resultado' => $this->httpStatus, 'code' => $this->codeName, 'mensaje' => $this->getMessage()];
    }
}
