<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Users\Models\User;
use App\Http\Resources\Concerns\FormatsApiTimestamps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class AdminUserResource extends JsonResource
{
    use FormatsApiTimestamps;

    /**
     * Account-level fields only. The admin directory never exposes a user's
     * private academic documents, prompts, or messages (doc 08).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'status' => $this->status->value,
            'suspended_at' => $this->timestamp($this->getAttribute('suspended_at')),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles
                ->pluck('key')
                ->map(static fn ($key): string => (string) $key)
                ->sort()
                ->values()
                ->all()),
            'last_login_at' => $this->timestamp($this->getAttribute('last_login_at')),
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
        ];
    }
}
