<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Mentor\Models\MentorProfile;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MentorProfile */
final class MentorProfileResource extends JsonResource
{
    use FormatsApiTimestamps;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'name' => $this->whenLoaded('user', fn (): string => (string) $this->user->name, 'Mentor'),
            'headline' => $this->headline,
            'bio' => $this->bio,
            'expertise' => $this->expertise,
            'availability_note' => $this->availability_note,
            'verification_state' => $this->verification_state->value,
            'is_accepting_requests' => (bool) $this->is_accepting_requests,
            'version' => $this->version,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }
}
