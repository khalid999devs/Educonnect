<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Users\Enums\AccountStatus;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListUsersRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', Rule::enum(RoleKey::class)],
            'status' => ['sometimes', 'string', Rule::enum(AccountStatus::class)],
            'cursor' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['search', 'role', 'status', 'cursor', 'per_page']);
            $this->validateCreatedAtCursor($validator);
        });
    }

    public function search(): ?string
    {
        $value = $this->input('search');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function role(): ?string
    {
        $value = $this->input('role');

        return is_string($value) ? $value : null;
    }

    public function status(): ?string
    {
        $value = $this->input('status');

        return is_string($value) ? $value : null;
    }
}
