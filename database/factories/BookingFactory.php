<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\SupplierMode;
use App\Models\Booking;
use App\Models\Fare;
use App\Models\Partner;
use App\Support\BookingReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'reference' => BookingReference::generate(),
            'fare_id' => Fare::factory(),
            'partner_id' => Partner::factory(),
            'status' => BookingStatus::Hold,
            'base_fare_paisa' => 1500000,
            'tax_paisa' => 200000,
            'total_paisa' => 1700000,
            'refundable' => true,
            'fare_rules_snapshot' => ['refundable' => true],
            'supplier_mode' => SupplierMode::Normal,
            'expires_at' => now()->addMinutes(20),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
