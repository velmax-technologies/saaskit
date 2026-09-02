<?php

use App\Support\Api\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request): bool {
            return $request->is('api/*');
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::validationError(
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::unauthorized();
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match ($e->getStatusCode()) {
                401 => ApiResponse::unauthorized(
                    $e->getMessage() ?: 'Unauthenticated.',
                ),
                403 => ApiResponse::forbidden(),
                404 => ApiResponse::notFound(),
                409 => ApiResponse::conflict(
                    $e->getMessage() ?: 'The request conflicts with the current state.',
                ),
                429 => ApiResponse::tooManyRequests(
                    $e->getMessage() ?: 'Too many requests.',
                ),
                default => ApiResponse::error(
                    $e->getMessage() ?: 'Request failed.',
                    $e->getStatusCode(),
                ),
            };
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::serverError(
                config('app.debug')
                    ? $e->getMessage()
                    : 'An unexpected server error occurred.',
            );
        });
    })
    ->create();
