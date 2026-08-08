<?php

declare(strict_types=1);

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA mode: requests whose Origin/Referer matches
        // SANCTUM_STATEFUL_DOMAINS authenticate with the encrypted, httpOnly
        // session cookie and are CSRF-protected. Requests from anywhere else
        // fall through to Bearer-token auth. This is what lets the React app
        // hold no token in JavaScript while mobile/CI clients still use tokens.
        $middleware->statefulApi();

        // Route-level aliases.
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render every API failure through the same envelope the success path
        // uses, so the React client has exactly one response shape to parse.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::validationError($e->errors(), $e->getMessage());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::unauthorized($e->getMessage() ?: 'Unauthenticated.');
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::forbidden($e->getMessage() ?: 'This action is unauthorized.');
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::notFound(class_basename($e->getModel()).' not found.');
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            unset($e);

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::notFound('The requested endpoint does not exist.');
        });

        // Catch-all: preserve real HTTP status codes, but never leak internals
        // in production.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            if ($status === Response::HTTP_TOO_MANY_REQUESTS) {
                return ApiResponse::error(
                    'Too many attempts. Please try again later.',
                    $status,
                );
            }

            if ($status < 500) {
                return ApiResponse::error($e->getMessage() ?: 'Request failed.', $status);
            }

            return ApiResponse::error(
                config('app.debug') ? $e->getMessage() : 'Server error. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                [],
                config('app.debug') ? [
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            );
        });
    })->create();
