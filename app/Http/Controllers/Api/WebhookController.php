<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PspWebhookRequest;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function __construct(private WebhookService $webhooks) {}

    public function psp(PspWebhookRequest $request): JsonResponse
    {
        return response()->json($this->webhooks->handlePspEvent($request->validated()));
    }
}
