<?php

declare(strict_types=1);

namespace App\Domains\Audit\Enums;

enum AuditAction: string
{
    case UserRolesChanged = 'authorization.user-roles-changed';
    case RoleCapabilitiesChanged = 'authorization.role-capabilities-changed';
    case SuperAdminBootstrapped = 'authorization.super-admin-bootstrapped';
    case UserSuspended = 'users.account-suspended';
    case UserReactivated = 'users.account-reactivated';
    case MentorVerificationChanged = 'mentors.verification-changed';
    case ReportResolved = 'community.report-resolved';
}
