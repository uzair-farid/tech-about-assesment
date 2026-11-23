<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'fare' => [
                'origin' => $this->fare->origin,
                'destination' => $this->fare->destination,
                'carrier_code' => $this->fare->carrier_code,
                'flight_number' => $this->fare->flight_number,
                'departs_at' => $this->fare->departs_at->toIso8601String(),
            ],
            'pricing' => [
                'currency' => $this->fare->currency,
                'base_fare_paisa' => $this->base_fare_paisa,
                'tax_paisa' => $this->tax_paisa,
                'total_paisa' => $this->total_paisa,
                'refunded_paisa' => $this->refunded_paisa,
            ],
            'refundable' => $this->refundable,
            'passenger_name' => $this->passenger_name,
            'pnr' => $this->pnr,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'cancel_requested_at' => $this->cancel_requested_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
