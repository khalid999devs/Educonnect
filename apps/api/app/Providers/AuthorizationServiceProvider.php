<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Policies\AcademicTermPolicy;
use App\Domains\Courses\Policies\CoursePolicy;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Policies\PromptTemplatePolicy;
use App\Domains\Guidance\Policies\UserPromptCopyPolicy;
use App\Domains\Guidance\Policies\UserPromptPreferencePolicy;
use App\Domains\Guidance\Policies\UserWorkflowPreferencePolicy;
use App\Domains\Guidance\Policies\WorkflowRecipePolicy;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Policies\IntakeItemPolicy;
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
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\SecondBrain\Policies\CollectionPolicy;
use App\Domains\SecondBrain\Policies\KnowledgeItemPolicy;
use App\Domains\SecondBrain\Policies\ResearchTopicPolicy;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Templates\Policies\TemplatePolicy;
use App\Domains\Templates\Policies\UserTemplateCopyPolicy;
use App\Domains\Templates\Policies\UserTemplatePreferencePolicy;
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
        Gate::policy(PromptTemplate::class, PromptTemplatePolicy::class);
        Gate::policy(UserPromptPreference::class, UserPromptPreferencePolicy::class);
        Gate::policy(UserPromptCopy::class, UserPromptCopyPolicy::class);
        Gate::policy(WorkflowRecipe::class, WorkflowRecipePolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
        Gate::policy(UserTemplatePreference::class, UserTemplatePreferencePolicy::class);
        Gate::policy(UserTemplateCopy::class, UserTemplateCopyPolicy::class);
        Gate::policy(IntakeItem::class, IntakeItemPolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(KnowledgeItem::class, KnowledgeItemPolicy::class);
        Gate::policy(ResearchTopic::class, ResearchTopicPolicy::class);
        Gate::policy(UserWorkflowPreference::class, UserWorkflowPreferencePolicy::class);

        foreach (CapabilityKey::cases() as $capability) {
            Gate::define(
                $capability->value,
                static fn (User $user): bool => $user->hasCapability($capability),
            );
        }
    }
}
