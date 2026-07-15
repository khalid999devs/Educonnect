<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Templates\Concerns\HandlesTemplateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ArchiveTemplateCopyRequest extends FormRequest
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
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['expected_version'])];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }
}
