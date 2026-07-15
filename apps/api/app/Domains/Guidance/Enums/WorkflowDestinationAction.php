<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Enums;

enum WorkflowDestinationAction: string
{
    case CreateTask = 'create_task';
    case SaveResource = 'save_resource';
    case UseTool = 'use_tool';
    case UsePrompt = 'use_prompt';
}
