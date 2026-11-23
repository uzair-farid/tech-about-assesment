<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Support\BearerToken;
use Illuminate\Database\Seeder;

/**
 * Fixed, documented demo keys (not real secrets - this is a local sqlite
 * fixture) so the README's curl examples work without first minting a key.
 */
class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $this->createPartner(
            name: 'Travel.pk Backend (full access)',
            plaintextKey: 'demo_full_11a9f3c7b2e84d6fa0c5e9d1b3a7f2c4',
            scopes: ['holds.write', 'payments.write', 'bookings.read', 'bookings.write', 'fares.read'],
        );

        $this->createPartner(
            name: 'Travel.pk Reporting (read-only)',
            plaintextKey: 'demo_readonly_7f2a9c1e4b6d803f5a2c7e9b1d4f6a80',
            scopes: ['bookings.read', 'fares.read'],
        );
    }

    private function createPartner(string $name, string $plaintextKey, array $scopes): void
    {
        Partner::query()->updateOrCreate(
            ['key_hash' => BearerToken::hash($plaintextKey)],
            ['name' => $name, 'scopes' => $scopes, 'is_active' => true],
        );

        $this->command?->info("{$name}: Bearer {$plaintextKey}");
    }
}
