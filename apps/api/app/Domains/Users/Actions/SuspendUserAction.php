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
use Illuminate\Support\Str;

final readonly class SuspendUserAction
{
    use GuardsUserAdministration;

    public function __construct(private AuditRecorder $auditRecorder) {}

    public function execute(User $actor, User $target, string $reason, string $requestId): User
    {
        if (! $actor->hasCapability(CapabilityKey::UsersSuspend)) {
            throw new AuthorizationException('Account suspension is not allowed.');
        }

        return DB::transaction(function () use ($actor, $target, $reason, $requestId): User {
            $locked = User::query()->lockForUpdate()->findOrFail($target->getKey());

            $this->assertActorMayAdminister($actor, $locked);

            if ($locked->isSuspended()) {
                throw new UserAccountConflict('This account is already suspended.');
            }

            $locked->forceFill([
                'status' => AccountStatus::Suspended->value,
                'suspended_at' => now(),
                'remember_token' => Str::random(60),
            ])->saveOrFail();

            // Suspension takes effect immediately: end every active session and token
            // so the account cannot continue an in-flight session and cannot sign in.
            DB::table('sessions')->where('user_id', $locked->getKey())->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $locked->getMorphClass())
                ->where('tokenable_id', $locked->getKey())
                ->delete();

            $this->auditRecorder->record(
                actor: $actor,
                action: AuditAction::UserSuspended,
                subjectType: 'user',
                subjectId: (string) $locked->public_id,
                reason: $reason,
                requestId: $requestId,
                beforeState: ['account_status' => [AccountStatus::Active->value]],
                afterState: ['account_status' => [AccountStatus::Suspended->value]],
            );

            return $locked->fresh(['roles']) ?? $locked;
        }, 3);
    }
}
