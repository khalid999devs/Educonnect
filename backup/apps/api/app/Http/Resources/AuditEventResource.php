<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Audit\Models\AuditEvent;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditEvent */
final class AuditEventResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'action' => (string) $this->action,
            'actor' => [
                'type' => (string) $this->actor_type,
                'id' => $this->actor_public_id !== null ? (string) $this->actor_public_id : null,
                'name' => $this->whenLoaded('actor', fn (): ?string => $this->actor?->name),
            ],
            'subject' => [
                'type' => (string) $this->subject_type,
                'id' => (string) $this->subject_id,
            ],
            'reason' => (string) $this->reason,
            'before_state' => $this->before_state,
            'after_state' => $this->after_state,
            'request_id' => (string) $this->request_id,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }
}
