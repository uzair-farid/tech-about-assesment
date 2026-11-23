<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Tests\Feature\Concerns\SignsPspWebhooks;

class WebhookTest extends FeatureTestCase
{
    use SignsPspWebhooks;

    public function test_a_successful_payment_webhook_tickets_the_booking(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 3]);
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-ok'],
        )->assertStatus(202);

        $payment = $booking->payments()->sole();

        $response = $this->postSignedWebhook([
            'event_id' => 'evt-1',
            'payment_reference' => $payment->psp_reference,
            'status' => 'succeeded',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ticketed');
        $booking->refresh();
        $this->assertSame(BookingStatus::Ticketed, $booking->status);
        $this->assertNotEmpty($booking->pnr);
    }

    public function test_a_failed_payment_webhook_releases_the_seat(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 3]);
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-fail'],
        )->assertStatus(202);

        $payment = $booking->payments()->sole();

        $response = $this->postSignedWebhook([
            'event_id' => 'evt-2',
            'payment_reference' => $payment->psp_reference,
            'status' => 'failed',
            'failure_reason' => 'card_declined',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'payment_failed');
        $booking->refresh();
        $this->assertSame(BookingStatus::PaymentFailed, $booking->status);
        $this->assertSame(3, $fare->fresh()->seats_left);
    }

    public function test_a_redelivered_webhook_event_id_is_ignored(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare();
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-dup'],
        )->assertStatus(202);

        $payment = $booking->payments()->sole();
        $payload = ['event_id' => 'evt-dup', 'payment_reference' => $payment->psp_reference, 'status' => 'succeeded'];

        $this->postSignedWebhook($payload)->assertOk()->assertJsonPath('status', 'ticketed');
        $second = $this->postSignedWebhook($payload);

        $second->assertOk();
        $second->assertJsonPath('status', 'ignored_duplicate_event');
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_two_differently_ided_deliveries_for_the_same_payment_are_not_double_applied(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 3]);
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-twice'],
        )->assertStatus(202);

        $payment = $booking->payments()->sole();

        $this->postSignedWebhook([
            'event_id' => 'evt-a',
            'payment_reference' => $payment->psp_reference,
            'status' => 'succeeded',
        ])->assertJsonPath('status', 'ticketed');

        $second = $this->postSignedWebhook([
            'event_id' => 'evt-b',
            'payment_reference' => $payment->psp_reference,
            'status' => 'succeeded',
        ]);

        $second->assertOk();
        $second->assertJsonPath('status', 'already_processed');
    }

    public function test_rejects_an_invalid_signature(): void
    {
        $response = $this->withHeaders([
            'X-Psp-Timestamp' => (string) time(),
            'X-Psp-Signature' => 'not-a-real-signature',
        ])->postJson('/api/webhooks/psp', [
            'event_id' => 'evt-bad',
            'payment_reference' => 'psp_does_not_exist',
            'status' => 'succeeded',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'invalid_webhook_signature');
    }

    public function test_rejects_a_stale_timestamp(): void
    {
        $response = $this->postSignedWebhook([
            'event_id' => 'evt-stale',
            'payment_reference' => 'psp_does_not_exist',
            'status' => 'succeeded',
        ], (string) (time() - 3600));

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'invalid_webhook_signature');
    }

    public function test_an_unknown_payment_reference_is_ignored_without_error(): void
    {
        $response = $this->postSignedWebhook([
            'event_id' => 'evt-unknown',
            'payment_reference' => 'psp_never_existed',
            'status' => 'succeeded',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ignored_unknown_payment');
    }
}
