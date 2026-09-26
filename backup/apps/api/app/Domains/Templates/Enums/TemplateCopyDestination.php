<?php

declare(strict_types=1);

namespace App\Domains\Templates\Enums;

enum TemplateCopyDestination: string
{
    case Dashboard = 'dashboard';
    case Course = 'course';
}
