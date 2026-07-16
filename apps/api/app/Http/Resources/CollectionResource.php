<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\SecondBrain\Models\Collection;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Collection */
final class CollectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $itemCount = $this->getAttribute('knowledge_items_count');

        return [
            'id' => (string) $this->public_id,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind,
            'item_count' => is_numeric($itemCount) ? (int) $itemCount : 0,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
