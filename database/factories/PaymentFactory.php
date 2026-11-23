<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'partner_id' => Partner::factory(),
            'idempotency_key' => Str::uuid()->toString(),
            'request_fingerprint' => hash('sha256', Str::random(10)),
            'amount_paisa' => 1700000,
            'status' => PaymentStatus::Initiated,
            'psp_reference' => 'psp_'.Str::uuid()->toString(),
            'response_status' => 202,
            'response_body' => ['status' => 'pending'],
        ];
    }
}
