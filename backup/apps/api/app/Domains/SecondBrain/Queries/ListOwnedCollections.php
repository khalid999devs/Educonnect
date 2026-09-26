<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Data\BrainListResult;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Support\BrainCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListOwnedCollections
{
    /** @return BrainListResult<Collection> */
    public function execute(
        User $user,
        ?string $search,
        ?string $kind,
        string $sort,
        int $perPage,
    ): BrainListResult {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use ($user, $search, $kind, $sort, $perPage, $ownsTransaction): BrainListResult {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $sortDefinition = BrainCursorSort::resolve($sort);
                $query = Collection::query()
                    ->select([
                        'collections.*',
                        $sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                    ])
                    ->where('user_id', $user->getKey())
                    ->withCount('knowledgeItems');

                if ($kind !== null) {
                    $query->where('kind', $kind);
                }

                if ($search !== null) {
                    $pattern = BrainSearchPattern::prefix($search);
                    $query->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$pattern]);
                }

                $paginator = $query
                    ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                    ->orderBy('public_id', $sortDefinition['direction'])
                    ->cursorPaginate($perPage)
                    ->withQueryString();
                $total = DB::table('collections')->where('user_id', $user->getKey())->count();

                return new BrainListResult($paginator, ['total' => $total]);
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'collection.list');
        }
    }
}
