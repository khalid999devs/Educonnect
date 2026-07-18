<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content\Concerns;

use App\Domains\Guidance\Enums\WorkflowDestinationAction;
use Illuminate\Validation\Rule;

trait HandlesWorkflowContent
{
    /**
     * Content-field rules for a workflow recipe, bounded to match the database
     * CHECK constraints. Steps are ordered by their position in the array; a
     * published recipe needs at least one, which the database enforces on the
     * publish transition. Shared by create and update.
     *
     * @return array<string, mixed>
     */
    protected function workflowContentRules(): array
    {
        return [
            'category_slug' => ['required', 'string', Rule::exists('tool_categories', 'slug')],
            'title' => ['required', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'goal' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'expected_outcome' => ['required', 'string', 'min:1', 'max:4000', $this->plainMultilineText()],
            'integrity_note' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'provenance' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*.title' => ['required', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'steps.*.instruction' => ['required', 'string', 'min:1', 'max:4000', $this->plainMultilineText()],
            'steps.*.destination_action' => ['nullable', 'string', Rule::enum(WorkflowDestinationAction::class)],
        ];
    }
}
