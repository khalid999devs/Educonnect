<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content\Concerns;

use Illuminate\Validation\Rule;

trait HandlesPromptContent
{
    /**
     * Content-field rules for a prompt template, bounded to match the database
     * CHECK constraints so an invalid submission fails validation rather than the
     * write. Shared by create and update.
     *
     * @return array<string, mixed>
     */
    protected function promptContentRules(): array
    {
        return [
            'category_slug' => ['required', 'string', Rule::exists('tool_categories', 'slug')],
            'title' => ['required', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'purpose' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'template_body' => ['required', 'string', 'min:1', 'max:8000', $this->plainMultilineText()],
            'placeholders' => ['required', 'array', 'min:1', 'max:20'],
            'placeholders.*' => ['required', 'string', 'min:1', 'max:100', $this->plainSingleLineText()],
            'expected_output' => ['required', 'string', 'min:1', 'max:4000', $this->plainMultilineText()],
            'integrity_note' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'provenance' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'related_tools' => ['sometimes', 'array', 'max:20'],
            'related_tools.*' => ['required', 'string', Rule::exists('tools', 'public_id')],
        ];
    }
}
