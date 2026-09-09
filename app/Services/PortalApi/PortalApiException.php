<?php

declare(strict_types=1);

namespace App\Services\PortalApi;

use RuntimeException;

/**
 * Error controlado al hablar con portal-paciente-api.
 */
final class PortalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?array $payload = null,
    ) {
        parent::__construct($message);
    }
}
