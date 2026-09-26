<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Enums\KnowledgeLinkRelation;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreKnowledgeLinkRequest extends FormRequest
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
            'target_id' => ['required', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'relation_type' => ['nullable', 'string', Rule::in(KnowledgeLinkRelation::values())],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['target_id', 'relation_type'])];
    }

    public function targetId(): string
    {
        return (string) $this->validated('target_id');
    }

    public function relationType(): string
    {
        $value = $this->validated('relation_type');

        return is_string($value) ? $value : KnowledgeLinkRelation::Related->value;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'target_id' => $this->nullableTrimmed($this->input('target_id')),
            'relation_type' => $this->nullableTrimmed($this->input('relation_type')),
        ]);
    }
}
