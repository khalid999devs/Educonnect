<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Domains\Mentor\Enums\MentorVerificationState;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListAdminMentorsRequest extends FormRequest
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
            'verification_state' => ['sometimes', 'string', Rule::enum(MentorVerificationState::class)],
            'cursor' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['search', 'verification_state', 'cursor', 'per_page']);
            $this->validateCreatedAtCursor($validator);
        });
    }

    public function search(): ?string
    {
        $value = $this->input('search');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function verificationState(): ?string
    {
        $value = $this->input('verification_state');

        return is_string($value) ? $value : null;
    }
}
