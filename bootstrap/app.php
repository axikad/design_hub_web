<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveOptionalSanctumUser;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'permission' => EnsurePermission::class,
            'optional.sanctum' => ResolveOptionalSanctumUser::class,
        ]);
        $middleware->statefulApi();
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception) => $request->is('api/*')
                || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                $message = collect($exception->errors())->flatten()->first() ?? 'Validation failed.';

                return ApiResponse::error(
                    $message,
                    'validation_error',
                    422,
                    $exception->errors(),
                );
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Please sign in to continue.', 'AUTH_REQUIRED', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('This action is forbidden.', 'forbidden', 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Resource not found.', 'resource_not_found', 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Too many requests. Please try again later.', 'rate_limited', 429);
            }
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            $driverError = $exception->errorInfo[1] ?? null;

            if ($driverError === 2002) {
                $message = 'The database is temporarily unavailable. Please try again shortly.';

                if ($request->is('api/*')) {
                    return ApiResponse::error($message, 'database_unavailable', 503);
                }

                return response($message, 503);
            }

            if ($driverError === 1062) {
                $message = 'The same value is already used by another record. Review the unique fields and try again.';

                if ($request->is('api/*')) {
                    return ApiResponse::error($message, 'duplicate_record', 409);
                }

                return back()->with('error', $message);
            }

            if ($driverError !== 1451) {
                return null;
            }

            $message = 'This record is connected to other records. Disconnect them before deleting, or use the provided delete confirmation flow.';

            if ($request->is('api/*')) {
                return ApiResponse::error($message, 'record_in_use', 409);
            }

            return back()->with('error', $message);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $responses = [
                401 => ['Please sign in to continue.', 'AUTH_REQUIRED'],
                403 => ['This action is forbidden.', 'forbidden'],
                404 => ['Resource not found.', 'resource_not_found'],
                409 => ['The request conflicts with the current resource state.', 'conflict'],
                422 => ['The request could not be processed.', 'unprocessable_entity'],
                429 => ['Too many requests. Please try again later.', 'rate_limited'],
            ];

            [$message, $code] = $responses[$status] ?? ['Request failed.', 'http_error'];

            return ApiResponse::error($message, $code, $status);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'An unexpected error occurred.',
                    'internal_error',
                    500,
                );
            }
        });
    })->create();
