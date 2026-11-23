<?php

namespace App\Exceptions;

class InvalidWebhookSignatureException extends ApiException
{
    public function __construct(string $message = 'Webhook signature verification failed.')
    {
        parent::__construct('invalid_webhook_signature', $message, 401);
    }
}
