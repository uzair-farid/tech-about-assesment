<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Concerns\SignsPspWebhooks;

class HoldExpiryTest extends FeatureTestCase
{
    use SignsPspWebhooks;

    public function test_reading_an_expired_hold_lazily_releases_the_seat(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 4]);
        [$booking, $customerToken] = $this->createHoldWithCustomerToken($fare, $partner, [
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->getJson("/api/v1/bookings/{$booking->reference}", $this->authHeaders($customerToken));

        $response->assertOk();
        $response->assertJsonPath('data.status', 'expired');
        $this->assertSame(4, $fare->fresh()->seats_left);
    }

    public function test_the_sweep_command_expires_stale_holds_and_payment_pending_bookings(): void
    {
        [$partner] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 4]);

        [$holdBooking] = $this->createHoldWithCustomerToken($fare, $partner, [
            'expires_at' => now()->subMinutes(1),
        ]);

        [$pendingBooking] = $this->createHoldWithCustomerToken($fare, $partner, [
            'status' => BookingStatus::PaymentPending,
            'expires_at' => now()->subMinutes(1),
        ]);

        Artisan::call('bookings:expire-stale');

        $this->assertSame(BookingStatus::Expired, $holdBooking->fresh()->status);
        $this->assertSame(BookingStatus::Expired, $pendingBooking->fresh()->status);
        $this->assertSame(4, $fare->fresh()->seats_left);
    }

    public function test_a_payment_that_succeeds_after_the_hold_window_closed_is_fully_refunded(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 2]);
        [$booking] = $this->createHoldWithCustomerToken($fare, $partner);

        $this->postJson(
            "/api/v1/holds/{$booking->reference}/payments",
            [],
            [...$this->authHeaders($key), 'Idempotency-Key' => 'idem-late'],
        )->assertStatus(202);

        $payment = $booking->payments()->sole();

        // The PSP takes longer to confirm than the hold's 20-minute window.
        $booking->forceFill(['expires_at' => now()->subMinute()])->save();

        $response = $this->postSignedWebhook([
            'event_id' => 'evt-late',
            'payment_reference' => $payment->psp_reference,
            'status' => 'succeeded',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'refunded_hold_expired');
        $response->assertJsonPath('refunded_paisa', $booking->total_paisa);

        $booking->refresh();
        $this->assertSame(BookingStatus::Refunded, $booking->status);
        $this->assertSame($booking->total_paisa, $booking->refunded_paisa);
        // Released exactly once: back to 2 (was never actually sold twice).
        $this->assertSame(2, $fare->fresh()->seats_left);
    }
}
