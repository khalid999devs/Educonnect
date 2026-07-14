<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Courses;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Courses\Concerns\HandlesAcademicInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourseRequest extends FormRequest
{
    use HandlesAcademicInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160', $this->plainSingleLineText()],
            'code' => ['nullable', 'string', 'max:32', $this->plainSingleLineText()],
            'description' => ['nullable', 'string', 'max:2000', $this->plainMultilineText()],
            'term_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, $this->allowedFields())];
    }

    /** @return array{title: string, code: ?string, description: ?string, term_id: ?string} */
    public function courseData(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'code' => $this->validated('code'),
            'description' => $this->validated('description'),
            'term_id' => $this->validated('term_id'),
        ];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['title', 'code', 'description', 'term_id'];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'code' => $this->nullableTrimmed($this->input('code')),
            'description' => $this->nullableTrimmed($this->input('description')),
            'term_id' => $this->nullableTrimmed($this->input('term_id')),
        ]);
    }
}
