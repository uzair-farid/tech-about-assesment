<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Payment;

class PaymentIdempotencyTest extends FeatureTestCase
{
    public function test_starts_a_payment_and_moves_booking_to_payment_pending(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $response = $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-1'],
        );

        $response->assertStatus(202);
        $response->assertJsonPath('status', 'pending');
        $this->assertSame(BookingStatus::PaymentPending, $booking->fresh()->status);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_requires_an_idempotency_key(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $response = $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            $this->authHeaders($key),
        );

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_replaying_the_same_idempotency_key_and_body_returns_the_cached_response(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);
        $headers = [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-replay'];

        $first = $this->postJson("/api/v1/holds/{$booking->reference}/payments", [], $headers);
        $second = $this->postJson("/api/v1/holds/{$booking->reference}/payments", [], $headers);

        $first->assertStatus(202);
        $second->assertStatus(202);
        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_reusing_the_idempotency_key_with_a_different_body_is_rejected(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);
        $headers = [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-conflict'];

        $this->postJson("/api/v1/holds/{$booking->reference}/payments", ['note' => 'first'], $headers)
            ->assertStatus(202);

        $response = $this->postJson("/api/v1/holds/{$booking->reference}/payments", ['note' => 'second'], $headers);

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'idempotency_key_conflict');
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_cannot_start_a_payment_on_an_expired_hold(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 3]);
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner, [
            'expires_at' => now()->subMinutes(5),
        ]);

        $response = $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-expired'],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'hold_expired');
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame(3, $fare->fresh()->seats_left);
    }

    public function test_cannot_start_a_second_payment_for_an_already_pending_booking(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-a'],
        )->assertStatus(202);

        $response = $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-b'],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'booking_state_conflict');
    }

    public function test_a_request_still_being_processed_under_the_same_key_is_rejected_as_in_progress(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        Payment::factory()->create([
            'booking_id' => $booking->id,
            'partner_id' => $partner->id,
            'idempotency_key' => 'idem-inflight',
            'request_fingerprint' => hash('sha256', json_encode([])),
            'response_status' => null,
            'response_body' => null,
        ]);

        $response = $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-inflight'],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'idempotency_key_in_progress');
    }
}
