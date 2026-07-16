<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Intake;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Intake\Concerns\HandlesIntakeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreFileIntakeRequest extends FormRequest
{
    use HandlesIntakeInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'context' => ['nullable', 'string', 'max:2000', $this->plainSingleLineText()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['resource_id', 'context'])];
    }

    public function resourcePublicId(): string
    {
        return (string) $this->validated('resource_id');
    }

    public function context(): ?string
    {
        $value = $this->validated('context');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'resource_id' => $this->nullableTrimmed($this->input('resource_id')),
            'context' => $this->nullableTrimmed($this->input('context')),
        ]);
    }
}
