<?php

use App\Http\Controllers\Api\V1\Admin\Audit\ListAuditEventsController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminCurrentUserController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLogoutController;
use App\Http\Controllers\Api\V1\Admin\Mentors\ListAdminMentorsController;
use App\Http\Controllers\Api\V1\Admin\Mentors\SetMentorVerificationController;
use App\Http\Controllers\Api\V1\Admin\Reports\ListAdminReportsController;
use App\Http\Controllers\Api\V1\Admin\Reports\ResolveAdminReportController;
use App\Http\Controllers\Api\V1\Admin\Roles\ChangeUserRolesController;
use App\Http\Controllers\Api\V1\Admin\Roles\ListRolesController;
use App\Http\Controllers\Api\V1\Admin\Users\ListUsersController;
use App\Http\Controllers\Api\V1\Admin\Users\ReactivateUserController;
use App\Http\Controllers\Api\V1\Admin\Users\ShowUserController;
use App\Http\Controllers\Api\V1\Admin\Users\SuspendUserController;
use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendEmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Community\CreateCommentController;
use App\Http\Controllers\Api\V1\Community\CreateCommentReportController;
use App\Http\Controllers\Api\V1\Community\CreatePostController;
use App\Http\Controllers\Api\V1\Community\CreatePostReportController;
use App\Http\Controllers\Api\V1\Community\DeleteCommentController;
use App\Http\Controllers\Api\V1\Community\DeletePostController;
use App\Http\Controllers\Api\V1\Community\JoinCommunityController;
use App\Http\Controllers\Api\V1\Community\LeaveCommunityController;
use App\Http\Controllers\Api\V1\Community\ListCommentsController;
use App\Http\Controllers\Api\V1\Community\ListCommunitiesController;
use App\Http\Controllers\Api\V1\Community\ListCommunityPostsController;
use App\Http\Controllers\Api\V1\Community\ListFeedController;
use App\Http\Controllers\Api\V1\Community\ListModerationReportsController;
use App\Http\Controllers\Api\V1\Community\ResolveReportController;
use App\Http\Controllers\Api\V1\Community\ShowCommunityController;
use App\Http\Controllers\Api\V1\Community\ShowPostController;
use App\Http\Controllers\Api\V1\Community\UpdatePostController;
use App\Http\Controllers\Api\V1\Copilot\CopilotAvailabilityController;
use App\Http\Controllers\Api\V1\Copilot\CopilotMessageController;
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
use App\Http\Controllers\Api\V1\Dashboard\ShowDashboardController;
use App\Http\Controllers\Api\V1\Guidance\ShowGuidanceController;
use App\Http\Controllers\Api\V1\Intake\CancelIntakeItemController;
use App\Http\Controllers\Api\V1\Intake\ConfirmIntakeController;
use App\Http\Controllers\Api\V1\Intake\CreateFileIntakeController;
use App\Http\Controllers\Api\V1\Intake\CreateLinkIntakeController;
use App\Http\Controllers\Api\V1\Intake\ListIntakeItemsController;
use App\Http\Controllers\Api\V1\Intake\ListIntakeSuggestionsController;
use App\Http\Controllers\Api\V1\Intake\RetryIntakeItemController;
use App\Http\Controllers\Api\V1\Intake\ShowIntakeItemController;
use App\Http\Controllers\Api\V1\Mentor\CreateMentorProfileController;
use App\Http\Controllers\Api\V1\Mentor\CreateMentorRequestController;
use App\Http\Controllers\Api\V1\Mentor\ListIncomingRequestsController;
use App\Http\Controllers\Api\V1\Mentor\ListMentorsController;
use App\Http\Controllers\Api\V1\Mentor\ListSentRequestsController;
use App\Http\Controllers\Api\V1\Mentor\ShowMentorController;
use App\Http\Controllers\Api\V1\Mentor\ShowOwnMentorProfileController;
use App\Http\Controllers\Api\V1\Mentor\TransitionMentorRequestController;
use App\Http\Controllers\Api\V1\Mentor\UpdateMentorProfileController;
use App\Http\Controllers\Api\V1\Onboarding\CompleteOnboardingController;
use App\Http\Controllers\Api\V1\Onboarding\ShowOnboardingController;
use App\Http\Controllers\Api\V1\Onboarding\UpdateOnboardingStepController;
use App\Http\Controllers\Api\V1\Planner\ArchiveTaskController;
use App\Http\Controllers\Api\V1\Planner\CreateFocusSessionController;
use App\Http\Controllers\Api\V1\Planner\CreateTaskController;
use App\Http\Controllers\Api\V1\Planner\DeleteFocusSessionController;
use App\Http\Controllers\Api\V1\Planner\DeleteTaskController;
use App\Http\Controllers\Api\V1\Planner\ListFocusSessionsController;
use App\Http\Controllers\Api\V1\Planner\ListTasksController;
use App\Http\Controllers\Api\V1\Planner\RestoreTaskController;
use App\Http\Controllers\Api\V1\Planner\ShowAgendaController;
use App\Http\Controllers\Api\V1\Planner\ShowFocusSessionController;
use App\Http\Controllers\Api\V1\Planner\ShowTaskController;
use App\Http\Controllers\Api\V1\Planner\ShowWeeklyPlannerController;
use App\Http\Controllers\Api\V1\Planner\UpdateFocusSessionController;
use App\Http\Controllers\Api\V1\Planner\UpdateTaskController;
use App\Http\Controllers\Api\V1\Planner\UpdateTaskStatusController;
use App\Http\Controllers\Api\V1\Prompts\DismissPromptController;
use App\Http\Controllers\Api\V1\Prompts\ListPromptsController;
use App\Http\Controllers\Api\V1\Prompts\RecordPromptCopyController;
use App\Http\Controllers\Api\V1\Prompts\SavePromptController;
use App\Http\Controllers\Api\V1\Prompts\ShowPromptController;
use App\Http\Controllers\Api\V1\Prompts\UndismissPromptController;
use App\Http\Controllers\Api\V1\Prompts\UnsavePromptController;
use App\Http\Controllers\Api\V1\Resources\CancelResourceUploadController;
use App\Http\Controllers\Api\V1\Resources\ConfirmResourceUploadController;
use App\Http\Controllers\Api\V1\Resources\CreateLinkResourceController;
use App\Http\Controllers\Api\V1\Resources\CreateResourceDownloadController;
use App\Http\Controllers\Api\V1\Resources\DeleteResourceController;
use App\Http\Controllers\Api\V1\Resources\InitiateFileResourceController;
use App\Http\Controllers\Api\V1\Resources\ListResourcesController;
use App\Http\Controllers\Api\V1\Resources\RetryResourceUploadController;
use App\Http\Controllers\Api\V1\Resources\ShowResourceController;
use App\Http\Controllers\Api\V1\Resources\UpdateResourceController;
use App\Http\Controllers\Api\V1\SecondBrain\AttachResearchSourceController;
use App\Http\Controllers\Api\V1\SecondBrain\CreateCollectionController;
use App\Http\Controllers\Api\V1\SecondBrain\CreateKnowledgeItemController;
use App\Http\Controllers\Api\V1\SecondBrain\CreateKnowledgeLinkController;
use App\Http\Controllers\Api\V1\SecondBrain\CreateKnowledgeNoteController;
use App\Http\Controllers\Api\V1\SecondBrain\CreateResearchTopicController;
use App\Http\Controllers\Api\V1\SecondBrain\DeleteCollectionController;
use App\Http\Controllers\Api\V1\SecondBrain\DeleteKnowledgeItemController;
use App\Http\Controllers\Api\V1\SecondBrain\DeleteKnowledgeLinkController;
use App\Http\Controllers\Api\V1\SecondBrain\DeleteKnowledgeNoteController;
use App\Http\Controllers\Api\V1\SecondBrain\DeleteResearchTopicController;
use App\Http\Controllers\Api\V1\SecondBrain\DetachResearchSourceController;
use App\Http\Controllers\Api\V1\SecondBrain\ListCollectionsController;
use App\Http\Controllers\Api\V1\SecondBrain\ListKnowledgeItemsController;
use App\Http\Controllers\Api\V1\SecondBrain\ListResearchTopicsController;
use App\Http\Controllers\Api\V1\SecondBrain\ShowCollectionController;
use App\Http\Controllers\Api\V1\SecondBrain\ShowKnowledgeItemController;
use App\Http\Controllers\Api\V1\SecondBrain\ShowResearchTopicController;
use App\Http\Controllers\Api\V1\SecondBrain\SyncKnowledgeCollectionsController;
use App\Http\Controllers\Api\V1\SecondBrain\SyncKnowledgeTagsController;
use App\Http\Controllers\Api\V1\SecondBrain\UpdateCollectionController;
use App\Http\Controllers\Api\V1\SecondBrain\UpdateKnowledgeItemController;
use App\Http\Controllers\Api\V1\SecondBrain\UpdateKnowledgeNoteController;
use App\Http\Controllers\Api\V1\SecondBrain\UpdateResearchSourceController;
use App\Http\Controllers\Api\V1\SecondBrain\UpdateResearchTopicController;
use App\Http\Controllers\Api\V1\TemplateCopies\ArchiveTemplateCopyController;
use App\Http\Controllers\Api\V1\TemplateCopies\ListTemplateCopiesController;
use App\Http\Controllers\Api\V1\TemplateCopies\RestoreTemplateCopyController;
use App\Http\Controllers\Api\V1\TemplateCopies\ShowTemplateCopyController;
use App\Http\Controllers\Api\V1\TemplateCopies\UpdateTemplateCopyController;
use App\Http\Controllers\Api\V1\Templates\CopyTemplateController;
use App\Http\Controllers\Api\V1\Templates\DismissTemplateController;
use App\Http\Controllers\Api\V1\Templates\ListTemplatesController;
use App\Http\Controllers\Api\V1\Templates\SaveTemplateController;
use App\Http\Controllers\Api\V1\Templates\ShowTemplateController;
use App\Http\Controllers\Api\V1\Templates\UndismissTemplateController;
use App\Http\Controllers\Api\V1\Templates\UnsaveTemplateController;
use App\Http\Controllers\Api\V1\Tools\DismissToolController;
use App\Http\Controllers\Api\V1\Tools\ListToolsController;
use App\Http\Controllers\Api\V1\Tools\SaveToolController;
use App\Http\Controllers\Api\V1\Tools\ShowToolController;
use App\Http\Controllers\Api\V1\Tools\UndismissToolController;
use App\Http\Controllers\Api\V1\Tools\UnsaveToolController;
use App\Http\Controllers\Api\V1\Workflows\DismissWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\ListWorkflowsController;
use App\Http\Controllers\Api\V1\Workflows\SaveWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\ShowWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\UndismissWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\UnsaveWorkflowController;
use App\Http\Middleware\EnsureAdminCapability;
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

            Route::prefix('tasks')
                ->name('tasks.')
                ->group(function (): void {
                    Route::get('/', ListTasksController::class)
                        ->middleware(['throttle:planner.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/', CreateTaskController::class)
                        ->middleware(['throttle:planner.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::get('/{task}', ShowTaskController::class)
                        ->where('task', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{task}', UpdateTaskController::class)
                        ->where('task', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{task}', DeleteTaskController::class)
                        ->where('task', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                    Route::put('/{task}/status', UpdateTaskStatusController::class)
                        ->where('task', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.write', 'can:academic.manage-own'])
                        ->name('status.update');
                    Route::put('/{task}/archive', ArchiveTaskController::class)
                        ->where('task', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.write', 'can:academic.manage-own'])
                        ->name('archive');
                    Route::delete('/{task}/archive', RestoreTaskController::class)
                        ->where('task', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.write', 'can:academic.manage-own'])
                        ->name('restore');
                });

            Route::prefix('focus-sessions')
                ->name('focus-sessions.')
                ->group(function (): void {
                    Route::get('/', ListFocusSessionsController::class)
                        ->middleware(['throttle:planner.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/', CreateFocusSessionController::class)
                        ->middleware(['throttle:planner.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::get('/{focus_session}', ShowFocusSessionController::class)
                        ->where('focus_session', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{focus_session}', UpdateFocusSessionController::class)
                        ->where('focus_session', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{focus_session}', DeleteFocusSessionController::class)
                        ->where('focus_session', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:planner.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                });

            Route::prefix('planner')
                ->name('planner.')
                ->group(function (): void {
                    Route::get('/agenda', ShowAgendaController::class)
                        ->middleware(['throttle:planner.read', 'can:academic.manage-own'])
                        ->name('agenda');
                    Route::get('/weekly', ShowWeeklyPlannerController::class)
                        ->middleware(['throttle:planner.read', 'can:academic.manage-own'])
                        ->name('weekly');
                });

            Route::prefix('resources')
                ->name('resources.')
                ->group(function (): void {
                    Route::get('/', ListResourcesController::class)
                        ->middleware(['throttle:resources.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/links', CreateLinkResourceController::class)
                        ->middleware(['throttle:resources.write', 'can:academic.manage-own'])
                        ->name('links.store');
                    Route::post('/files', InitiateFileResourceController::class)
                        ->middleware(['throttle:resources.upload', 'can:academic.manage-own'])
                        ->name('files.store');
                    Route::get('/{resource}', ShowResourceController::class)
                        ->where('resource', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:resources.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{resource}', UpdateResourceController::class)
                        ->where('resource', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:resources.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{resource}', DeleteResourceController::class)
                        ->where('resource', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:resources.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                    Route::post('/{resource}/upload-url', RetryResourceUploadController::class)
                        ->where('resource', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:resources.upload', 'can:academic.manage-own'])
                        ->name('upload.retry');
                    Route::post('/{resource}/confirm', ConfirmResourceUploadController::class)
                        ->where('resource', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:resources.upload', 'can:academic.manage-own'])
                        ->name('upload.confirm');
                    Route::post('/{resource}/download', CreateResourceDownloadController::class)
                        ->where('resource', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:resources.download', 'can:academic.manage-own'])
                        ->name('download');
                    Route::post('/{resource}/cancel', CancelResourceUploadController::class)
                        ->where('resource', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:resources.destructive', 'can:academic.manage-own'])
                        ->name('upload.cancel');
                });

            Route::prefix('tools')
                ->name('tools.')
                ->group(function (): void {
                    Route::get('/', ListToolsController::class)
                        ->middleware(['throttle:tools.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/{tool}', ShowToolController::class)
                        ->where('tool', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:tools.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{tool}/saved', SaveToolController::class)
                        ->where('tool', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:tools.preference', 'can:academic.manage-own'])
                        ->name('saved.store');
                    Route::delete('/{tool}/saved', UnsaveToolController::class)
                        ->where('tool', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:tools.preference', 'can:academic.manage-own'])
                        ->name('saved.destroy');
                    Route::put('/{tool}/dismissed', DismissToolController::class)
                        ->where('tool', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:tools.preference', 'can:academic.manage-own'])
                        ->name('dismissed.store');
                    Route::delete('/{tool}/dismissed', UndismissToolController::class)
                        ->where('tool', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:tools.preference', 'can:academic.manage-own'])
                        ->name('dismissed.destroy');
                });

            Route::prefix('prompts')
                ->name('prompts.')
                ->group(function (): void {
                    Route::get('/', ListPromptsController::class)
                        ->middleware(['throttle:prompts.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/{prompt}', ShowPromptController::class)
                        ->where('prompt', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:prompts.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{prompt}/saved', SavePromptController::class)
                        ->where('prompt', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:prompts.preference', 'can:academic.manage-own'])
                        ->name('saved.store');
                    Route::delete('/{prompt}/saved', UnsavePromptController::class)
                        ->where('prompt', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:prompts.preference', 'can:academic.manage-own'])
                        ->name('saved.destroy');
                    Route::put('/{prompt}/dismissed', DismissPromptController::class)
                        ->where('prompt', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:prompts.preference', 'can:academic.manage-own'])
                        ->name('dismissed.store');
                    Route::delete('/{prompt}/dismissed', UndismissPromptController::class)
                        ->where('prompt', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:prompts.preference', 'can:academic.manage-own'])
                        ->name('dismissed.destroy');
                    Route::post('/{prompt}/copies', RecordPromptCopyController::class)
                        ->where('prompt', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:prompts.preference', 'can:academic.manage-own'])
                        ->name('copies.store');
                });

            Route::prefix('workflows')
                ->name('workflows.')
                ->group(function (): void {
                    Route::get('/', ListWorkflowsController::class)
                        ->middleware(['throttle:workflows.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/{workflow}', ShowWorkflowController::class)
                        ->where('workflow', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:workflows.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{workflow}/saved', SaveWorkflowController::class)
                        ->where('workflow', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:workflows.preference', 'can:academic.manage-own'])
                        ->name('saved.store');
                    Route::delete('/{workflow}/saved', UnsaveWorkflowController::class)
                        ->where('workflow', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:workflows.preference', 'can:academic.manage-own'])
                        ->name('saved.destroy');
                    Route::put('/{workflow}/dismissed', DismissWorkflowController::class)
                        ->where('workflow', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:workflows.preference', 'can:academic.manage-own'])
                        ->name('dismissed.store');
                    Route::delete('/{workflow}/dismissed', UndismissWorkflowController::class)
                        ->where('workflow', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:workflows.preference', 'can:academic.manage-own'])
                        ->name('dismissed.destroy');
                });

            Route::prefix('templates')
                ->name('templates.')
                ->group(function (): void {
                    Route::get('/', ListTemplatesController::class)
                        ->middleware(['throttle:templates.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/{template}', ShowTemplateController::class)
                        ->where('template', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:templates.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{template}/saved', SaveTemplateController::class)
                        ->where('template', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:templates.preference', 'can:academic.manage-own'])
                        ->name('saved.store');
                    Route::delete('/{template}/saved', UnsaveTemplateController::class)
                        ->where('template', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:templates.preference', 'can:academic.manage-own'])
                        ->name('saved.destroy');
                    Route::put('/{template}/dismissed', DismissTemplateController::class)
                        ->where('template', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:templates.preference', 'can:academic.manage-own'])
                        ->name('dismissed.store');
                    Route::delete('/{template}/dismissed', UndismissTemplateController::class)
                        ->where('template', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:templates.preference', 'can:academic.manage-own'])
                        ->name('dismissed.destroy');
                    Route::post('/{template}/copies', CopyTemplateController::class)
                        ->where('template', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:template-copies.write', 'can:academic.manage-own'])
                        ->name('copies.store');
                });

            Route::prefix('template-copies')
                ->name('template-copies.')
                ->group(function (): void {
                    Route::get('/', ListTemplateCopiesController::class)
                        ->middleware(['throttle:template-copies.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/{copy}', ShowTemplateCopyController::class)
                        ->where('copy', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:template-copies.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{copy}', UpdateTemplateCopyController::class)
                        ->where('copy', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:template-copies.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::put('/{copy}/archive', ArchiveTemplateCopyController::class)
                        ->where('copy', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:template-copies.write', 'can:academic.manage-own'])
                        ->name('archive');
                    Route::delete('/{copy}/archive', RestoreTemplateCopyController::class)
                        ->where('copy', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:template-copies.write', 'can:academic.manage-own'])
                        ->name('restore');
                });

            Route::get('/guidance', ShowGuidanceController::class)
                ->middleware(['throttle:guidance.read', 'can:academic.manage-own'])
                ->name('guidance.show');

            Route::get('/dashboard', ShowDashboardController::class)
                ->middleware(['throttle:dashboard.read', 'can:academic.manage-own'])
                ->name('dashboard.show');

            Route::prefix('copilot')
                ->name('copilot.')
                ->group(function (): void {
                    Route::get('/availability', CopilotAvailabilityController::class)
                        ->middleware(['throttle:copilot.read', 'can:academic.manage-own'])
                        ->name('availability');
                    Route::post('/messages', CopilotMessageController::class)
                        ->middleware(['throttle:copilot.message', 'can:academic.manage-own'])
                        ->name('messages.store');
                });

            Route::prefix('intake')
                ->name('intake.')
                ->group(function (): void {
                    Route::get('/', ListIntakeItemsController::class)
                        ->middleware(['throttle:intake.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/links', CreateLinkIntakeController::class)
                        ->middleware(['throttle:intake.write', 'can:academic.manage-own'])
                        ->name('links.store');
                    Route::post('/files', CreateFileIntakeController::class)
                        ->middleware(['throttle:intake.write', 'can:academic.manage-own'])
                        ->name('files.store');
                    Route::get('/{item}', ShowIntakeItemController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:intake.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::post('/{item}/cancel', CancelIntakeItemController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:intake.write', 'can:academic.manage-own'])
                        ->name('cancel');
                    Route::post('/{item}/retry', RetryIntakeItemController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:intake.write', 'can:academic.manage-own'])
                        ->name('retry');
                    Route::get('/{item}/suggestions', ListIntakeSuggestionsController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:intake.read', 'can:academic.manage-own'])
                        ->name('suggestions.index');
                    Route::post('/{item}/confirmation', ConfirmIntakeController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:intake.write', 'can:academic.manage-own'])
                        ->name('confirmation.store');
                });

            Route::prefix('collections')
                ->name('collections.')
                ->group(function (): void {
                    Route::get('/', ListCollectionsController::class)
                        ->middleware(['throttle:brain.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/', CreateCollectionController::class)
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::get('/{collection}', ShowCollectionController::class)
                        ->where('collection', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{collection}', UpdateCollectionController::class)
                        ->where('collection', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{collection}', DeleteCollectionController::class)
                        ->where('collection', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                });

            Route::prefix('knowledge')
                ->name('knowledge.')
                ->group(function (): void {
                    Route::get('/', ListKnowledgeItemsController::class)
                        ->middleware(['throttle:brain.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/', CreateKnowledgeItemController::class)
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::get('/{item}', ShowKnowledgeItemController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{item}', UpdateKnowledgeItemController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{item}', DeleteKnowledgeItemController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                    Route::post('/{item}/notes', CreateKnowledgeNoteController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('notes.store');
                    Route::put('/{item}/notes/{note}', UpdateKnowledgeNoteController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->where('note', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('notes.update');
                    Route::delete('/{item}/notes/{note}', DeleteKnowledgeNoteController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->where('note', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.destructive', 'can:academic.manage-own'])
                        ->name('notes.destroy');
                    Route::put('/{item}/tags', SyncKnowledgeTagsController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('tags.sync');
                    Route::put('/{item}/collections', SyncKnowledgeCollectionsController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('collections.sync');
                    Route::post('/{item}/links', CreateKnowledgeLinkController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('links.store');
                    Route::delete('/{item}/links/{link}', DeleteKnowledgeLinkController::class)
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->where('link', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.destructive', 'can:academic.manage-own'])
                        ->name('links.destroy');
                });

            Route::prefix('research-topics')
                ->name('research-topics.')
                ->group(function (): void {
                    Route::get('/', ListResearchTopicsController::class)
                        ->middleware(['throttle:brain.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::post('/', CreateResearchTopicController::class)
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::get('/{topic}', ShowResearchTopicController::class)
                        ->where('topic', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::put('/{topic}', UpdateResearchTopicController::class)
                        ->where('topic', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{topic}', DeleteResearchTopicController::class)
                        ->where('topic', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                    Route::post('/{topic}/sources', AttachResearchSourceController::class)
                        ->where('topic', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('sources.store');
                    Route::put('/{topic}/sources/{item}', UpdateResearchSourceController::class)
                        ->where('topic', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.write', 'can:academic.manage-own'])
                        ->name('sources.update');
                    Route::delete('/{topic}/sources/{item}', DetachResearchSourceController::class)
                        ->where('topic', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->where('item', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:brain.destructive', 'can:academic.manage-own'])
                        ->name('sources.destroy');
                });

            Route::prefix('communities')
                ->name('communities.')
                ->group(function (): void {
                    Route::get('/', ListCommunitiesController::class)
                        ->middleware(['throttle:community.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/{community}', ShowCommunityController::class)
                        ->where('community', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::post('/{community}/membership', JoinCommunityController::class)
                        ->where('community', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.write', 'can:academic.manage-own'])
                        ->name('membership.store');
                    Route::delete('/{community}/membership', LeaveCommunityController::class)
                        ->where('community', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.write', 'can:academic.manage-own'])
                        ->name('membership.destroy');
                    Route::get('/{community}/posts', ListCommunityPostsController::class)
                        ->where('community', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.read', 'can:academic.manage-own'])
                        ->name('posts.index');
                    Route::post('/{community}/posts', CreatePostController::class)
                        ->where('community', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.write', 'can:academic.manage-own'])
                        ->name('posts.store');
                });

            Route::get('feed', ListFeedController::class)
                ->middleware(['throttle:community.read', 'can:academic.manage-own'])
                ->name('feed.index');

            Route::prefix('posts')
                ->name('posts.')
                ->group(function (): void {
                    Route::get('/{post}', ShowPostController::class)
                        ->where('post', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::patch('/{post}', UpdatePostController::class)
                        ->where('post', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.write', 'can:academic.manage-own'])
                        ->name('update');
                    Route::delete('/{post}', DeletePostController::class)
                        ->where('post', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                    Route::get('/{post}/comments', ListCommentsController::class)
                        ->where('post', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.read', 'can:academic.manage-own'])
                        ->name('comments.index');
                    Route::post('/{post}/comments', CreateCommentController::class)
                        ->where('post', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.write', 'can:academic.manage-own'])
                        ->name('comments.store');
                    Route::post('/{post}/reports', CreatePostReportController::class)
                        ->where('post', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.report', 'can:academic.manage-own'])
                        ->name('reports.store');
                });

            Route::prefix('comments')
                ->name('comments.')
                ->group(function (): void {
                    Route::delete('/{comment}', DeleteCommentController::class)
                        ->where('comment', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.destructive', 'can:academic.manage-own'])
                        ->name('destroy');
                    Route::post('/{comment}/reports', CreateCommentReportController::class)
                        ->where('comment', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:community.report', 'can:academic.manage-own'])
                        ->name('reports.store');
                });

            Route::prefix('moderation')
                ->name('moderation.')
                ->group(function (): void {
                    Route::get('/reports', ListModerationReportsController::class)
                        ->middleware(['throttle:moderation.read', 'can:community.moderate'])
                        ->name('reports.index');
                    Route::patch('/reports/{report}/resolution', ResolveReportController::class)
                        ->where('report', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:moderation.write', 'can:community.moderate'])
                        ->name('reports.resolve');
                });

            Route::prefix('mentors')
                ->name('mentors.')
                ->group(function (): void {
                    Route::get('/', ListMentorsController::class)
                        ->middleware(['throttle:mentor.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/{mentor}', ShowMentorController::class)
                        ->where('mentor', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:mentor.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::post('/{mentor}/requests', CreateMentorRequestController::class)
                        ->where('mentor', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:mentor.request', 'can:academic.manage-own'])
                        ->name('requests.store');
                });

            Route::prefix('mentor-profile')
                ->name('mentor-profile.')
                ->group(function (): void {
                    Route::get('/', ShowOwnMentorProfileController::class)
                        ->middleware(['throttle:mentor.read', 'can:academic.manage-own'])
                        ->name('show');
                    Route::post('/', CreateMentorProfileController::class)
                        ->middleware(['throttle:mentor.write', 'can:academic.manage-own'])
                        ->name('store');
                    Route::patch('/', UpdateMentorProfileController::class)
                        ->middleware(['throttle:mentor.write', 'can:academic.manage-own'])
                        ->name('update');
                });

            Route::prefix('mentor-requests')
                ->name('mentor-requests.')
                ->group(function (): void {
                    Route::get('/', ListSentRequestsController::class)
                        ->middleware(['throttle:mentor.read', 'can:academic.manage-own'])
                        ->name('index');
                    Route::get('/incoming', ListIncomingRequestsController::class)
                        ->middleware(['throttle:mentor.read', 'can:academic.manage-own'])
                        ->name('incoming');
                    Route::patch('/{mentorRequest}', TransitionMentorRequestController::class)
                        ->where('mentorRequest', '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}')
                        ->middleware(['throttle:mentor.write', 'can:academic.manage-own'])
                        ->name('update');
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

            // Every operational route requires a verified, active admin-access holder;
            // per-route capability gates then narrow each action.
            Route::middleware(RequireAdminAccess::class)->group(function (): void {
                $ulid = '[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}';

                Route::get('/me', AdminCurrentUserController::class)->name('me');

                Route::prefix('users')->name('users.')->group(function () use ($ulid): void {
                    Route::get('/', ListUsersController::class)
                        ->middleware(['throttle:admin.read', EnsureAdminCapability::class.':authorization.roles-view'])
                        ->name('index');
                    Route::get('/{user}', ShowUserController::class)
                        ->where('user', $ulid)
                        ->middleware(['throttle:admin.read', EnsureAdminCapability::class.':authorization.roles-view'])
                        ->name('show');
                    Route::post('/{user}/suspension', SuspendUserController::class)
                        ->where('user', $ulid)
                        ->middleware(['throttle:admin.write', EnsureAdminCapability::class.':users.suspend'])
                        ->name('suspend');
                    Route::post('/{user}/reactivation', ReactivateUserController::class)
                        ->where('user', $ulid)
                        ->middleware(['throttle:admin.write', EnsureAdminCapability::class.':users.suspend'])
                        ->name('reactivate');
                    Route::put('/{user}/roles', ChangeUserRolesController::class)
                        ->where('user', $ulid)
                        ->middleware(['throttle:admin.write', EnsureAdminCapability::class.':authorization.roles-assign'])
                        ->name('roles.update');
                });

                Route::get('/roles', ListRolesController::class)
                    ->middleware(['throttle:admin.read', EnsureAdminCapability::class.':authorization.roles-view'])
                    ->name('roles.index');

                Route::prefix('mentors')->name('mentors.')->group(function () use ($ulid): void {
                    Route::get('/', ListAdminMentorsController::class)
                        ->middleware(['throttle:admin.read', EnsureAdminCapability::class.':mentors.curate'])
                        ->name('index');
                    Route::patch('/{mentor}/verification', SetMentorVerificationController::class)
                        ->where('mentor', $ulid)
                        ->middleware(['throttle:admin.write', EnsureAdminCapability::class.':mentors.curate'])
                        ->name('verification');
                });

                Route::prefix('reports')->name('reports.')->group(function () use ($ulid): void {
                    Route::get('/', ListAdminReportsController::class)
                        ->middleware(['throttle:admin.read', EnsureAdminCapability::class.':moderation.scoped,moderation.global'])
                        ->name('index');
                    Route::patch('/{report}/resolution', ResolveAdminReportController::class)
                        ->where('report', $ulid)
                        ->middleware(['throttle:admin.write', EnsureAdminCapability::class.':moderation.scoped,moderation.global'])
                        ->name('resolve');
                });

                Route::get('/audit-events', ListAuditEventsController::class)
                    ->middleware(['throttle:admin.read', EnsureAdminCapability::class.':audit.view-all'])
                    ->name('audit.index');
            });
        });
    });
