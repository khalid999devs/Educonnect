<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Enums;

enum RoleKey: string
{
    case Student = 'student';
    case Mentor = 'mentor';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
}
