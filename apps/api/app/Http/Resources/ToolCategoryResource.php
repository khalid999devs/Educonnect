<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Tools\Models\ToolCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToolCategory */
final class ToolCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => (string) $this->slug,
            'name' => (string) $this->name,
            'description' => (string) $this->description,
        ];
    }
}
