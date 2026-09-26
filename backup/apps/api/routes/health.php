<?php

use App\Http\Controllers\Api\V1\Health\LivenessController;
use App\Http\Controllers\Api\V1\Health\ReadinessController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
    ->group(function (): void {
        Route::get('/api/health', LivenessController::class)->name('api.health');

        Route::prefix('/api/v1')
            ->name('api.v1.')
            ->group(function (): void {
                Route::get('/health', LivenessController::class)->name('health');
                Route::get('/health/readiness', ReadinessController::class)->name('health.readiness');
            });
    });
