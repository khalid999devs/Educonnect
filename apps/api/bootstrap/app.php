<?php

use App\Http\Middleware\AssignRequestId;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->statefulApi();
        $middleware->preventRequestsDuringMaintenance(except: [
            'api/health',
            'api/v1/health',
            'api/v1/health/readiness',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(
            function (Response $response, Throwable $exception, Request $request): Response {
                if (! $request->is('api/*')) {
                    return $response;
                }

                return app(ApiExceptionRenderer::class)->render($response, $exception, $request);
            },
        );
    })->create();
