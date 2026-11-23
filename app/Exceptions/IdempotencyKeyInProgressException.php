<?php

namespace App\Exceptions;

class IdempotencyKeyInProgressException extends ApiException
{
    public function __construct(string $message = 'A request with this Idempotency-Key is still being processed.')
    {
        parent::__construct('idempotency_key_in_progress', $message, 409);
    }
}
