<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $result = $this->payments->startPayment(
            partner: $request->attributes->get('partner'),
            booking: $booking,
            idempotencyKey: $request->attributes->get('idempotency_key'),
            requestPayload: $request->all(),
        );

        return response()->json($result['body'], $result['status']);
    }
}
