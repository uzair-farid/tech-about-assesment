<?php

namespace App\Exceptions;

class SeatUnavailableException extends ApiException
{
    public function __construct(string $message = 'No seats are left on this fare.')
    {
        parent::__construct('seat_unavailable', $message, 409);
    }
}
