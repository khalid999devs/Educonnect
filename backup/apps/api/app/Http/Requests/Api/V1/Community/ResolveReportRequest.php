<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Community;

use App\Domains\Community\Enums\ReportStatus;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Community\Concerns\HandlesCommunityInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ResolveReportRequest extends FormRequest
{
    use HandlesCommunityInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::in([
                ReportStatus::Actioned->value,
                ReportStatus::Dismissed->value,
            ])],
            'hide_content' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['resolution', 'hide_content', 'note', 'expected_version'])];
    }

    public function resolution(): string
    {
        return (string) $this->validated('resolution');
    }

    public function hideContent(): bool
    {
        return $this->boolean('hide_content');
    }

    public function note(): ?string
    {
        $value = $this->validated('note');

        return is_string($value) ? $value : null;
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => $this->nullableTrimmed($this->input('note'))]);
    }
}
