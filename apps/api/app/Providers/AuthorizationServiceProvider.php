<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Policies\AcademicTermPolicy;
use App\Domains\Courses\Policies\CoursePolicy;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Policies\OnboardingProgressPolicy;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Planner\Policies\FocusSessionPolicy;
use App\Domains\Planner\Policies\TaskPolicy;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Policies\ResourcePolicy;
use App\Domains\Resources\Policies\StoredFilePolicy;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Tools\Policies\ToolPolicy;
use App\Domains\Tools\Policies\UserToolPreferencePolicy;
use App\Domains\Users\Models\User;
use App\Domains\Users\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(OnboardingProgress::class, OnboardingProgressPolicy::class);
        Gate::policy(AcademicTerm::class, AcademicTermPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(FocusSession::class, FocusSessionPolicy::class);
        Gate::policy(Resource::class, ResourcePolicy::class);
        Gate::policy(StoredFile::class, StoredFilePolicy::class);
        Gate::policy(Tool::class, ToolPolicy::class);
        Gate::policy(UserToolPreference::class, UserToolPreferencePolicy::class);

        foreach (CapabilityKey::cases() as $capability) {
            Gate::define(
                $capability->value,
                static fn (User $user): bool => $user->hasCapability($capability),
            );
        }
    }
}
