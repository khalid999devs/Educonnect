<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Guidance;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Guidance\Concerns\HandlesGuidanceInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ShowGuidanceRequest extends FormRequest
{
    use HandlesGuidanceInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'min:1', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['category'])];
    }

    public function categorySlug(): string
    {
        $value = $this->validated('category');

        return is_string($value) ? $value : '';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('category'))) {
            $this->merge(['category' => trim((string) $this->input('category'))]);
        }
    }
}
