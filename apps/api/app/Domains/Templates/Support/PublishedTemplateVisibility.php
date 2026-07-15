<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Templates\Models\Template;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PublishedTemplateVisibility
{
    /** @param Builder<Template> $query */
    public static function apply(Builder $query): void
    {
        $query
            ->where('templates.state', GuidanceReviewState::Published->value)
            ->whereNotNull('templates.published_at')
            ->where('templates.published_at', '<=', now())
            ->whereNull('templates.archived_at')
            ->whereNotNull('templates.last_reviewed_at')
            ->where('templates.last_reviewed_at', '<=', now())
            ->whereNotNull('templates.summary')
            ->whereNotNull('templates.integrity_note')
            ->whereNotNull('templates.provenance')
            ->whereExists(static function (QueryBuilder $versions): void {
                $versions
                    ->selectRaw('1')
                    ->from('template_versions')
                    ->whereColumn('template_versions.template_id', 'templates.id');
            });
    }

    public static function allows(Template $template): bool
    {
        $state = $template->getAttribute('state');
        $publishedAt = $template->getAttribute('published_at');
        $reviewedAt = $template->getAttribute('last_reviewed_at');

        return ($state instanceof GuidanceReviewState ? $state : GuidanceReviewState::tryFrom((string) $state))
                === GuidanceReviewState::Published
            && $publishedAt instanceof CarbonInterface
            && ! $publishedAt->isFuture()
            && $template->getAttribute('archived_at') === null
            && $reviewedAt instanceof CarbonInterface
            && ! $reviewedAt->isFuture()
            && self::hasText($template, 'summary')
            && self::hasText($template, 'integrity_note')
            && self::hasText($template, 'provenance');
    }

    private static function hasText(Template $template, string $attribute): bool
    {
        $value = $template->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
