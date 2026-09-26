<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Mentor;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Mentor\Concerns\HandlesMentorInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class TransitionMentorRequestRequest extends FormRequest
{
    use HandlesMentorInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['withdraw', 'accept', 'decline', 'complete'])],
            'response_note' => ['nullable', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['action', 'response_note', 'expected_version'])];
    }

    public function action(): string
    {
        return (string) $this->validated('action');
    }

    public function responseNote(): ?string
    {
        $value = $this->validated('response_note');

        return is_string($value) ? $value : null;
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['response_note' => $this->nullableTrimmed($this->input('response_note'))]);
    }
}
