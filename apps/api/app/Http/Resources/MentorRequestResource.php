<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Mentor\Models\MentorRequest;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MentorRequest */
final class MentorRequestResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status->value,
            'response_note' => $this->response_note,
            'mentor' => $this->whenLoaded('mentorProfile', fn (): array => [
                'id' => (string) $this->mentorProfile->public_id,
                'name' => $this->mentorProfile->relationLoaded('user') && $this->mentorProfile->user !== null
                    ? (string) $this->mentorProfile->user->name
                    : 'Mentor',
                'headline' => (string) $this->mentorProfile->headline,
            ]),
            'requester' => $this->whenLoaded('requester', fn (): array => [
                'name' => (string) $this->requester->name,
            ]),
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'responded_at' => $this->timestamp($this->getAttribute('responded_at')),
        ];
    }
}
