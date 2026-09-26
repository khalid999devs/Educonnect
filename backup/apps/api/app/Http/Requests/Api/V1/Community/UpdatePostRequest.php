<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Community;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Community\Concerns\HandlesCommunityInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdatePostRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'body' => ['required', 'string', 'min:1', 'max:5000', $this->plainMultilineText()],
            'shared_resource_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['title', 'body', 'shared_resource_id', 'expected_version'])];
    }

    public function title(): ?string
    {
        $value = $this->validated('title');

        return is_string($value) ? $value : null;
    }

    public function body(): string
    {
        return (string) $this->validated('body');
    }

    public function sharedResourceId(): ?string
    {
        $value = $this->validated('shared_resource_id');

        return is_string($value) ? $value : null;
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->nullableTrimmed($this->input('title')),
            'shared_resource_id' => $this->nullableTrimmed($this->input('shared_resource_id')),
        ]);
    }
}
