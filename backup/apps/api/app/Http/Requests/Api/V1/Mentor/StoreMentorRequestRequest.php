<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Mentor;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Mentor\Concerns\HandlesMentorInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreMentorRequestRequest extends FormRequest
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
            'subject' => ['required', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'message' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'context_course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['subject', 'message', 'context_course_id'])];
    }

    public function subject(): string
    {
        return (string) $this->validated('subject');
    }

    public function message(): string
    {
        return (string) $this->validated('message');
    }

    public function contextCourseId(): ?string
    {
        $value = $this->validated('context_course_id');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => $this->nullableTrimmed($this->input('subject')),
            'context_course_id' => $this->nullableTrimmed($this->input('context_course_id')),
        ]);
    }
}
