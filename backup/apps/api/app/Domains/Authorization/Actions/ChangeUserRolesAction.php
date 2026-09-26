<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Authorization\Models\Role;
use App\Domains\Users\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ChangeUserRolesAction
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /**
     * @param  list<RoleKey>  $roles
     */
    public function execute(
        User $target,
        array $roles,
        User $actor,
        string $reason,
        string $requestId,
    ): User {
        if (! $actor->hasCapability(CapabilityKey::RolesAssign)) {
            throw new AuthorizationException('Role assignment is not allowed.');
        }

        $roleKeys = $this->normalizeRoles($roles);

        return DB::transaction(function () use ($target, $roleKeys, $actor, $reason, $requestId): User {
            $lockedTarget = User::query()->lockForUpdate()->findOrFail($target->getKey());
            $availableRoles = Role::query()
                ->whereIn('key', array_map(static fn (RoleKey $role): string => $role->value, $roleKeys))
                ->lockForUpdate()
                ->get()
                ->keyBy('key');

            if ($availableRoles->count() !== count($roleKeys)) {
                throw new DomainException('One or more canonical roles are unavailable.');
            }

            $beforeKeys = $lockedTarget->roles()
                ->orderBy('roles.key')
                ->pluck('roles.key')
                ->all();
            $afterKeys = array_map(static fn (RoleKey $role): string => $role->value, $roleKeys);
            sort($afterKeys);

            $protectedChange = $availableRoles->contains(fn (Role $role): bool => $role->is_protected)
                || Role::query()->whereIn('key', $beforeKeys)->where('is_protected', true)->exists();

            if ($protectedChange && ! $actor->hasCapability(CapabilityKey::ProtectedRolesManage)) {
                throw new AuthorizationException('Protected role assignment is not allowed.');
            }

            if ($beforeKeys === $afterKeys) {
                return $lockedTarget->fresh(['roles']) ?? $lockedTarget;
            }

            $this->assertLastSuperAdminIsRetained($lockedTarget, $beforeKeys, $afterKeys);

            $currentRoleIds = $lockedTarget->roles()->pluck('roles.id')->map(static fn ($id): int => (int) $id)->all();
            $nextRoleIds = $availableRoles->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $remove = array_values(array_diff($currentRoleIds, $nextRoleIds));
            $add = array_values(array_diff($nextRoleIds, $currentRoleIds));

            if ($remove !== []) {
                $lockedTarget->roles()->detach($remove);
            }

            if ($add !== []) {
                $lockedTarget->roles()->attach(array_fill_keys($add, ['assigned_at' => now()]));
            }

            $this->revokeSessions($lockedTarget);
            $this->auditRecorder->record(
                actor: $actor,
                action: AuditAction::UserRolesChanged,
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

    /**
     * @param  list<RoleKey>  $roles
     * @return list<RoleKey>
     */
    private function normalizeRoles(array $roles): array
    {
        if ($roles === []) {
            throw new InvalidArgumentException('At least one role is required.');
        }

        $unique = [];

        foreach ($roles as $role) {
            $unique[$role->value] = $role;
        }

        return array_values($unique);
    }

    /**
     * @param  list<string>  $beforeKeys
     * @param  list<string>  $afterKeys
     */
    private function assertLastSuperAdminIsRetained(User $target, array $beforeKeys, array $afterKeys): void
    {
        if (! in_array(RoleKey::SuperAdmin->value, $beforeKeys, true)
            || in_array(RoleKey::SuperAdmin->value, $afterKeys, true)) {
            return;
        }

        Role::query()
            ->where('key', RoleKey::SuperAdmin->value)
            ->lockForUpdate()
            ->firstOrFail();

        $otherSuperAdmins = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.key', RoleKey::SuperAdmin->value)
            ->where('role_user.user_id', '<>', $target->getKey())
            ->count();

        if ($otherSuperAdmins === 0) {
            throw new DomainException('The final super administrator role cannot be removed.');
        }
    }

    private function revokeSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveOrFail();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
    }
}
