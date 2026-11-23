<?php

namespace Tests\Feature;

use App\Models\CustomerToken;
use App\Support\BearerToken;
use Illuminate\Support\Str;

class AuthTest extends FeatureTestCase
{
    public function test_a_customer_token_cannot_read_a_different_booking(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare();
        [, $tokenForBookingA] = $this->createHoldWithCustomerToken($fare, $partner);
        [$bookingB] = $this->createHoldWithCustomerToken($fare, $partner);

        $response = $this->getJson(
            "/api/v1/bookings/{$bookingB->reference}",
            $this->authHeaders($tokenForBookingA),
        );

        $response->assertStatus(404);
    }

    public function test_a_customer_token_can_read_its_own_booking(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking, $token] = $this->createHoldWithCustomerToken($fare, $partner);

        $response = $this->getJson("/api/v1/bookings/{$booking->reference}", $this->authHeaders($token));

        $response->assertOk();
        $response->assertJsonPath('data.reference', $booking->reference);
    }

    public function test_an_expired_customer_token_is_rejected(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $expiredToken = Str::random(48);
        CustomerToken::factory()->expired()->create([
            'booking_id' => $booking->id,
            'token_hash' => BearerToken::hash($expiredToken),
        ]);

        $response = $this->getJson(
            "/api/v1/bookings/{$booking->reference}",
            $this->authHeaders($expiredToken),
        );

        $response->assertStatus(401);
    }

    public function test_an_unknown_bearer_token_is_rejected(): void
    {
        $fare = $this->createFare();
        [$partner] = $this->partnerWithKey();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $response = $this->getJson(
            "/api/v1/bookings/{$booking->reference}",
            $this->authHeaders('totally-made-up-token'),
        );

        $response->assertStatus(401);
    }

    public function test_an_inactive_partner_key_is_rejected(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $partner->update(['is_active' => false]);
        $fare = $this->createFare();

        $response = $this->postJson('/api/v1/holds', ['fare_id' => $fare->id], $this->authHeaders($key));

        $response->assertStatus(401);
    }
}
