<?php

namespace App\Http\Middleware;

use App\Exceptions\ForbiddenApiException;
use App\Models\Partner;
use App\Support\BearerToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePartner
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = BearerToken::extract($request);

        $partner = Partner::query()
            ->where('key_hash', BearerToken::hash($token))
            ->where('is_active', true)
            ->first();

        if (! $partner) {
            throw new AuthenticationException('Invalid or inactive partner key.');
        }

        if ($scope !== null && ! $partner->hasScope($scope)) {
            throw new ForbiddenApiException("Partner key is missing the \"{$scope}\" scope.");
        }

        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
