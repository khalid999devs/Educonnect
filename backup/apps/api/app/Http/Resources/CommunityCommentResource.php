<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Models\CommunityComment;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunityComment */
final class CommunityCommentResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isVisible = $this->moderation_state === ModerationState::Visible;

        return [
            'id' => (string) $this->public_id,
            'author' => [
                'name' => $this->whenLoaded('author', fn (): string => (string) $this->author->name, 'Unknown'),
                'is_verified_mentor' => (bool) ($this->getAttribute('author_is_verified_mentor') ?? false),
            ],
            'body' => $isVisible ? $this->body : null,
            'moderation_state' => $this->moderation_state->value,
            'is_mine' => $request->user() !== null
                && (int) $request->user()->getKey() === (int) $this->author_id,
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }
}
