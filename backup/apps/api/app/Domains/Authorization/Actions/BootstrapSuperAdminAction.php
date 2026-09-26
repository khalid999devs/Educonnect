<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Authorization\Models\Role;
use App\Domains\Users\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class BootstrapSuperAdminAction
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function execute(User $target, string $reason, string $requestId): User
    {
        return DB::transaction(function () use ($target, $reason, $requestId): User {
            $superAdminRole = Role::query()
                ->where('key', RoleKey::SuperAdmin->value)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedTarget = User::query()->lockForUpdate()->findOrFail($target->getKey());

            if (! $lockedTarget->hasVerifiedEmail()) {
                throw new DomainException('The initial super administrator must have a verified email address.');
            }

            if (DB::table('role_user')->where('role_id', $superAdminRole->getKey())->exists()) {
                throw new DomainException('A super administrator already exists; the bootstrap command is one-use only.');
            }

            $beforeKeys = $lockedTarget->roles()->orderBy('roles.key')->pluck('roles.key')->all();
            $lockedTarget->roles()->syncWithoutDetaching([
                $superAdminRole->getKey() => ['assigned_at' => now()],
            ]);
            $afterKeys = [...$beforeKeys, RoleKey::SuperAdmin->value];
            $afterKeys = array_values(array_unique($afterKeys));
            sort($afterKeys);

            $lockedTarget->forceFill(['remember_token' => Str::random(60)])->saveOrFail();
            DB::table('sessions')->where('user_id', $lockedTarget->getKey())->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $lockedTarget->getMorphClass())
                ->where('tokenable_id', $lockedTarget->getKey())
                ->delete();

            $this->auditRecorder->record(
                actor: null,
                action: AuditAction::SuperAdminBootstrapped,
                subjectType: 'user',
                subjectId: (string) $lockedTarget->public_id,
                reason: $reason,
                requestId: $requestId,
                beforeState: ['role_keys' => $beforeKeys],
                afterState: ['role_keys' => $afterKeys],
            );

            return $lockedTarget->fresh(['roles']) ?? $lockedTarget;
        }, 3);
    }
}
