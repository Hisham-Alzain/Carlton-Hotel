<?php

use App\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', \App\Http\Middleware\AttachRequestId::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\SetLocale::class);
        $middleware->alias([
            'permission'          => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role'                => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission'  => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'has_booking'         => \App\Http\Middleware\EnsureHasBooking::class,
            'is_checked_in'       => \App\Http\Middleware\EnsureIsCheckedIn::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request): ?JsonResponse {
            if (! $request->wantsJson() && ! $request->is('api/*')) {
                return null;
            }
            $requestId = $request->attributes->get('request_id', (string) Str::uuid());

            if ($e instanceof DomainException) {
                return response()->json([
                    'success'    => false,
                    'message'    => $e->getMessage() ?: __('custom.errors.' . $e->errorCode()),
                    'error_code' => $e->errorCode(),
                    'context'    => $e->context() ?: null,
                    'request_id' => $requestId,
                ], $e->statusCode());
            }
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success'    => false,
                    'message'    => __('custom.errors.validation_failed'),
                    'error_code' => 'validation_failed',
                    'errors'     => $e->errors(),
                    'context'    => null,
                    'request_id' => $requestId,
                ], 422);
            }
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success'    => false,
                    'message'    => __('custom.errors.unauthorized'),
                    'error_code' => 'unauthorized',
                    'context'    => null,
                    'request_id' => $requestId,
                ], 401);
            }
            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException || $e instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
                return response()->json([
                    'success'    => false,
                    'message'    => __('custom.errors.forbidden'),
                    'error_code' => 'forbidden',
                    'context'    => null,
                    'request_id' => $requestId,
                ], 403);
            }
            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'success'    => false,
                    'message'    => __('custom.errors.not_found'),
                    'error_code' => 'not_found',
                    'context'    => null,
                    'request_id' => $requestId,
                ], 404);
            }
            if ($e instanceof TooManyRequestsHttpException) {
                return response()->json([
                    'success'    => false,
                    'message'    => __('custom.errors.too_many_requests'),
                    'error_code' => 'too_many_requests',
                    'context'    => null,
                    'request_id' => $requestId,
                ], 429);
            }
            $message = app()->isLocal() ? $e->getMessage() : __('custom.errors.server_error');
            return response()->json([
                'success'    => false,
                'message'    => $message,
                'error_code' => 'server_error',
                'context'    => null,
                'request_id' => $requestId,
            ], 500);
        });
    })->create();
