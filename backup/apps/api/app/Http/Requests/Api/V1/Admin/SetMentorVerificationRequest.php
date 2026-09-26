<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Domains\Mentor\Enums\MentorVerificationState;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SetMentorVerificationRequest extends FormRequest
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
            'verification_state' => ['required', 'string', Rule::enum(MentorVerificationState::class)],
            'expected_version' => ['required', 'integer', 'min:0'],
            'reason' => $this->reasonRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['verification_state', 'expected_version', 'reason']);
        });
    }

    public function verificationState(): MentorVerificationState
    {
        return MentorVerificationState::from((string) $this->input('verification_state'));
    }

    public function expectedVersion(): int
    {
        return (int) $this->input('expected_version');
    }
}
