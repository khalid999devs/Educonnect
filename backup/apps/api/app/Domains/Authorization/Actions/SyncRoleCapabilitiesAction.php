<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Models\Capability;
use App\Domains\Authorization\Models\Role;
use App\Domains\Users\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class SyncRoleCapabilitiesAction
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /**
     * @param  list<CapabilityKey>  $capabilities
     */
    public function execute(
        Role $role,
        array $capabilities,
        User $actor,
        string $reason,
        string $requestId,
    ): Role {
        if (! $actor->hasCapability(CapabilityKey::CapabilitiesManage)) {
            throw new AuthorizationException('Capability management is not allowed.');
        }

        $capabilityKeys = $this->normalizeCapabilities($capabilities);

        return DB::transaction(function () use ($role, $capabilityKeys, $actor, $reason, $requestId): Role {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->getKey());

            if ($lockedRole->is_protected && ! $actor->hasCapability(CapabilityKey::ProtectedRolesManage)) {
                throw new AuthorizationException('Protected role management is not allowed.');
            }

            $availableCapabilities = Capability::query()
                ->whereIn('key', array_map(
                    static fn (CapabilityKey $capability): string => $capability->value,
                    $capabilityKeys,
                ))
                ->lockForUpdate()
                ->get()
                ->keyBy('key');

            if ($availableCapabilities->count() !== count($capabilityKeys)) {
                throw new DomainException('One or more canonical capabilities are unavailable.');
            }

            $beforeKeys = $lockedRole->capabilities()->orderBy('capabilities.key')->pluck('capabilities.key')->all();
            $afterKeys = array_map(
                static fn (CapabilityKey $capability): string => $capability->value,
                $capabilityKeys,
            );
            sort($afterKeys);

            if ($beforeKeys === $afterKeys) {
                return $lockedRole->fresh(['capabilities']) ?? $lockedRole;
            }

            $lockedRole->capabilities()->sync($availableCapabilities->pluck('id')->all());
            $this->revokeAffectedSessions($lockedRole);
            $this->auditRecorder->record(
                actor: $actor,
                action: AuditAction::RoleCapabilitiesChanged,
                subjectType: 'role',
                subjectId: (string) $lockedRole->key,
                reason: $reason,
                requestId: $requestId,
                beforeState: ['capability_keys' => $beforeKeys],
                afterState: ['capability_keys' => $afterKeys],
            );

            return $lockedRole->fresh(['capabilities']) ?? $lockedRole;
        }, 3);
    }

    /**
     * @param  list<CapabilityKey>  $capabilities
     * @return list<CapabilityKey>
     */
    private function normalizeCapabilities(array $capabilities): array
    {
        $unique = [];

        foreach ($capabilities as $capability) {
            $unique[$capability->value] = $capability;
        }

        return array_values($unique);
    }

    private function revokeAffectedSessions(Role $role): void
    {
        $userIds = $role->users()->pluck('users.id')->all();

        if ($userIds === []) {
            return;
        }

        User::query()->whereKey($userIds)->update(['remember_token' => null]);
        DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $userIds)
            ->delete();
    }
}
