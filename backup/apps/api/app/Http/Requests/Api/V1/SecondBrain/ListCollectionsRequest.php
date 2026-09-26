<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Enums\CollectionKind;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListCollectionsRequest extends FormRequest
{
    use HandlesBrainInput;

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
            'kind' => ['nullable', 'string', Rule::in(CollectionKind::values())],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['search', 'kind', 'sort', 'per_page', 'cursor']);
            $this->validateCursor($validator, $this->sort());
        }];
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) ? $value : null;
    }

    public function kind(): ?string
    {
        $value = $this->validated('kind');

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
        foreach (['search', 'kind', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
