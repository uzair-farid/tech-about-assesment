<?php

namespace App\Exceptions;

class HoldExpiredException extends ApiException
{
    public function __construct(string $message = 'This hold has expired and its seat was released.')
    {
        parent::__construct('hold_expired', $message, 409);
    }
}
