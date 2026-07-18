<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content\Concerns;

use App\Domains\Templates\Enums\TemplateFormat;
use Illuminate\Validation\Rule;

trait HandlesTemplateContent
{
    /**
     * Content-field rules for a template plus its version body, bounded to match
     * the database CHECK constraints. The badge is always `approved_free` (the
     * only value), so it is set by the action rather than submitted.
     *
     * @param  bool  $bodyRequired  Create requires an initial version; update may
     *                              revise the metadata without a new version.
     * @return array<string, mixed>
     */
    protected function templateContentRules(bool $bodyRequired): array
    {
        $bodyRule = $bodyRequired ? 'required' : 'sometimes';

        return [
            'category_slug' => ['required', 'string', Rule::exists('tool_categories', 'slug')],
            'title' => ['required', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'summary' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'integrity_note' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'provenance' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'format' => [$bodyRule, 'string', Rule::enum(TemplateFormat::class)],
            'body' => [$bodyRule, 'string', 'min:1', 'max:20000', $this->plainMultilineText()],
            'change_note' => ['nullable', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
        ];
    }

    /** @return list<string> */
    protected function templateContentFields(): array
    {
        return ['category_slug', 'title', 'summary', 'integrity_note', 'provenance', 'format', 'body', 'change_note'];
    }
}
