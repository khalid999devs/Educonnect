<?php

declare(strict_types=1);

namespace App\Domains\Intake\Enums;

enum IntakeSuggestionKind: string
{
    case Task = 'task';
    case Resource = 'resource';
    case KnowledgeItem = 'knowledge_item';
}
