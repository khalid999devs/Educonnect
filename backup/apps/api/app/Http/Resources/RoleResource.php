<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => (string) $this->key,
            'name' => (string) $this->name,
            'display_priority' => (int) $this->display_priority,
            'is_protected' => (bool) $this->is_protected,
            'capabilities' => $this->whenLoaded('capabilities', fn (): array => $this->capabilities
                ->pluck('key')
                ->map(static fn ($key): string => (string) $key)
                ->sort()
                ->values()
                ->all()),
        ];
    }
}
