<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Planner\Concerns\HandlesPlannerInput;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListFocusSessionsRequest extends FormRequest
{
    use HandlesPlannerInput;

    private const SORTS = ['starts_at', '-starts_at'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'task_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'overlap_from' => ['nullable', 'string', 'max:25', $this->offsetDateTime()],
            'overlap_before' => ['nullable', 'string', 'max:25', $this->offsetDateTime()],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, [
                'task_id',
                'course_id',
                'overlap_from',
                'overlap_before',
                'sort',
                'per_page',
                'cursor',
            ]);
            $this->validateFocusCursor($validator, $this->sort());

            if ($this->input('task_id') !== null && $this->input('course_id') !== null) {
                $validator->errors()->add('course_id', 'Filter by a task or a course, not both.');
            }

            $from = $this->parsedDateTime('overlap_from');
            $before = $this->parsedDateTime('overlap_before');

            if ($from !== null && $before !== null && $before <= $from) {
                $validator->errors()->add('overlap_before', 'The overlap before timestamp must be after overlap from.');
            }
        }];
    }

    public function taskId(): ?string
    {
        $value = $this->validated('task_id');

        return is_string($value) ? $value : null;
    }

    public function courseId(): ?string
    {
        $value = $this->validated('course_id');

        return is_string($value) ? $value : null;
    }

    public function overlapFrom(): ?CarbonImmutable
    {
        return $this->parsedDateTime('overlap_from');
    }

    public function overlapBefore(): ?CarbonImmutable
    {
        return $this->parsedDateTime('overlap_before');
    }

    public function sort(): string
    {
        $value = $this->input('sort', 'starts_at');

        return is_string($value) ? $value : 'starts_at';
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    protected function prepareForValidation(): void
    {
        foreach (['task_id', 'course_id', 'overlap_from', 'overlap_before', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
