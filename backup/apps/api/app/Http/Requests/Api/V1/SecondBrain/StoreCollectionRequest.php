<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Enums\CollectionKind;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCollectionRequest extends FormRequest
{
    use HandlesBrainInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', $this->plainSingleLineText()],
            'description' => ['nullable', 'string', 'max:1000', $this->plainMultilineText()],
            'kind' => ['nullable', 'string', Rule::in(CollectionKind::values())],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['name', 'description', 'kind'])];
    }

    /** @return array{name: string, description: ?string, kind: string} */
    public function collectionData(): array
    {
        $kind = $this->validated('kind');

        return [
            'name' => (string) $this->validated('name'),
            'description' => $this->validated('description'),
            'kind' => is_string($kind) ? $kind : CollectionKind::General->value,
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'description' => $this->nullableTrimmed($this->input('description')),
            'kind' => $this->nullableTrimmed($this->input('kind')),
        ]);
    }
}
