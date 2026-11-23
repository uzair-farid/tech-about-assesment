<?php

namespace App\Models;

use App\Enums\AirlineAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirlineAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'idempotency_key',
        'status',
        'pnr',
        'attempt_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => AirlineAttemptStatus::class,
            'attempt_count' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
