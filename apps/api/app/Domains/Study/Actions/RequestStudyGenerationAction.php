<?php

declare(strict_types=1);

namespace App\Domains\Study\Actions;

use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Study\Exceptions\StudyPersistenceFailure;
use App\Domains\Study\Jobs\GenerateStudyArtifact;
use App\Domains\Study\Models\StudyArtifact;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Requests one study generation for one knowledge item.
 *
 * The persisted artifact IS the cache and the dedupe key. `UNIQUE(knowledge_item_id, kind)`
 * means there is exactly one row per (material, action) and this Action decides
 * what a repeat request means:
 *
 * - queued or running -> hand back the same artifact; the student is already
 *   waiting on it and a second dispatch would only re-bill a provider.
 * - ready            -> hand back the same artifact; the answer already exists.
 * - failed           -> re-queue that same row, clearing the failure reason and
 *   bumping `version`, so a transient provider outage is one click to retry.
 *
 * The response is 202 in every case: the caller polls the artifact either way,
 * and a `ready` artifact simply completes on the first poll.
 */
final readonly class RequestStudyGenerationAction
{
    public function __construct(private FindOwnedKnowledgeItem $items) {}

    public function execute(User $user, string $itemPublicId, StudyArtifactKind $kind): StudyArtifact
    {
        Gate::forUser($user)->authorize('create', StudyArtifact::class);

        try {
            return DB::transaction(function () use ($user, $itemPublicId, $kind): StudyArtifact {
                $item = $this->items->execute($user, $itemPublicId);

                $artifact = StudyArtifact::query()
                    ->where('user_id', $user->getKey())
                    ->where('knowledge_item_id', $item->getKey())
                    ->where('kind', $kind->value)
                    ->lockForUpdate()
                    ->first();

                if ($artifact instanceof StudyArtifact && $artifact->status !== StudyArtifactStatus::Failed) {
                    return $artifact;
                }

                if ($artifact instanceof StudyArtifact) {
                    $artifact->forceFill([
                        'status' => StudyArtifactStatus::Queued->value,
                        'payload' => null,
                        'failure_reason' => null,
                        'provider' => null,
                        'model' => null,
                        'latency_ms' => null,
                        'version' => (int) $artifact->version + 1,
                    ])->save();
                } else {
                    $artifact = new StudyArtifact;
                    $artifact->forceFill([
                        'user_id' => $user->getKey(),
                        'knowledge_item_id' => $item->getKey(),
                        'kind' => $kind->value,
                        'status' => StudyArtifactStatus::Queued->value,
                        'schema_version' => 'v1',
                        'version' => 1,
                    ])->save();
                }

                GenerateStudyArtifact::dispatch(
                    (int) $artifact->getKey(),
                    (string) $item->public_id,
                    $kind->value,
                )->afterCommit();

                return $artifact->refresh();
            }, 3);
        } catch (QueryException $exception) {
            throw StudyPersistenceFailure::fromQueryException($exception, 'study.generation.request');
        }
    }
}
