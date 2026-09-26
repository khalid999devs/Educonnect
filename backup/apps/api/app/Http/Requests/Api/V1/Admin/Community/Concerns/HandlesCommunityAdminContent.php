<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Community\Concerns;

trait HandlesCommunityAdminContent
{
    /**
     * Presentation-field rules for a community, bounded to the column widths.
     *
     * @return array<string, mixed>
     */
    protected function communityContentRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120', $this->plainSingleLineText()],
            'summary' => ['required', 'string', 'min:1', 'max:280', $this->plainMultilineText()],
            'description' => ['nullable', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'topic' => ['nullable', 'string', 'min:1', 'max:80', $this->plainSingleLineText()],
        ];
    }

    /** @return list<string> */
    protected function communityContentFields(): array
    {
        return ['name', 'summary', 'description', 'topic'];
    }
}
