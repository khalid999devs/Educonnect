<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

use App\Domains\Intake\Contracts\AIProvider;
use Illuminate\Contracts\Foundation\Application;

/**
 * Selects the approved provider chain for intake classification: the
 * configured primary first, then the deterministic rule-based fallback so a
 * failing or invalid provider never blocks the non-AI organization path.
 */
final readonly class ClassificationPolicy
{
    public function __construct(private Application $app) {}

    /** @return non-empty-list<AIProvider> */
    public function providers(): array
    {
        $primary = $this->app->make(AIProvider::class);
        $fallback = $this->app->make(RuleBasedClassificationProvider::class);

        if ($primary->name() === $fallback->name()) {
            return [$fallback];
        }

        return [$primary, $fallback];
    }

    public function maxOutputRetries(): int
    {
        return max(0, (int) config('intake.classification.max_output_retries'));
    }

    public function maxSuggestions(): int
    {
        return max(1, (int) config('intake.classification.max_suggestions'));
    }

    public function maxCourses(): int
    {
        return max(1, (int) config('intake.classification.max_courses'));
    }
}
