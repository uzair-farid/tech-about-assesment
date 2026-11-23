<?php

namespace Database\Seeders;

use App\Models\Fare;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Six fixed fare offers across the three routes the task asked for.
 * Two are non-refundable (AirBlue LHE-KHI, FlyDubai KHI-DXB) and the
 * Emirates KHI-DXB fare has exactly one seat left, for the last-seat
 * race test.
 */
class FareSeeder extends Seeder
{
    public function run(): void
    {
        $fares = [
            [
                'origin' => 'LHE', 'destination' => 'KHI', 'carrier_code' => 'PK', 'flight_number' => 'PK301',
                'departs_at' => '2026-12-10 08:00:00', 'arrives_at' => '2026-12-10 09:45:00',
                'price' => '15250.00', 'tax' => '2100.00', 'seats_left' => 20, 'refundable' => true,
            ],
            [
                'origin' => 'LHE', 'destination' => 'KHI', 'carrier_code' => 'PA', 'flight_number' => 'PA415',
                'departs_at' => '2026-12-10 14:30:00', 'arrives_at' => '2026-12-10 16:15:00',
                'price' => '12999.99', 'tax' => '1850.50', 'seats_left' => 8, 'refundable' => false,
            ],
            [
                'origin' => 'KHI', 'destination' => 'DXB', 'carrier_code' => 'EK', 'flight_number' => 'EK607',
                'departs_at' => '2026-12-11 02:15:00', 'arrives_at' => '2026-12-11 04:40:00',
                'price' => '84250.50', 'tax' => '9875.25', 'seats_left' => 1, 'refundable' => true,
            ],
            [
                'origin' => 'KHI', 'destination' => 'DXB', 'carrier_code' => 'FZ', 'flight_number' => 'FZ333',
                'departs_at' => '2026-12-11 23:50:00', 'arrives_at' => '2026-12-12 02:10:00',
                'price' => '65000.00', 'tax' => '7200.00', 'seats_left' => 15, 'refundable' => false,
            ],
            [
                'origin' => 'ISB', 'destination' => 'JED', 'carrier_code' => 'SV', 'flight_number' => 'SV756',
                'departs_at' => '2026-12-12 05:00:00', 'arrives_at' => '2026-12-12 09:30:00',
                'price' => '125000.75', 'tax' => '14500.25', 'seats_left' => 10, 'refundable' => true,
            ],
            [
                'origin' => 'ISB', 'destination' => 'JED', 'carrier_code' => 'PK', 'flight_number' => 'PK761',
                'departs_at' => '2026-12-12 19:20:00', 'arrives_at' => '2026-12-12 23:55:00',
                'price' => '98500.25', 'tax' => '11200.00', 'seats_left' => 25, 'refundable' => true,
            ],
        ];

        foreach ($fares as $fare) {
            Fare::query()->updateOrCreate(
                [
                    'carrier_code' => $fare['carrier_code'],
                    'flight_number' => $fare['flight_number'],
                    'departs_at' => $fare['departs_at'],
                ],
                [
                    'origin' => $fare['origin'],
                    'destination' => $fare['destination'],
                    'arrives_at' => $fare['arrives_at'],
                    'base_fare_paisa' => Money::fromSupplierString($fare['price']),
                    'tax_paisa' => Money::fromSupplierString($fare['tax']),
                    'currency' => 'PKR',
                    'seats_left' => $fare['seats_left'],
                    'refundable' => $fare['refundable'],
                    'fare_rules' => [
                        'refundable' => $fare['refundable'],
                        'notes' => $fare['refundable']
                            ? 'Refundable; a flat cancellation fee applies.'
                            : 'Non-refundable; only taxes are returned on cancellation.',
                    ],
                ],
            );
        }
    }
}
