<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AuthenticateBookingAccess;
use App\Http\Middleware\AuthenticatePartner;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Http\Middleware\VerifyPspSignature;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'partner.auth' => AuthenticatePartner::class,
            'booking.auth' => AuthenticateBookingAccess::class,
            'psp.signature' => VerifyPspSignature::class,
            'idempotent' => EnsureIdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(function (ApiException $e) {
            return $e->render();
        });

        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'error' => ['code' => 'unauthenticated', 'message' => 'Authentication required.'],
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'The requested resource was not found.'],
            ], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e) {
            return response()->json([
                'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.'],
            ], 405);
        });
    })->create();
