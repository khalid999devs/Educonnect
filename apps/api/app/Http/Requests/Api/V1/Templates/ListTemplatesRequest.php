<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Support\TemplateCursorSort;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Templates\Concerns\HandlesTemplateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListTemplatesRequest extends FormRequest
{
    use HandlesTemplateInput;

    private const SORTS = ['title', '-last_reviewed_at'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:1', 'max:100', $this->plainSingleLineText()],
            'category' => ['nullable', 'string', 'min:1', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'preference' => ['nullable', 'string', Rule::in([
                'all',
                ...array_column(GuidancePreferenceState::cases(), 'value'),
                'none',
            ])],
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
                'category',
                'preference',
                'sort',
                'per_page',
                'cursor',
            ]);
            $this->validateGuidanceCursor(
                $validator,
                $this->sort(),
                static fn (string $sort): array => TemplateCursorSort::for($sort),
            );
        }];
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) ? $value : null;
    }

    public function category(): ?string
    {
        $value = $this->validated('category');

        return is_string($value) ? $value : null;
    }

    public function preference(): string
    {
        $value = $this->input('preference', 'all');

        return is_string($value) ? $value : 'all';
    }

    public function sort(): string
    {
        $value = $this->input('sort', 'title');

        return is_string($value) ? $value : 'title';
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    protected function prepareForValidation(): void
    {
        foreach (['search', 'category', 'preference', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
