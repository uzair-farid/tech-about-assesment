<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidWebhookSignatureException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the PSP's HMAC-SHA256 webhook signature. The signed string is
 * "{timestamp}.{raw body}", matching how Stripe and most PSPs sign webhooks,
 * so a replayed or forged payload can be told apart from a genuine one.
 */
class VerifyPspSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Psp-Timestamp');
        $signature = $request->header('X-Psp-Signature');

        if (! $timestamp || ! $signature) {
            throw new InvalidWebhookSignatureException('Missing signature headers.');
        }

        $tolerance = (int) config('services.psp.webhook_tolerance_seconds');

        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw new InvalidWebhookSignatureException('Webhook timestamp is outside the allowed tolerance.');
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp.'.'.$request->getContent(),
            (string) config('services.psp.webhook_secret'),
        );

        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignatureException('Signature does not match payload.');
        }

        return $next($request);
    }
}
