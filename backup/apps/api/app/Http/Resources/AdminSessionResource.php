<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class AdminSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authorization = $this->authorization();

        return [
            'user' => UserResource::make($this->resource),
            'authorization' => $authorization,
        ];
    }

    /**
     * @return array{roles: list<string>, capabilities: list<string>}
     */
    private function authorization(): array
    {
        $roles = [];
        $capabilities = [];

        foreach ($this->resource->roles()->with('capabilities')->get() as $role) {
            $roles[] = (string) $role->getAttribute('key');

            foreach ($role->capabilities as $capability) {
                $capabilities[] = (string) $capability->getAttribute('key');
            }
        }

        $roles = array_values(array_unique($roles));
        $capabilities = array_values(array_unique($capabilities));
        sort($roles, SORT_STRING);
        sort($capabilities, SORT_STRING);

        return [
            'roles' => $roles,
            'capabilities' => $capabilities,
        ];
    }
}
