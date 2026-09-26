<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Intake;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Intake\Concerns\HandlesIntakeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreLinkIntakeRequest extends FormRequest
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
            'url' => ['required', 'string', 'max:2048', 'regex:/^https:\/\/[^\s]+$/D'],
            'context' => ['nullable', 'string', 'max:2000', $this->plainSingleLineText()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['url', 'context'])];
    }

    public function url(): string
    {
        return (string) $this->validated('url');
    }

    public function context(): ?string
    {
        $value = $this->validated('context');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('url');
        $this->merge([
            'url' => is_string($url) ? trim($url) : $url,
            'context' => $this->nullableTrimmed($this->input('context')),
        ]);
    }
}
