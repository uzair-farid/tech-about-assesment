<?php

namespace App\Http\Middleware;

use App\Exceptions\ForbiddenApiException;
use App\Exceptions\NotFoundApiException;
use App\Models\Booking;
use App\Models\CustomerToken;
use App\Models\Partner;
use App\Support\BearerToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Grants access to a single booking either to a scoped partner key or to the
 * opaque customer token issued for that exact booking. A customer token
 * presented against someone else's booking must look identical to the
 * booking not existing, so it returns 404 rather than 403.
 */
class AuthenticateBookingAccess
{
    public function handle(Request $request, Closure $next, ?string $partnerScope = null): Response
    {
        $token = BearerToken::extract($request);
        $hash = BearerToken::hash($token);

        $partner = Partner::query()->where('key_hash', $hash)->where('is_active', true)->first();

        if ($partner) {
            if ($partnerScope !== null && ! $partner->hasScope($partnerScope)) {
                throw new ForbiddenApiException("Partner key is missing the \"{$partnerScope}\" scope.");
            }

            $request->attributes->set('actor_type', 'partner');
            $request->attributes->set('partner', $partner);

            return $next($request);
        }

        $customerToken = CustomerToken::query()->where('token_hash', $hash)->first();

        if (! $customerToken) {
            throw new AuthenticationException('Invalid credentials.');
        }

        if ($customerToken->isExpired()) {
            throw new AuthenticationException('Customer token has expired.');
        }

        $booking = $request->route('booking');

        if ($booking instanceof Booking && $customerToken->booking_id !== $booking->id) {
            throw new NotFoundApiException;
        }

        $customerToken->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('actor_type', 'customer');
        $request->attributes->set('customer_token', $customerToken);

        return $next($request);
    }
}
