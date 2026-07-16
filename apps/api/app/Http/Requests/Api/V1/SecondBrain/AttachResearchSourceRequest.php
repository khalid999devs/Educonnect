<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Enums\ReadingStatus;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttachResearchSourceRequest extends FormRequest
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
            'knowledge_item_id' => ['required', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'reading_status' => ['nullable', 'string', Rule::in(ReadingStatus::values())],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields(
            $validator,
            ['knowledge_item_id', 'reading_status'],
        )];
    }

    public function knowledgeItemId(): string
    {
        return (string) $this->validated('knowledge_item_id');
    }

    public function readingStatus(): string
    {
        $value = $this->validated('reading_status');

        return is_string($value) ? $value : ReadingStatus::ToRead->value;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'knowledge_item_id' => $this->nullableTrimmed($this->input('knowledge_item_id')),
            'reading_status' => $this->nullableTrimmed($this->input('reading_status')),
        ]);
    }
}
