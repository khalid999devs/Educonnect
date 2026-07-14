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
use App\Http\Controllers\Api\V1\Courses\ArchiveCourseController;
use App\Http\Controllers\Api\V1\Courses\CreateAcademicTermController;
use App\Http\Controllers\Api\V1\Courses\CreateCourseController;
use App\Http\Controllers\Api\V1\Courses\DeleteAcademicTermController;
use App\Http\Controllers\Api\V1\Courses\DeleteCourseController;
use App\Http\Controllers\Api\V1\Courses\ListAcademicTermsController;
use App\Http\Controllers\Api\V1\Courses\ListCoursesController;
use App\Http\Controllers\Api\V1\Courses\RestoreCourseController;
use App\Http\Controllers\Api\V1\Courses\ShowAcademicTermController;
use App\Http\Controllers\Api\V1\Courses\ShowCourseController;
use App\Http\Controllers\Api\V1\Courses\UpdateAcademicTermController;
use App\Http\Controllers\Api\V1\Courses\UpdateCourseController;
use App\Http\Controllers\Api\V1\Onboarding\CompleteOnboardingController;
use App\Http\Controllers\Api\V1\Onboarding\ShowOnboardingController;
use App\Http\Controllers\Api\V1\Onboarding\UpdateOnboardingStepController;
use App\Http\Middleware\EnsureAdminSessionPasswordIsCurrent;
use App\Http\Middleware\RequireAdminAccess;
use App\Http\Middleware\RequireBrowserSurface;
use App\Http\Middleware\RequireStatefulSpaSession;
use App\Http\Middleware\RequireVerifiedEmail;
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

        Route::prefix('onboarding')
            ->middleware([
                'auth:sanctum',
                RequireVerifiedEmail::class,
            ])
            ->name('onboarding.')
            ->group(function (): void {
                Route::get('/', ShowOnboardingController::class)
                    ->middleware(['throttle:onboarding.read', 'can:academic.manage-own'])
                    ->name('show');
                Route::put('/steps/{step}', UpdateOnboardingStepController::class)
                    ->whereIn('step', ['institution', 'program', 'study_stage', 'courses', 'goals', 'first_source'])
                    ->middleware(['throttle:onboarding.write', 'can:academic.manage-own'])
                    ->name('steps.update');
                Route::put('/completion', CompleteOnboardingController::class)
                    ->middleware(['throttle:onboarding.complete', 'can:academic.manage-own'])
                    ->name('completion.update');
            });

        Route::middleware([
            'auth:sanctum',
            RequireVerifiedEmail::class,
        ])->group(function (): void {
            Route::prefix('academic-terms')
                ->name('academic-terms.')
                ->group(function (): void {
                    Route::get('/', ListAcademicTermsController::class)
                        ->middleware(['throttle:academic.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/', CreateAcademicTermController::class)
                        ->middleware(['throttle:academic.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::get('/{term}', ShowAcademicTermController::class)
                        ->where('term', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{term}', UpdateAcademicTermController::class)
                        ->where('term', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{term}', DeleteAcademicTermController::class)
                        ->where('term', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                });

            Route::prefix('courses')
                ->name('courses.')
                ->group(function (): void {
                    Route::get('/', ListCoursesController::class)
                        ->middleware(['throttle:academic.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/', CreateCourseController::class)
                        ->middleware(['throttle:academic.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::get('/{course}', ShowCourseController::class)
                        ->where('course', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{course}', UpdateCourseController::class)
                        ->where('course', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{course}', DeleteCourseController::class)
                        ->where('course', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                    Route::put('/{course}/archive', ArchiveCourseController::class)
                        ->where('course', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.write', 'can:academic.manage-own'])
                        ->name('archive');
                    Route::delete('/{course}/archive', RestoreCourseController::class)
                        ->where('course', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:academic.write', 'can:academic.manage-own'])
                        ->name('restore');
                });
        });
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
