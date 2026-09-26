<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Enums\CommunityVisibility;
use App\Domains\Community\Exceptions\CommunityStateConflict;
use App\Domains\Community\Exceptions\CommunityVersionConflict;
use App\Domains\Community\Models\Community;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class SetCommunityVisibilityAction
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /**
     * Publish or archive a community. Archiving hides it from members, so the
     * change is recorded in the audit log. Optimistic concurrency via
     * expected_version.
     */
    public function execute(
        User $actor,
        Community $community,
        CommunityVisibility $target,
        string $reason,
        string $requestId,
        int $expectedVersion,
    ): Community {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Community management is not allowed.');
        }

        return DB::transaction(function () use ($actor, $community, $target, $reason, $requestId, $expectedVersion): Community {
            $locked = Community::query()->lockForUpdate()->findOrFail($community->getKey());

            if ($locked->version !== $expectedVersion) {
                throw new CommunityVersionConflict;
            }

            $before = (string) $locked->visibility;

            if ($before === $target->value) {
                throw new CommunityStateConflict("This community is already {$target->value}.");
            }

            $locked->forceFill([
                'visibility' => $target->value,
                'version' => $locked->version + 1,
            ])->save();

            $this->auditRecorder->record(
                actor: $actor,
                action: AuditAction::CommunityVisibilityChanged,
                subjectType: 'community',
                subjectId: (string) $locked->public_id,
                reason: $reason,
                requestId: $requestId,
                beforeState: ['visibility' => [$before]],
                afterState: ['visibility' => [$target->value]],
            );

            return $locked;
        }, 3);
    }
}
