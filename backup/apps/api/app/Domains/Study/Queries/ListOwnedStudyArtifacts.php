<?php

declare(strict_types=1);

namespace App\Domains\Study\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Study\Data\StudyListResult;
use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Study\Exceptions\StudyPersistenceFailure;
use App\Domains\Study\Models\StudyArtifact;
use App\Domains\Study\Support\StudyCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists the caller's own study artifacts, optionally scoped to one knowledge
 * item and filtered by kind or status. The item filter resolves the public id
 * inside the owner scope, so a foreign id is a 404 and never a cross-tenant
 * read.
 */
final class ListOwnedStudyArtifacts
{
    public function execute(
        User $user,
        ?string $itemPublicId,
        ?StudyArtifactKind $kind,
        ?StudyArtifactStatus $status,
        string $sort,
        int $perPage,
    ): StudyListResult {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use (
                $user,
                $itemPublicId,
                $kind,
                $status,
                $sort,
                $perPage,
                $ownsTransaction,
            ): StudyListResult {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $definition = StudyCursorSort::resolve($sort);
                $query = StudyArtifact::query()
                    ->select([
                        'study_artifacts.*',
                        $definition['column'].' as '.$definition['cursor_column'],
                    ])
                    ->where('user_id', $user->getKey());

                if ($itemPublicId !== null) {
                    $query->where('knowledge_item_id', $this->ownedItemId($user, $itemPublicId));
                }

                if ($kind instanceof StudyArtifactKind) {
                    $query->where('kind', $kind->value);
                }

                if ($status instanceof StudyArtifactStatus) {
                    $query->where('status', $status->value);
                }

                $paginator = $query
                    ->orderBy($definition['cursor_column'], $definition['direction'])
                    ->orderBy('public_id', $definition['direction'])
                    ->cursorPaginate($perPage)
                    ->withQueryString();

                return new StudyListResult($paginator, $this->summary($user));
            }, 3);
        } catch (QueryException $exception) {
            throw StudyPersistenceFailure::fromQueryException($exception, 'study.artifact.list');
        }
    }

    private function ownedItemId(User $user, string $itemPublicId): int
    {
        $id = KnowledgeItem::query()
            ->where('user_id', $user->getKey())
            ->where('public_id', $itemPublicId)
            ->value('id');

        if (! is_numeric($id)) {
            throw new NotFoundHttpException;
        }

        return (int) $id;
    }

    /** @return array<string, int> */
    private function summary(User $user): array
    {
        /** @var array<string, int> $counts */
        $counts = DB::table('study_artifacts')
            ->where('user_id', $user->getKey())
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $summary = ['total' => 0];

        foreach (StudyArtifactStatus::values() as $status) {
            $summary[$status] = $counts[$status] ?? 0;
            $summary['total'] += $summary[$status];
        }

        return $summary;
    }
}
