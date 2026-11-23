<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('fares', [FareController::class, 'index'])
        ->middleware('partner.auth:fares.read');

    Route::post('holds', [HoldController::class, 'store'])
        ->middleware('partner.auth:holds.write');

    Route::post('holds/{booking:reference}/payments', [PaymentController::class, 'store'])
        ->middleware(['partner.auth:payments.write', 'idempotent']);

    Route::get('bookings/{booking:reference}', [BookingController::class, 'show'])
        ->middleware('booking.auth:bookings.read');

    Route::post('bookings/{booking:reference}/cancel', [BookingController::class, 'cancel'])
        ->middleware('booking.auth:bookings.write');
});

Route::post('webhooks/psp', [WebhookController::class, 'psp'])
    ->middleware('psp.signature');
