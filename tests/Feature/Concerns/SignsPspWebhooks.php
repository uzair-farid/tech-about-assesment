<?php

namespace Tests\Feature\Concerns;

use Illuminate\Testing\TestResponse;

trait SignsPspWebhooks
{
    protected function postSignedWebhook(array $payload, ?string $timestamp = null): TestResponse
    {
        $timestamp ??= (string) time();
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

        $signature = hash_hmac(
            'sha256',
            "{$timestamp}.{$rawBody}",
            (string) config('services.psp.webhook_secret'),
        );

        $server = $this->transformHeadersToServerVars([
            'X-Psp-Timestamp' => $timestamp,
            'X-Psp-Signature' => $signature,
            'Content-Type' => 'application/json',
        ]);

        return $this->call('POST', '/api/webhooks/psp', [], [], [], $server, $rawBody);
    }
}
