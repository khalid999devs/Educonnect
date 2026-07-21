<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Community\Models\CommunityMembership;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person inside a community the viewer has joined.
 *
 * This payload is deliberately minimal: display name, membership role, join
 * time, and the verified-mentor badge. It must never carry an email address,
 * an internal numeric id, or any other account attribute.
 *
 * @mixin CommunityMembership
 */
final class CommunityMemberResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'name' => (string) ($this->getAttribute('member_name') ?? 'Unknown'),
            'role' => $this->role->value,
            'joined_at' => $this->timestamp($this->getAttribute('created_at')),
            'is_verified_mentor' => (bool) ($this->getAttribute('member_is_verified_mentor') ?? false),
        ];
    }
}
