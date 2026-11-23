<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'partner_id',
        'idempotency_key',
        'request_fingerprint',
        'amount_paisa',
        'status',
        'psp_reference',
        'failure_reason',
        'response_status',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_paisa' => 'integer',
            'response_status' => 'integer',
            'response_body' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }
}
