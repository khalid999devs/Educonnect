<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Enums;

enum CapabilityKey: string
{
    case AcademicManageOwn = 'academic.manage-own';
    case AdminAccess = 'admin.access';
    case ModerationScoped = 'moderation.scoped';
    case ModerationGlobal = 'moderation.global';
    case PrivateSupportAccess = 'users.private-support-access';
    case UsersSuspend = 'users.suspend';
    case ContentCurate = 'content.curate';
    case MentorsCurate = 'mentors.curate';
    case RolesView = 'authorization.roles-view';
    case RolesAssign = 'authorization.roles-assign';
    case ProtectedRolesManage = 'authorization.protected-roles-manage';
    case CapabilitiesManage = 'authorization.capabilities-manage';
    case AuditViewScoped = 'audit.view-scoped';
    case AuditViewAll = 'audit.view-all';
}
