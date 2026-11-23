<?php

namespace App\Exceptions;

class IdempotencyKeyMissingException extends ApiException
{
    public function __construct(string $message = 'The Idempotency-Key header is required.')
    {
        parent::__construct('idempotency_key_required', $message, 400);
    }
}
