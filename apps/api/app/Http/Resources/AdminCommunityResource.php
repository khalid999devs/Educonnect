<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Community\Models\Community;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Community */
final class AdminCommunityResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'slug' => (string) $this->slug,
            'name' => (string) $this->name,
            'summary' => (string) $this->summary,
            'description' => $this->description,
            'topic' => $this->topic,
            'visibility' => (string) $this->visibility,
            'is_seeded' => (bool) $this->is_seeded,
            'member_count' => (int) ($this->memberships_count ?? 0),
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }
}
