<?php

declare(strict_types=1);

namespace App\Domains\Templates\Enums;

enum TemplateFormat: string
{
    case Markdown = 'markdown';
    case Plain = 'plain';
}
