<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RecordHttpMetrics;
use App\Http\Middleware\RequireBrowserSurface;
use App\Http\Middleware\RequireStatefulSpaSession;
use App\Http\Middleware\RequireVerifiedEmail;
use App\Support\ApiExceptionRenderer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/health.php'));

            // Local-disk resource transport (dev only, inert in production).
            // Outside the stateful `api` group so the raw signed PUT/GET carry
            // no cookies or CSRF token, exactly as an S3 upload would.
            Route::middleware(['signed', 'throttle:120,1'])
                ->prefix('api/v1')
                ->group(base_path('routes/resource-transport.php'));
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('resources:reconcile-storage --limit=10')
            ->everyFiveMinutes()
            ->withoutOverlapping(120);
        $schedule->command('telemetry:prune')
            ->dailyAt('03:15')
            ->withoutOverlapping(600);
        $schedule->command('intake:recover-stranded --limit=50')
            ->everyFiveMinutes()
            ->withoutOverlapping(120);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->statefulApi();
        $middleware->appendToGroup('api', RecordHttpMetrics::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, RequireBrowserSurface::class);
        $middleware->prependToPriorityList(RequireBrowserSurface::class, RequireStatefulSpaSession::class);
        $middleware->prependToPriorityList(ThrottleRequests::class, RequireVerifiedEmail::class);
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
