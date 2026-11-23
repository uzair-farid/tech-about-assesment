<?php

namespace App\Http\Controllers\Api;

use App\Enums\SupplierMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateHoldRequest;
use App\Http\Resources\BookingResource;
use App\Services\HoldService;
use Illuminate\Http\JsonResponse;

class HoldController extends Controller
{
    public function __construct(private HoldService $holds) {}

    public function store(CreateHoldRequest $request): JsonResponse
    {
        $result = $this->holds->createHold(
            partner: $request->attributes->get('partner'),
            fareId: (int) $request->validated('fare_id'),
            passengerName: $request->validated('passenger_name'),
            supplierMode: SupplierMode::from($request->validated('supplier_mode') ?? SupplierMode::Normal->value),
        );

        $booking = $result['booking']->load('fare');

        return BookingResource::make($booking)
            ->additional(['customer_token' => $result['customer_token']])
            ->response()
            ->setStatusCode(201);
    }
}
