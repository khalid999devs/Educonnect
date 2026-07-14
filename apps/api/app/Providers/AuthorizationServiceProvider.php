<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Policies\OnboardingProgressPolicy;
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

        foreach (CapabilityKey::cases() as $capability) {
            Gate::define(
                $capability->value,
                static fn (User $user): bool => $user->hasCapability($capability),
            );
        }
    }
}
