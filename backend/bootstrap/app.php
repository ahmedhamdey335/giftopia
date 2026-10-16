<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\OptionalSanctumAuthentication;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => CheckRole::class,
            'optional-auth' => OptionalSanctumAuthentication::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiResponse::error('The given data was invalid.', $exception->errors(), 422);
            }

            if ($exception instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated.', status: 401);
            }

            if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                return ApiResponse::error('Resource not found.', status: 404);
            }

            if ($exception instanceof MethodNotAllowedHttpException) {
                return ApiResponse::error('Method not allowed.', status: 405);
            }

            if ($exception instanceof TooManyRequestsHttpException) {
                return ApiResponse::error('Too many requests.', status: 429);
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

                return ApiResponse::error(
                    $status >= 500 ? 'Server error.' : ($exception->getMessage() ?: 'Request failed.'),
                    status: $status,
                );
            }

            return ApiResponse::error('Server error.', status: 500);
        });
    })->create();
