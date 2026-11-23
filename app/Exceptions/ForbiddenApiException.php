<?php

namespace App\Exceptions;

class ForbiddenApiException extends ApiException
{
    public function __construct(string $message = 'You do not have permission to perform this action.')
    {
        parent::__construct('forbidden', $message, 403);
    }
}
