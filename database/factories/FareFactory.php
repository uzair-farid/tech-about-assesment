<?php

namespace Database\Factories;

use App\Models\Fare;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fare>
 */
class FareFactory extends Factory
{
    protected $model = Fare::class;

    public function definition(): array
    {
        return [
            'origin' => 'LHE',
            'destination' => 'KHI',
            'carrier_code' => 'PK',
            'flight_number' => 'PK'.fake()->unique()->numberBetween(100, 999),
            'departs_at' => now()->addDays(10),
            'arrives_at' => now()->addDays(10)->addHours(2),
            'base_fare_paisa' => Money::fromSupplierString('15000.00'),
            'tax_paisa' => Money::fromSupplierString('2000.00'),
            'currency' => 'PKR',
            'seats_left' => 5,
            'refundable' => true,
            'fare_rules' => ['refundable' => true, 'notes' => 'Test fixture fare.'],
        ];
    }

    public function nonRefundable(): static
    {
        return $this->state(fn () => [
            'refundable' => false,
            'fare_rules' => ['refundable' => false, 'notes' => 'Test fixture fare.'],
        ]);
    }

    public function withSeatsLeft(int $seats): static
    {
        return $this->state(fn () => ['seats_left' => $seats]);
    }
}
