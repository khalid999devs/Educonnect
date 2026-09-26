<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Planner\Concerns\HandlesPlannerInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateTaskStatusRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', Rule::enum(TaskStatus::class)],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['expected_version', 'status'])];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    public function status(): TaskStatus
    {
        return TaskStatus::from((string) $this->validated('status'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('status'))) {
            $this->merge(['status' => trim((string) $this->input('status'))]);
        }
    }
}
