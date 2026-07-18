<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Models\CommunityPost;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunityPost */
final class CommunityPostResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isVisible = $this->moderation_state === ModerationState::Visible;

        return [
            'id' => (string) $this->public_id,
            'community' => $this->whenLoaded('community', fn (): array => [
                'id' => (string) $this->community->public_id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
            ]),
            'author' => [
                'name' => $this->whenLoaded('author', fn (): string => (string) $this->author->name, 'Unknown'),
                'is_verified_mentor' => (bool) ($this->getAttribute('author_is_verified_mentor') ?? false),
            ],
            'title' => $isVisible ? $this->title : null,
            'body' => $isVisible ? $this->body : null,
            'moderation_state' => $this->moderation_state->value,
            'is_mine' => $request->user() !== null
                && (int) $request->user()->getKey() === (int) $this->author_id,
            'shared_resource' => $this->sharedResourcePayload($isVisible),
            'comment_count' => (int) ($this->getAttribute('comment_count') ?? 0),
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
        ];
    }

    /** @return array<string, string>|null */
    private function sharedResourcePayload(bool $isVisible): ?array
    {
        if (! $isVisible || ! $this->relationLoaded('sharedResource') || $this->sharedResource === null) {
            return null;
        }

        return [
            'title' => (string) $this->sharedResource->title,
            'url' => (string) $this->sharedResource->source_url,
        ];
    }
}
