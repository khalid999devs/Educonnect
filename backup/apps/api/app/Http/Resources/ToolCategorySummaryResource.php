<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Tools\Models\ToolCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The student-facing shape of a curated tool category.
 *
 * This is deliberately separate from ToolCategoryResource, which serves the
 * admin curation surface and is pinned by the `Category` OpenAPI schema
 * (`additionalProperties: false`, `slug` rather than `key`). Students address a
 * category by `key`, matching the ToolCategoryReference already embedded in
 * every tool, prompt, workflow, and template payload.
 *
 * @mixin ToolCategory
 */
final class ToolCategorySummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $description = $this->getAttribute('description');

        return [
            'id' => (string) $this->public_id,
            'key' => (string) $this->slug,
            'name' => (string) $this->name,
            'description' => is_string($description) && $description !== '' ? $description : null,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
