<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Planner\Concerns\HandlesPlannerInput;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    use HandlesPlannerInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160', $this->plainSingleLineText()],
            'description' => ['nullable', 'string', 'max:2000', $this->plainMultilineText()],
            'course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'due_at' => ['nullable', 'string', 'max:25', $this->offsetDateTime()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, $this->allowedFields())];
    }

    /** @return array{title: string, description: ?string, course_id: ?string, due_at: ?CarbonImmutable} */
    public function taskData(): array
    {
        $courseId = $this->validated('course_id');

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'course_id' => is_string($courseId) ? $courseId : null,
            'due_at' => $this->parsedDateTime('due_at'),
        ];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['title', 'description', 'course_id', 'due_at'];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'description' => $this->nullableTrimmed($this->input('description')),
            'course_id' => $this->nullableTrimmed($this->input('course_id')),
            'due_at' => $this->nullableTrimmed($this->input('due_at')),
        ]);
    }
}
