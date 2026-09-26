<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Mentor;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Mentor\Concerns\HandlesMentorInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ListMentorsRequest extends FormRequest
{
    use HandlesMentorInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:1', 'max:120', $this->plainSingleLineText()],
            'expertise' => ['nullable', 'string', 'min:1', 'max:40', $this->plainSingleLineText()],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['search', 'expertise', 'per_page', 'cursor']);
            $this->validateCursor($validator, '-created_at');
        }];
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) ? $value : null;
    }

    public function expertise(): ?string
    {
        $value = $this->validated('expertise');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['search', 'expertise', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
