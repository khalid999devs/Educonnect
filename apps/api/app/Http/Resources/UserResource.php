<?php

namespace App\Http\Resources;

use App\Domains\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'primary_role' => null,
        ];
    }
}
