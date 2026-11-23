<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'partner.auth' => \App\Http\Middleware\AuthenticatePartner::class,
            'booking.auth' => \App\Http\Middleware\AuthenticateBookingAccess::class,
            'psp.signature' => \App\Http\Middleware\VerifyPspSignature::class,
            'idempotent' => \App\Http\Middleware\EnsureIdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(function (\App\Exceptions\ApiException $e) {
            return $e->render();
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'error' => ['code' => 'unauthenticated', 'message' => 'Authentication required.'],
            ], 401);
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'The requested resource was not found.'],
            ], 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e) {
            return response()->json([
                'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.'],
            ], 405);
        });
    })->create();
