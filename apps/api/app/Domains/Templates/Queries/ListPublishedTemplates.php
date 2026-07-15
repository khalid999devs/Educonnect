<?php

declare(strict_types=1);

namespace App\Domains\Templates\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Templates\Support\PublishedTemplateVisibility;
use App\Domains\Templates\Support\TemplateCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ListPublishedTemplates
{
    /** @return CursorPaginator<int, Template> */
    public function execute(
        User $user,
        ?string $search,
        ?string $category,
        string $preference,
        string $sort,
        int $perPage,
    ): CursorPaginator {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Template page size must be between 1 and 50.');
        }

        try {
            $sortDefinition = TemplateCursorSort::for($sort);
            $query = Template::query()
                ->select('templates.*')
                ->selectRaw($sortDefinition['expression'].' as '.$sortDefinition['cursor_column'])
                ->addSelect([
                    'viewer_preference_state' => UserTemplatePreference::query()
                        ->select('state')
                        ->whereColumn('user_template_preferences.template_id', 'templates.id')
                        ->where('user_template_preferences.user_id', $user->getKey())
                        ->limit(1),
                    'viewer_active_copy_count' => UserTemplateCopy::query()
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('user_template_copies.template_id', 'templates.id')
                        ->where('user_template_copies.user_id', $user->getKey())
                        ->whereNull('user_template_copies.archived_at'),
                ])
                ->with(['category', 'latestVersion']);

            PublishedTemplateVisibility::apply($query);

            if ($category !== null) {
                $query->whereHas(
                    'category',
                    static fn (Builder $categories): Builder => $categories->where('slug', $category),
                );
            }

            if ($search !== null) {
                $query->whereRaw(
                    "LOWER(CONCAT_WS(' ', templates.title, templates.summary, templates.integrity_note, templates.provenance)) LIKE ? ESCAPE '\\'",
                    [$this->containsPattern($search)],
                );
            }

            $this->applyPreferenceFilter($query, (int) $user->getKey(), $preference);

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.list');
        }
    }

    /** @param Builder<Template> $query */
    private function applyPreferenceFilter(Builder $query, int $userId, string $preference): void
    {
        if ($preference === 'all') {
            return;
        }

        if ($preference === 'none') {
            $query->whereNotExists(static function (QueryBuilder $preferences) use ($userId): void {
                $preferences
                    ->selectRaw('1')
                    ->from('user_template_preferences')
                    ->whereColumn('user_template_preferences.template_id', 'templates.id')
                    ->where('user_template_preferences.user_id', $userId);
            });

            return;
        }

        $state = GuidancePreferenceState::tryFrom($preference);

        if (! $state instanceof GuidancePreferenceState) {
            throw new InvalidArgumentException('Unsupported template preference filter.');
        }

        $query->whereExists(static function (QueryBuilder $preferences) use ($userId, $state): void {
            $preferences
                ->selectRaw('1')
                ->from('user_template_preferences')
                ->whereColumn('user_template_preferences.template_id', 'templates.id')
                ->where('user_template_preferences.user_id', $userId)
                ->where('user_template_preferences.state', $state->value);
        });
    }

    private function containsPattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search));

        return '%'.$escaped.'%';
    }
}
