<?php

declare(strict_types=1);

namespace App\Domains\Tools\Support;

use App\Domains\Tools\Enums\ToolReviewState;
use App\Domains\Tools\Models\Tool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class PublishedToolVisibility
{
    /** @param Builder<Tool> $query */
    public static function apply(Builder $query): void
    {
        $query
            ->where('tools.state', ToolReviewState::Published->value)
            ->whereNotNull('tools.published_at')
            ->where('tools.published_at', '<=', now())
            ->whereNull('tools.archived_at')
            ->whereNotNull('tools.last_reviewed_at')
            ->where('tools.last_reviewed_at', '<=', now())
            ->whereNotNull('tools.purpose')
            ->whereNotNull('tools.selection_reason')
            ->whereNotNull('tools.usage_guidance')
            ->whereNotNull('tools.limitations')
            ->whereNotNull('tools.cost_note')
            ->whereNotNull('tools.privacy_note')
            ->whereNotNull('tools.external_url')
            ->whereNotNull('tools.provenance')
            ->whereRaw("jsonb_typeof(tools.use_cases) = 'array'")
            ->whereRaw('jsonb_array_length(tools.use_cases) > 0');
    }

    public static function allows(Tool $tool): bool
    {
        $state = $tool->getAttribute('state');
        $publishedAt = $tool->getAttribute('published_at');
        $reviewedAt = $tool->getAttribute('last_reviewed_at');
        $useCases = $tool->getAttribute('use_cases');

        return ($state instanceof ToolReviewState ? $state : ToolReviewState::tryFrom((string) $state))
                === ToolReviewState::Published
            && $publishedAt instanceof CarbonInterface
            && ! $publishedAt->isFuture()
            && $tool->getAttribute('archived_at') === null
            && $reviewedAt instanceof CarbonInterface
            && ! $reviewedAt->isFuture()
            && self::hasText($tool, 'purpose')
            && self::hasText($tool, 'selection_reason')
            && self::hasText($tool, 'usage_guidance')
            && self::hasText($tool, 'limitations')
            && self::hasText($tool, 'cost_note')
            && self::hasText($tool, 'privacy_note')
            && self::hasText($tool, 'external_url')
            && self::hasText($tool, 'provenance')
            && is_array($useCases)
            && $useCases !== [];
    }

    private static function hasText(Tool $tool, string $attribute): bool
    {
        $value = $tool->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
