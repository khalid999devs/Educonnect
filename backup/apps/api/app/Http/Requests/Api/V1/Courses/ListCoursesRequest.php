<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Courses;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Courses\Concerns\HandlesAcademicInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListCoursesRequest extends FormRequest
{
    use HandlesAcademicInput;

    private const SORTS = ['created_at', '-created_at', 'updated_at', '-updated_at'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:1', 'max:100', $this->plainSingleLineText()],
            'status' => ['nullable', 'string', Rule::in(['active', 'archived', 'all'])],
            'term_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['search', 'status', 'term_id', 'sort', 'per_page', 'cursor']);
            $this->validateCursor($validator, $this->sort());
        }];
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) ? $value : null;
    }

    public function status(): string
    {
        $value = $this->input('status', 'active');

        return is_string($value) ? $value : 'active';
    }

    public function termId(): ?string
    {
        $value = $this->validated('term_id');

        return is_string($value) ? $value : null;
    }

    public function sort(): string
    {
        $value = $this->input('sort', '-updated_at');

        return is_string($value) ? $value : '-updated_at';
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    protected function prepareForValidation(): void
    {
        foreach (['search', 'status', 'term_id', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
