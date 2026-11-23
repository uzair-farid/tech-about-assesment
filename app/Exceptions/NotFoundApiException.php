<?php

namespace App\Exceptions;

class NotFoundApiException extends ApiException
{
    public function __construct(string $message = 'The requested resource was not found.')
    {
        parent::__construct('not_found', $message, 404);
    }
}
