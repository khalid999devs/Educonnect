<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content\Concerns;

use Illuminate\Validation\Rule;

trait HandlesToolContent
{
    /**
     * Content-field rules for a tool, bounded to match the database CHECK
     * constraints so an invalid submission fails validation rather than the
     * write. Shared by create and update.
     *
     * @return array<string, mixed>
     */
    protected function toolContentRules(): array
    {
        return [
            'category_slug' => ['required', 'string', Rule::exists('tool_categories', 'slug')],
            'name' => ['required', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'purpose' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'selection_reason' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'use_cases' => ['required', 'array', 'min:1', 'max:20'],
            'use_cases.*' => ['required', 'string', 'min:1', 'max:120', $this->plainSingleLineText()],
            'usage_guidance' => ['required', 'string', 'min:1', 'max:4000', $this->plainMultilineText()],
            'limitations' => ['required', 'string', 'min:1', 'max:4000', $this->plainMultilineText()],
            'cost_note' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'privacy_note' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'url' => ['required', 'string', 'max:2048', 'url', 'starts_with:https://'],
            'provenance' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function toolContentFields(): array
    {
        return ['content' => ['category_slug', 'name', 'purpose', 'selection_reason', 'use_cases', 'usage_guidance', 'limitations', 'cost_note', 'privacy_note', 'url', 'provenance']];
    }
}
