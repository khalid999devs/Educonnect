<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Domains\Community\Enums\ReportStatus;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ResolveAdminReportRequest extends FormRequest
{
    use InteractsWithAdmin;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::in([
                ReportStatus::Actioned->value,
                ReportStatus::Dismissed->value,
            ])],
            'hide_content' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => $this->reasonRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $note = $this->input('note');

        if (is_string($note)) {
            $trimmed = trim($note);
            $this->merge(['note' => $trimmed === '' ? null : $trimmed]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['resolution', 'hide_content', 'note', 'expected_version', 'reason']);
        });
    }

    public function resolution(): string
    {
        return (string) $this->input('resolution');
    }

    public function hideContent(): bool
    {
        return $this->boolean('hide_content');
    }

    public function note(): ?string
    {
        $value = $this->input('note');

        return is_string($value) ? $value : null;
    }

    public function expectedVersion(): int
    {
        return (int) $this->input('expected_version');
    }
}
