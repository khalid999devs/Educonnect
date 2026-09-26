<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Domains\Templates\Enums\TemplateCopyDestination;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Templates\Concerns\HandlesTemplateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CopyTemplateRequest extends FormRequest
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
            'destination' => ['required', 'string', Rule::in(array_column(TemplateCopyDestination::cases(), 'value'))],
            'course_id' => [
                'required_if:destination,'.TemplateCopyDestination::Course->value,
                'prohibited_unless:destination,'.TemplateCopyDestination::Course->value,
                'nullable',
                'string',
                'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D',
            ],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['destination', 'course_id'])];
    }

    public function destination(): TemplateCopyDestination
    {
        return TemplateCopyDestination::from((string) $this->validated('destination'));
    }

    public function coursePublicId(): ?string
    {
        $value = $this->validated('course_id');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'destination' => is_string($this->input('destination')) ? trim((string) $this->input('destination')) : $this->input('destination'),
            'course_id' => $this->nullableTrimmed($this->input('course_id')),
        ]);
    }
}
