<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\SupplierMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $attributes = [
        'refunded_paisa' => 0,
    ];

    protected $fillable = [
        'reference',
        'fare_id',
        'partner_id',
        'status',
        'base_fare_paisa',
        'tax_paisa',
        'total_paisa',
        'refundable',
        'fare_rules_snapshot',
        'passenger_name',
        'supplier_mode',
        'pnr',
        'refunded_paisa',
        'expires_at',
        'cancel_requested_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'supplier_mode' => SupplierMode::class,
            'base_fare_paisa' => 'integer',
            'tax_paisa' => 'integer',
            'total_paisa' => 'integer',
            'refunded_paisa' => 'integer',
            'refundable' => 'boolean',
            'fare_rules_snapshot' => 'array',
            'expires_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function fare(): BelongsTo
    {
        return $this->belongsTo(Fare::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customerTokens(): HasMany
    {
        return $this->hasMany(CustomerToken::class);
    }

    public function airlineAttempt(): HasOne
    {
        return $this->hasOne(AirlineAttempt::class);
    }

    /**
     * The 20-minute window covers deciding to pay AND the PSP confirming
     * it - a payment started just before expiry can still have its
     * success webhook land after the window closes, so the seat
     * commitment lapses for payment_pending too, not just hold.
     */
    public function isHoldExpired(): bool
    {
        return in_array($this->status, [BookingStatus::Hold, BookingStatus::PaymentPending], true)
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function refundableRemainingPaisa(): int
    {
        return max(0, $this->total_paisa - $this->refunded_paisa);
    }
}
