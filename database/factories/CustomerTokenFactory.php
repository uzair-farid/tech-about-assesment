<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\CustomerToken;
use App\Support\BearerToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerToken>
 */
class CustomerTokenFactory extends Factory
{
    protected $model = CustomerToken::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'token_hash' => BearerToken::hash(Str::random(48)),
            'expires_at' => now()->addHours(24),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }
}
