<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class BearerToken
{
    public static function extract(Request $request): string
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ') || trim(substr($header, 7)) === '') {
            throw new AuthenticationException('Missing bearer token.');
        }

        return trim(substr($header, 7));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
