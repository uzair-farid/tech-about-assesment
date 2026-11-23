<?php

namespace App\Exceptions;

class IdempotencyKeyConflictException extends ApiException
{
    public function __construct(string $message = 'This Idempotency-Key was already used with a different request payload.')
    {
        parent::__construct('idempotency_key_conflict', $message, 409);
    }
}
