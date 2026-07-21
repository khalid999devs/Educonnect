<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Study;

use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Study\Concerns\HandlesStudyInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreateStudyGenerationRequest extends FormRequest
{
    use HandlesStudyInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(StudyArtifactKind::values())],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['kind'])];
    }

    public function kind(): StudyArtifactKind
    {
        return StudyArtifactKind::from((string) $this->validated('kind'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('kind'))) {
            $this->merge(['kind' => trim((string) $this->input('kind'))]);
        }
    }
}
