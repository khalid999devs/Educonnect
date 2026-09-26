<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Data;

use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Onboarding\Enums\OnboardingStepState;
use Carbon\CarbonInterface;

final readonly class OnboardingSnapshot
{
    /**
     * @param  array<string, OnboardingStepState>  $steps
     * @param  array{institution_name: ?string, institution_country_code: ?string, department: ?string, degree: ?string, major: ?string, year_label: ?string, term_label: ?string}  $profile
     * @param  list<array{title: string, code: ?string}>  $courses
     * @param  list<string>  $goals
     * @param  list<string>  $problems
     * @param  array{url: string, title: ?string}|null  $firstSource
     */
    public function __construct(
        public int $version,
        public OnboardingStatus $status,
        public ?OnboardingStep $currentStep,
        public bool $canComplete,
        public ?CarbonInterface $completedAt,
        public array $steps,
        public array $profile,
        public array $courses,
        public array $goals,
        public array $problems,
        public ?array $firstSource,
    ) {}
}
