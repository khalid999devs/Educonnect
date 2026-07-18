<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Actions\Concerns\GuardsUserAdministration;
use App\Domains\Users\Enums\AccountStatus;
use App\Domains\Users\Exceptions\UserAccountConflict;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ReactivateUserAction
{
    use GuardsUserAdministration;

    public function __construct(private AuditRecorder $auditRecorder) {}

    public function execute(User $actor, User $target, string $reason, string $requestId): User
    {
        if (! $actor->hasCapability(CapabilityKey::UsersSuspend)) {
            throw new AuthorizationException('Account reactivation is not allowed.');
        }

        return DB::transaction(function () use ($actor, $target, $reason, $requestId): User {
            $locked = User::query()->lockForUpdate()->findOrFail($target->getKey());

            $this->assertActorMayAdminister($actor, $locked);

            if (! $locked->isSuspended()) {
                throw new UserAccountConflict('This account is already active.');
            }

            $locked->forceFill([
                'status' => AccountStatus::Active->value,
                'suspended_at' => null,
            ])->saveOrFail();

            $this->auditRecorder->record(
                actor: $actor,
                action: AuditAction::UserReactivated,
                subjectType: 'user',
                subjectId: (string) $locked->public_id,
                reason: $reason,
                requestId: $requestId,
                beforeState: ['account_status' => [AccountStatus::Suspended->value]],
                afterState: ['account_status' => [AccountStatus::Active->value]],
            );

            return $locked->fresh(['roles']) ?? $locked;
        }, 3);
    }
}
