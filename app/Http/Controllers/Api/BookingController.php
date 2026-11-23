<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\CancelService;
use App\Services\HoldExpiryService;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private HoldExpiryService $holdExpiry,
        private CancelService $cancels,
    ) {
    }

    public function show(Booking $booking): BookingResource
    {
        $booking = $this->holdExpiry->expireIfDue($booking);

        return BookingResource::make($booking->load('fare'));
    }

    public function cancel(Booking $booking): JsonResponse
    {
        $result = $this->cancels->cancel($booking);

        return response()->json($result['body'], $result['status']);
    }
}
