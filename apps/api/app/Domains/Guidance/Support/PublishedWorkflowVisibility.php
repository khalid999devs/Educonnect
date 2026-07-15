<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Support;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\WorkflowRecipe;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PublishedWorkflowVisibility
{
    /** @param Builder<WorkflowRecipe> $query */
    public static function apply(Builder $query): void
    {
        $query
            ->where('workflow_recipes.state', GuidanceReviewState::Published->value)
            ->whereNotNull('workflow_recipes.published_at')
            ->where('workflow_recipes.published_at', '<=', now())
            ->whereNull('workflow_recipes.archived_at')
            ->whereNotNull('workflow_recipes.last_reviewed_at')
            ->where('workflow_recipes.last_reviewed_at', '<=', now())
            ->whereNotNull('workflow_recipes.goal')
            ->whereNotNull('workflow_recipes.expected_outcome')
            ->whereNotNull('workflow_recipes.integrity_note')
            ->whereNotNull('workflow_recipes.provenance')
            ->whereExists(static function (QueryBuilder $steps): void {
                $steps
                    ->selectRaw('1')
                    ->from('workflow_steps')
                    ->whereColumn('workflow_steps.workflow_recipe_id', 'workflow_recipes.id');
            });
    }

    public static function allows(WorkflowRecipe $workflow): bool
    {
        $state = $workflow->getAttribute('state');
        $publishedAt = $workflow->getAttribute('published_at');
        $reviewedAt = $workflow->getAttribute('last_reviewed_at');

        return ($state instanceof GuidanceReviewState ? $state : GuidanceReviewState::tryFrom((string) $state))
                === GuidanceReviewState::Published
            && $publishedAt instanceof CarbonInterface
            && ! $publishedAt->isFuture()
            && $workflow->getAttribute('archived_at') === null
            && $reviewedAt instanceof CarbonInterface
            && ! $reviewedAt->isFuture()
            && self::hasText($workflow, 'goal')
            && self::hasText($workflow, 'expected_outcome')
            && self::hasText($workflow, 'integrity_note')
            && self::hasText($workflow, 'provenance');
    }

    private static function hasText(WorkflowRecipe $workflow, string $attribute): bool
    {
        $value = $workflow->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
