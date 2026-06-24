<?php

use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Middleware\RequireStatefulSpaSession;
use Illuminate\Support\Facades\Route;

// Routes in this file are automatically prefixed with /api/v1.

Route::prefix('auth')
    ->middleware(RequireStatefulSpaSession::class)
    ->name('auth.')
    ->group(function (): void {
        Route::post('/register', RegisterController::class)
            ->middleware('throttle:auth')
            ->name('register');
        Route::post('/login', LoginController::class)
            ->middleware('throttle:auth')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', LogoutController::class)->name('logout');
            Route::get('/me', CurrentUserController::class)->name('me');
        });
    });
