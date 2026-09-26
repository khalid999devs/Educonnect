<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Planner\Concerns\HandlesPlannerInput;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFocusSessionRequest extends FormRequest
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
            'task_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'starts_at' => ['required', 'string', 'max:25', $this->offsetDateTime()],
            'ends_at' => ['required', 'string', 'max:25', $this->offsetDateTime()],
            'note' => ['nullable', 'string', 'max:2000', $this->plainMultilineText()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, $this->allowedFields());

            if ($this->input('task_id') !== null && $this->input('course_id') !== null) {
                $validator->errors()->add('course_id', 'A focus session can target a task or a course, not both.');
            }

            $startsAt = $this->parsedDateTime('starts_at');
            $endsAt = $this->parsedDateTime('ends_at');

            if ($startsAt !== null && $endsAt !== null) {
                if ($endsAt <= $startsAt) {
                    $validator->errors()->add('ends_at', 'The ends at timestamp must be after starts at.');
                } elseif ($endsAt->getTimestamp() - $startsAt->getTimestamp() > 86400) {
                    $validator->errors()->add('ends_at', 'A focus session cannot exceed 24 hours.');
                }
            }
        }];
    }

    /** @return array{task_id: ?string, course_id: ?string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, note: ?string} */
    public function focusData(): array
    {
        $taskId = $this->validated('task_id');
        $courseId = $this->validated('course_id');
        $startsAt = $this->parsedDateTime('starts_at');
        $endsAt = $this->parsedDateTime('ends_at');

        if (! $startsAt instanceof CarbonImmutable || ! $endsAt instanceof CarbonImmutable) {
            throw new \LogicException('Validated focus timestamps must be available.');
        }

        return [
            'task_id' => is_string($taskId) ? $taskId : null,
            'course_id' => is_string($courseId) ? $courseId : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'note' => $this->validated('note'),
        ];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['task_id', 'course_id', 'starts_at', 'ends_at', 'note'];
    }

    protected function prepareForValidation(): void
    {
        foreach (['task_id', 'course_id', 'starts_at', 'ends_at', 'note'] as $key) {
            $this->merge([$key => $this->nullableTrimmed($this->input($key))]);
        }
    }
}
