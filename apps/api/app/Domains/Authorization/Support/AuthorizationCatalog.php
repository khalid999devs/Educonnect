<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Support;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Enums\RoleKey;

final class AuthorizationCatalog
{
    /**
     * @return array<string, array{name: string, display_priority: int, is_protected: bool}>
     */
    public static function roles(): array
    {
        return [
            RoleKey::Student->value => ['name' => 'Student', 'display_priority' => 10, 'is_protected' => false],
            RoleKey::Mentor->value => ['name' => 'Mentor', 'display_priority' => 20, 'is_protected' => false],
            RoleKey::Moderator->value => ['name' => 'Moderator', 'display_priority' => 30, 'is_protected' => false],
            RoleKey::Admin->value => ['name' => 'Admin', 'display_priority' => 40, 'is_protected' => true],
            RoleKey::SuperAdmin->value => ['name' => 'Super admin', 'display_priority' => 50, 'is_protected' => true],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function capabilities(): array
    {
        return [
            CapabilityKey::AcademicManageOwn->value => 'Manage owned academic data',
            CapabilityKey::AdminAccess->value => 'Access the administrative application surface',
            CapabilityKey::ModerationScoped->value => 'Moderate explicitly assigned scopes',
            CapabilityKey::ModerationGlobal->value => 'Moderate across all scopes',
            CapabilityKey::PrivateSupportAccess->value => 'Access private user data through an audited support workflow',
            CapabilityKey::UsersSuspend->value => 'Suspend and reactivate user accounts',
            CapabilityKey::ContentCurate->value => 'Curate platform-owned content',
            CapabilityKey::MentorsCurate->value => 'Curate mentor profiles',
            CapabilityKey::RolesView->value => 'View role and capability assignments',
            CapabilityKey::RolesAssign->value => 'Assign non-protected roles',
            CapabilityKey::ProtectedRolesManage->value => 'Assign or remove protected roles',
            CapabilityKey::CapabilitiesManage->value => 'Change role capability mappings',
            CapabilityKey::AuditViewScoped->value => 'View audit events within an assigned scope',
            CapabilityKey::AuditViewAll->value => 'View all authorization audit events',
        ];
    }

    /**
     * @return array<string, list<CapabilityKey>>
     */
    public static function roleCapabilities(): array
    {
        return [
            RoleKey::Student->value => [
                CapabilityKey::AcademicManageOwn,
            ],
            RoleKey::Mentor->value => [
                CapabilityKey::AcademicManageOwn,
            ],
            RoleKey::Moderator->value => [
                CapabilityKey::AdminAccess,
                CapabilityKey::ModerationScoped,
                CapabilityKey::AuditViewScoped,
            ],
            RoleKey::Admin->value => [
                CapabilityKey::AdminAccess,
                CapabilityKey::ModerationGlobal,
                CapabilityKey::UsersSuspend,
                CapabilityKey::ContentCurate,
                CapabilityKey::MentorsCurate,
                CapabilityKey::RolesView,
                CapabilityKey::RolesAssign,
                CapabilityKey::AuditViewAll,
            ],
            RoleKey::SuperAdmin->value => [
                CapabilityKey::AdminAccess,
                CapabilityKey::ModerationScoped,
                CapabilityKey::ModerationGlobal,
                CapabilityKey::UsersSuspend,
                CapabilityKey::ContentCurate,
                CapabilityKey::MentorsCurate,
                CapabilityKey::RolesView,
                CapabilityKey::RolesAssign,
                CapabilityKey::ProtectedRolesManage,
                CapabilityKey::CapabilitiesManage,
                CapabilityKey::AuditViewScoped,
                CapabilityKey::AuditViewAll,
            ],
        ];
    }
}
