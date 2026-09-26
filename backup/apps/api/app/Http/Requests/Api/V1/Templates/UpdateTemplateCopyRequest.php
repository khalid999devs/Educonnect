<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Templates\Concerns\HandlesTemplateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateTemplateCopyRequest extends FormRequest
{
    use HandlesTemplateInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:160', $this->plainSingleLineText()],
            'body' => ['required', 'string', 'max:20000', $this->plainMultilineText()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['expected_version', 'title', 'body'])];
    }

    /** @return array{title: string, body: string} */
    public function copyData(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'body' => (string) $this->validated('body'),
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $body = $this->input('body');
        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'body' => is_string($body) ? trim($body) : $body,
        ]);
    }
}
