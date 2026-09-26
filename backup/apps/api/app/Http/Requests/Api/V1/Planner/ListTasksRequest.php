<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Planner\Concerns\HandlesPlannerInput;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListTasksRequest extends FormRequest
{
    use HandlesPlannerInput;

    private const SORTS = ['updated_at', '-updated_at'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:1', 'max:100', $this->plainSingleLineText()],
            'status' => ['nullable', 'string', Rule::in([...array_column(TaskStatus::cases(), 'value'), 'all'])],
            'archive_status' => ['nullable', 'string', Rule::in(['active', 'archived', 'all'])],
            'course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'due_from' => ['nullable', 'string', 'max:25', $this->offsetDateTime()],
            'due_before' => ['nullable', 'string', 'max:25', $this->offsetDateTime()],
            'has_due' => ['nullable', 'boolean'],
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
                'search',
                'status',
                'archive_status',
                'course_id',
                'due_from',
                'due_before',
                'has_due',
                'sort',
                'per_page',
                'cursor',
            ]);
            $this->validateTaskCursor($validator, $this->sort());
            $from = $this->parsedDateTime('due_from');
            $before = $this->parsedDateTime('due_before');

            if ($from !== null && $before !== null && $before <= $from) {
                $validator->errors()->add('due_before', 'The due before timestamp must be after due from.');
            }

            if ($this->input('has_due') === false && ($from !== null || $before !== null)) {
                $validator->errors()->add('has_due', 'A due range cannot be combined with has_due=false.');
            }
        }];
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) ? $value : null;
    }

    public function status(): string
    {
        $value = $this->input('status', 'all');

        return is_string($value) ? $value : 'all';
    }

    public function archiveStatus(): string
    {
        $value = $this->input('archive_status', 'active');

        return is_string($value) ? $value : 'active';
    }

    public function courseId(): ?string
    {
        $value = $this->validated('course_id');

        return is_string($value) ? $value : null;
    }

    public function dueFrom(): ?CarbonImmutable
    {
        return $this->parsedDateTime('due_from');
    }

    public function dueBefore(): ?CarbonImmutable
    {
        return $this->parsedDateTime('due_before');
    }

    public function hasDue(): ?bool
    {
        $value = $this->validated('has_due');

        return is_bool($value) ? $value : null;
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
        foreach (['search', 'status', 'archive_status', 'course_id', 'due_from', 'due_before', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }

        if (is_string($this->input('has_due'))) {
            $value = filter_var($this->input('has_due'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if (is_bool($value)) {
                $this->merge(['has_due' => $value]);
            }
        }
    }
}
