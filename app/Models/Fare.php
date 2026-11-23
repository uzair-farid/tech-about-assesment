<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fare extends Model
{
    use HasFactory;

    protected $fillable = [
        'origin',
        'destination',
        'carrier_code',
        'flight_number',
        'departs_at',
        'arrives_at',
        'base_fare_paisa',
        'tax_paisa',
        'currency',
        'seats_left',
        'refundable',
        'fare_rules',
    ];

    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'arrives_at' => 'datetime',
            'base_fare_paisa' => 'integer',
            'tax_paisa' => 'integer',
            'seats_left' => 'integer',
            'refundable' => 'boolean',
            'fare_rules' => 'array',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function totalPaisa(): int
    {
        return $this->base_fare_paisa + $this->tax_paisa;
    }
}
