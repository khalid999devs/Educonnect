<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Guidance\Support\PromptCursorSort;
use App\Domains\Guidance\Support\PublishedPromptVisibility;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Support\PublishedToolVisibility;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ListPublishedPrompts
{
    /** @return CursorPaginator<int, PromptTemplate> */
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
            throw new InvalidArgumentException('Prompt page size must be between 1 and 50.');
        }

        try {
            $sortDefinition = PromptCursorSort::for($sort);
            $query = PromptTemplate::query()
                ->select('prompt_templates.*')
                ->selectRaw($sortDefinition['expression'].' as '.$sortDefinition['cursor_column'])
                ->addSelect([
                    'viewer_preference_state' => UserPromptPreference::query()
                        ->select('state')
                        ->whereColumn('user_prompt_preferences.prompt_template_id', 'prompt_templates.id')
                        ->where('user_prompt_preferences.user_id', $user->getKey())
                        ->limit(1),
                    'viewer_copy_count' => UserPromptCopy::query()
                        ->select('copy_count')
                        ->whereColumn('user_prompt_copies.prompt_template_id', 'prompt_templates.id')
                        ->where('user_prompt_copies.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->with([
                    'category',
                    'relatedTools' => static function (Relation $tools): void {
                        if (! $tools instanceof BelongsToMany) {
                            return;
                        }

                        /** @var Builder<Tool> $builder */
                        $builder = $tools->getQuery();
                        PublishedToolVisibility::apply($builder);
                        $tools->orderBy('tools.name');
                    },
                ]);

            PublishedPromptVisibility::apply($query);

            if ($category !== null) {
                $query->whereHas(
                    'category',
                    static fn (Builder $categories): Builder => $categories->where('slug', $category),
                );
            }

            if ($search !== null) {
                $query->whereRaw(
                    "LOWER(CONCAT_WS(' ', prompt_templates.title, prompt_templates.purpose, prompt_templates.template_body, prompt_templates.expected_output, prompt_templates.integrity_note, prompt_templates.provenance)) LIKE ? ESCAPE '\\'",
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
            throw GuidancePersistenceFailure::fromQueryException($exception, 'prompt.list');
        }
    }

    /** @param Builder<PromptTemplate> $query */
    private function applyPreferenceFilter(Builder $query, int $userId, string $preference): void
    {
        if ($preference === 'all') {
            return;
        }

        if ($preference === 'none') {
            $query->whereNotExists(static function (QueryBuilder $preferences) use ($userId): void {
                $preferences
                    ->selectRaw('1')
                    ->from('user_prompt_preferences')
                    ->whereColumn('user_prompt_preferences.prompt_template_id', 'prompt_templates.id')
                    ->where('user_prompt_preferences.user_id', $userId);
            });

            return;
        }

        $state = GuidancePreferenceState::tryFrom($preference);

        if (! $state instanceof GuidancePreferenceState) {
            throw new InvalidArgumentException('Unsupported prompt preference filter.');
        }

        $query->whereExists(static function (QueryBuilder $preferences) use ($userId, $state): void {
            $preferences
                ->selectRaw('1')
                ->from('user_prompt_preferences')
                ->whereColumn('user_prompt_preferences.prompt_template_id', 'prompt_templates.id')
                ->where('user_prompt_preferences.user_id', $userId)
                ->where('user_prompt_preferences.state', $state->value);
        });
    }

    private function containsPattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search));

        return '%'.$escaped.'%';
    }
}
