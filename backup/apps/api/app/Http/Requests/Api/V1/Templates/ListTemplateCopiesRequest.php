<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Domains\Templates\Enums\TemplateCopyDestination;
use App\Domains\Templates\Support\TemplateCopyCursorSort;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Templates\Concerns\HandlesTemplateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListTemplateCopiesRequest extends FormRequest
{
    use HandlesTemplateInput;

    private const SORTS = ['-created_at', 'title'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'destination' => ['nullable', 'string', Rule::in(array_column(TemplateCopyDestination::cases(), 'value'))],
            'course' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'include_archived' => ['nullable', 'boolean'],
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
                'destination',
                'course',
                'include_archived',
                'sort',
                'per_page',
                'cursor',
            ]);
            $this->validateGuidanceCursor(
                $validator,
                $this->sort(),
                static fn (string $sort): array => TemplateCopyCursorSort::for($sort),
            );
        }];
    }

    public function destination(): ?TemplateCopyDestination
    {
        $value = $this->validated('destination');

        return is_string($value) ? TemplateCopyDestination::tryFrom($value) : null;
    }

    public function coursePublicId(): ?string
    {
        $value = $this->validated('course');

        return is_string($value) ? $value : null;
    }

    public function includeArchived(): bool
    {
        return filter_var($this->input('include_archived', false), FILTER_VALIDATE_BOOL);
    }

    public function sort(): string
    {
        $value = $this->input('sort', '-created_at');

        return is_string($value) ? $value : '-created_at';
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    protected function prepareForValidation(): void
    {
        foreach (['destination', 'course', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }

        // Query strings carry booleans as the text "true"/"false", which the
        // `boolean` rule rejects; normalise to a real boolean before validation.
        if ($this->has('include_archived')) {
            $this->merge(['include_archived' => $this->boolean('include_archived')]);
        }
    }
}
