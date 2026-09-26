<?php

declare(strict_types=1);

namespace App\Domains\Tools\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Domains\Tools\Exceptions\ToolPersistenceFailure;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Tools\Support\PublishedToolVisibility;
use App\Domains\Tools\Support\ToolCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ListPublishedTools
{
    /** @return CursorPaginator<int, Tool> */
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
            throw new InvalidArgumentException('Tool page size must be between 1 and 50.');
        }

        try {
            $sortDefinition = ToolCursorSort::for($sort);
            $query = Tool::query()
                ->select('tools.*')
                ->selectRaw($sortDefinition['expression'].' as '.$sortDefinition['cursor_column'])
                ->addSelect([
                    'viewer_preference_state' => UserToolPreference::query()
                        ->select('state')
                        ->whereColumn('user_tool_preferences.tool_id', 'tools.id')
                        ->where('user_tool_preferences.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->with('category');

            PublishedToolVisibility::apply($query);

            if ($category !== null) {
                $query->whereHas(
                    'category',
                    static fn (Builder $categories): Builder => $categories->where('slug', $category),
                );
            }

            if ($search !== null) {
                $query->whereRaw(
                    "LOWER(CONCAT_WS(' ', tools.name, tools.purpose, tools.selection_reason, tools.use_cases::text, tools.usage_guidance, tools.limitations, tools.cost_note, tools.privacy_note, tools.provenance)) LIKE ? ESCAPE '\\'",
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
            throw ToolPersistenceFailure::fromQueryException($exception, 'tool.list');
        }
    }

    /** @param Builder<Tool> $query */
    private function applyPreferenceFilter(Builder $query, int $userId, string $preference): void
    {
        if ($preference === 'all') {
            return;
        }

        if ($preference === 'none') {
            $query->whereNotExists(static function (QueryBuilder $preferences) use ($userId): void {
                $preferences
                    ->selectRaw('1')
                    ->from('user_tool_preferences')
                    ->whereColumn('user_tool_preferences.tool_id', 'tools.id')
                    ->where('user_tool_preferences.user_id', $userId);
            });

            return;
        }

        $state = ToolPreferenceState::tryFrom($preference);

        if (! $state instanceof ToolPreferenceState) {
            throw new InvalidArgumentException('Unsupported tool preference filter.');
        }

        $query->whereExists(static function (QueryBuilder $preferences) use ($userId, $state): void {
            $preferences
                ->selectRaw('1')
                ->from('user_tool_preferences')
                ->whereColumn('user_tool_preferences.tool_id', 'tools.id')
                ->where('user_tool_preferences.user_id', $userId)
                ->where('user_tool_preferences.state', $state->value);
        });
    }

    private function containsPattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search));

        return '%'.$escaped.'%';
    }
}
