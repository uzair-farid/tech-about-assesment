<?php

namespace App\Exceptions;

class InvalidPayloadException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct('invalid_payload', $message, 422);
    }
}
