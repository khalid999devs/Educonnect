<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Community\Models\Community;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Community */
final class CommunityResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'topic' => $this->topic,
            'visibility' => $this->visibility,
            'is_seeded' => (bool) $this->is_seeded,
            'is_member' => (bool) ($this->getAttribute('viewer_is_member') ?? false),
            'membership_role' => $this->getAttribute('viewer_membership_role'),
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }
}
