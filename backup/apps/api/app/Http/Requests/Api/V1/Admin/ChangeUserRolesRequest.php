<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ChangeUserRolesRequest extends FormRequest
{
    use InteractsWithAdmin;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::enum(RoleKey::class)],
            'reason' => $this->reasonRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['roles', 'reason']);
        });
    }

    /** @return list<RoleKey> */
    public function roles(): array
    {
        $roles = $this->input('roles');

        if (! is_array($roles)) {
            return [];
        }

        $mapped = [];

        foreach ($roles as $role) {
            if (is_string($role) && ($key = RoleKey::tryFrom($role)) !== null) {
                $mapped[$key->value] = $key;
            }
        }

        return array_values($mapped);
    }
}
