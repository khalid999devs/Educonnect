<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Community\Models\ContentReport;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentReport */
final class ContentReportResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'community' => $this->whenLoaded('community', fn (): array => [
                'id' => (string) $this->community->public_id,
                'name' => $this->community->name,
            ]),
            'subject' => $this->subjectPayload(),
            'reason' => $this->reason->value,
            'detail' => $this->detail,
            'status' => $this->status->value,
            'resolution_note' => $this->resolution_note,
            'version' => $this->version,
            'handled_at' => $this->timestamp($this->getAttribute('handled_at')),
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }

    /** @return array<string, string|null> */
    private function subjectPayload(): array
    {
        if ($this->post_id !== null) {
            $post = $this->relationLoaded('post') ? $this->post : null;

            return [
                'type' => 'post',
                'id' => $post !== null ? (string) $post->public_id : null,
                'excerpt' => $post !== null ? $this->excerpt((string) $post->body) : null,
            ];
        }

        $comment = $this->relationLoaded('comment') ? $this->comment : null;

        return [
            'type' => 'comment',
            'id' => $comment !== null ? (string) $comment->public_id : null,
            'excerpt' => $comment !== null ? $this->excerpt((string) $comment->body) : null,
        ];
    }

    private function excerpt(string $body): string
    {
        return mb_strlen($body) > 140 ? mb_substr($body, 0, 137).'...' : $body;
    }
}
