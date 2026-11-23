<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Hold = 'hold';
    case Expired = 'expired';
    case PaymentPending = 'payment_pending';
    case PaymentFailed = 'payment_failed';
    case Paid = 'paid';
    case Ticketed = 'ticketed';
    case TicketFailed = 'ticket_failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
