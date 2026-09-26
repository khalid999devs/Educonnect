<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Support;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\PromptTemplate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PublishedPromptVisibility
{
    /** @param Builder<PromptTemplate> $query */
    public static function apply(Builder $query): void
    {
        $query
            ->where('prompt_templates.state', GuidanceReviewState::Published->value)
            ->whereNotNull('prompt_templates.published_at')
            ->where('prompt_templates.published_at', '<=', now())
            ->whereNull('prompt_templates.archived_at')
            ->whereNotNull('prompt_templates.last_reviewed_at')
            ->where('prompt_templates.last_reviewed_at', '<=', now())
            ->whereNotNull('prompt_templates.purpose')
            ->whereNotNull('prompt_templates.template_body')
            ->whereNotNull('prompt_templates.expected_output')
            ->whereNotNull('prompt_templates.integrity_note')
            ->whereNotNull('prompt_templates.provenance')
            ->whereRaw("jsonb_typeof(prompt_templates.placeholders) = 'array'")
            ->whereRaw('jsonb_array_length(prompt_templates.placeholders) > 0')
            ->whereExists(static function (QueryBuilder $related): void {
                $related
                    ->selectRaw('1')
                    ->from('prompt_template_tool')
                    ->whereColumn('prompt_template_tool.prompt_template_id', 'prompt_templates.id');
            });
    }

    public static function allows(PromptTemplate $prompt): bool
    {
        $state = $prompt->getAttribute('state');
        $publishedAt = $prompt->getAttribute('published_at');
        $reviewedAt = $prompt->getAttribute('last_reviewed_at');
        $placeholders = $prompt->getAttribute('placeholders');

        return ($state instanceof GuidanceReviewState ? $state : GuidanceReviewState::tryFrom((string) $state))
                === GuidanceReviewState::Published
            && $publishedAt instanceof CarbonInterface
            && ! $publishedAt->isFuture()
            && $prompt->getAttribute('archived_at') === null
            && $reviewedAt instanceof CarbonInterface
            && ! $reviewedAt->isFuture()
            && self::hasText($prompt, 'purpose')
            && self::hasText($prompt, 'template_body')
            && self::hasText($prompt, 'expected_output')
            && self::hasText($prompt, 'integrity_note')
            && self::hasText($prompt, 'provenance')
            && is_array($placeholders)
            && $placeholders !== [];
    }

    private static function hasText(PromptTemplate $prompt, string $attribute): bool
    {
        $value = $prompt->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
