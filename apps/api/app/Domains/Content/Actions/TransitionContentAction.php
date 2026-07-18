<?php

declare(strict_types=1);

namespace App\Domains\Content\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Content\Enums\ContentTransition;
use App\Domains\Content\Exceptions\ContentStateConflict;
use App\Domains\Content\Exceptions\ContentVersionConflict;
use App\Domains\Users\Models\User;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class TransitionContentAction
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /**
     * Move one platform-owned catalog record through the curation lifecycle. The
     * database transition trigger and the published-content-complete CHECK are
     * authoritative — an illegal transition or an incomplete record surfaces as a
     * conflict. Publishing/archiving stamps the review/publication/archive times;
     * returning to draft clears the publication timestamps so the record becomes
     * editable again.
     *
     * @template TModel of Model
     *
     * @param  TModel  $content
     * @return TModel
     */
    public function execute(
        User $actor,
        Model $content,
        string $subjectType,
        ContentTransition $transition,
        int $expectedVersion,
        string $reason,
        string $requestId,
    ): Model {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        try {
            return DB::transaction(function () use ($actor, $content, $subjectType, $transition, $expectedVersion, $reason, $requestId): Model {
                /** @var TModel $locked */
                $locked = $content->newQuery()->lockForUpdate()->findOrFail($content->getKey());

                if ((int) $locked->getAttribute('version') !== $expectedVersion) {
                    throw new ContentVersionConflict;
                }

                $rawBefore = $locked->getAttribute('state');
                $before = $rawBefore instanceof BackedEnum ? (string) $rawBefore->value : (string) $rawBefore;
                $target = $transition->targetState();
                $now = now();

                $attributes = [
                    'state' => $target,
                    'version' => (int) $locked->getAttribute('version') + 1,
                ];

                match ($transition) {
                    // One timestamp for both so last_reviewed_at <= published_at holds.
                    ContentTransition::Publish => $attributes += [
                        'published_at' => $now,
                        'last_reviewed_at' => $now,
                    ],
                    ContentTransition::Archive => $attributes += ['archived_at' => $now],
                    // A draft carries no review/publication metadata (lifecycle CHECK),
                    // so returning to draft clears all three timestamps.
                    ContentTransition::ReturnToDraft => $attributes += [
                        'last_reviewed_at' => null,
                        'published_at' => null,
                        'archived_at' => null,
                    ],
                    ContentTransition::SubmitForReview => null,
                };

                $locked->forceFill($attributes)->save();

                $this->auditRecorder->record(
                    actor: $actor,
                    action: AuditAction::ContentLifecycleChanged,
                    subjectType: $subjectType,
                    subjectId: (string) $locked->getAttribute('public_id'),
                    reason: $reason,
                    requestId: $requestId,
                    beforeState: ['content_state' => [$before]],
                    afterState: ['content_state' => [$target]],
                );

                return $locked;
            }, 3);
        } catch (QueryException $exception) {
            // The transition trigger (P0001) and the published-content-complete
            // CHECK (23514) both signal a caller-correctable lifecycle problem.
            $sqlState = (string) ($exception->errorInfo[0] ?? '');

            if (in_array($sqlState, ['23514', 'P0001'], true)) {
                throw new ContentStateConflict;
            }

            throw $exception;
        }
    }
}
