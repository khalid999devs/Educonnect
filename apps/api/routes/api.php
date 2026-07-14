<?php

use App\Http\Controllers\Api\V1\Admin\Auth\AdminCurrentUserController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLogoutController;
use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendEmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Middleware\EnsureAdminSessionPasswordIsCurrent;
use App\Http\Middleware\RequireAdminAccess;
use App\Http\Middleware\RequireBrowserSurface;
use App\Http\Middleware\RequireStatefulSpaSession;
use Illuminate\Support\Facades\Route;

// Routes in this file are automatically prefixed with /api/v1.

Route::middleware([
    RequireStatefulSpaSession::class,
    RequireBrowserSurface::class.':'.RequireBrowserSurface::STUDENT,
])
    ->group(function (): void {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('/register', RegisterController::class)
                    ->middleware('throttle:auth.register')
                    ->name('register');
                Route::post('/login', LoginController::class)
                    ->middleware('throttle:auth.login')
                    ->name('login');
                Route::post('/forgot-password', ForgotPasswordController::class)
                    ->middleware('throttle:auth.password-email')
                    ->name('password.email');
                Route::post('/reset-password', ResetPasswordController::class)
                    ->middleware('throttle:auth.password-reset')
                    ->name('password.reset');

                Route::middleware('auth:sanctum')->group(function (): void {
                    Route::post('/logout', LogoutController::class)->name('logout');
                    Route::post('/logout-all', LogoutAllController::class)
                        ->middleware('throttle:auth.logout-all')
                        ->name('logout-all');
                    Route::post('/email/verification-notification', SendEmailVerificationController::class)
                        ->middleware('throttle:auth.verification-send')
                        ->name('verification.send');
                    Route::get('/verify-email/{user}/{hash}', VerifyEmailController::class)
                        ->middleware(['signed', 'throttle:auth.verification-verify'])
                        ->name('verification.verify');

                    // Compatibility alias; new clients use the canonical /api/v1/me route.
                    Route::get('/me', CurrentUserController::class)->name('me');
                });
            });

        Route::get('/me', CurrentUserController::class)
            ->middleware('auth:sanctum')
            ->name('me');
    });

Route::prefix('admin')
    ->middleware([
        RequireStatefulSpaSession::class,
        RequireBrowserSurface::class.':'.RequireBrowserSurface::ADMIN,
    ])
    ->name('admin.')
    ->group(function (): void {
        Route::post('/auth/login', AdminLoginController::class)
            ->middleware('throttle:auth.admin-login')
            ->name('auth.login');

        Route::middleware([
            'auth:admin',
            EnsureAdminSessionPasswordIsCurrent::class,
        ])->group(function (): void {
            Route::post('/auth/logout', AdminLogoutController::class)->name('auth.logout');
            Route::get('/me', AdminCurrentUserController::class)
                ->middleware(RequireAdminAccess::class)
                ->name('me');
        });
    });
