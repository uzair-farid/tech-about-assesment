<?php

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyKeyMissingException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            throw new IdempotencyKeyMissingException();
        }

        $request->attributes->set('idempotency_key', $key);

        return $next($request);
    }
}
