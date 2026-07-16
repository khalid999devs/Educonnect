<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Data\BrainListResult;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\SecondBrain\Support\BrainCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListOwnedResearchTopics
{
    /** @return BrainListResult<ResearchTopic> */
    public function execute(
        User $user,
        ?string $search,
        string $sort,
        int $perPage,
    ): BrainListResult {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use ($user, $search, $sort, $perPage, $ownsTransaction): BrainListResult {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $sortDefinition = BrainCursorSort::resolve($sort);
                $query = ResearchTopic::query()
                    ->select([
                        'research_topics.*',
                        $sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                    ])
                    ->where('user_id', $user->getKey())
                    ->withCount('sources');

                if ($search !== null) {
                    $pattern = BrainSearchPattern::prefix($search);
                    $query->whereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$pattern]);
                }

                $paginator = $query
                    ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                    ->orderBy('public_id', $sortDefinition['direction'])
                    ->cursorPaginate($perPage)
                    ->withQueryString();
                $total = DB::table('research_topics')->where('user_id', $user->getKey())->count();

                return new BrainListResult($paginator, ['total' => $total]);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'research.list');
        }
    }
}
