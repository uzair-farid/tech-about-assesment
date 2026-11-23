<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Support\BearerToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'key_hash' => BearerToken::hash(Str::random(40)),
            'scopes' => ['holds.write', 'payments.write', 'bookings.read', 'bookings.write', 'fares.read'],
            'is_active' => true,
        ];
    }

    /**
     * Creates the partner and returns it alongside the plaintext key, since
     * only the hash is ever persisted.
     *
     * @return array{0: Partner, 1: string}
     */
    public function createWithKey(array $attributes = []): array
    {
        $plaintext = Str::random(40);

        $partner = $this->create([
            ...$attributes,
            'key_hash' => BearerToken::hash($plaintext),
        ]);

        return [$partner, $plaintext];
    }
}
