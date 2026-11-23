<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hold lifetime
    |--------------------------------------------------------------------------
    |
    | Minutes a fare hold stays valid before the reserved seat is released
    | back into inventory.
    |
    */
    'hold_ttl_minutes' => (int) env('HOLD_TTL_MINUTES', 20),

    /*
    |--------------------------------------------------------------------------
    | Customer token lifetime
    |--------------------------------------------------------------------------
    |
    | Hours an opaque customer token stays valid. Each token is scoped to a
    | single booking.
    |
    */
    'customer_token_ttl_hours' => (int) env('CUSTOMER_TOKEN_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Refund policy
    |--------------------------------------------------------------------------
    |
    | Refundable fares forfeit a flat cancellation fee (in paisa) and refund
    | the rest. Non-refundable fares forfeit the base fare and only the tax
    | component is returned, matching common airline fare rules.
    |
    */
    'refundable_cancellation_fee_paisa' => (int) env('REFUNDABLE_CANCELLATION_FEE_PAISA', 150000),

];
