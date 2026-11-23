<?php

namespace App\Exceptions;

class BookingStateConflictException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct('booking_state_conflict', $message, 409);
    }
}
