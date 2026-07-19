<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content;

use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared list filter for every catalog type - the four lifecycle states are
 * identical across tools, prompts, and workflows.
 */
final class ListAdminContentRequest extends FormRequest
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
            'state' => ['sometimes', 'string', Rule::in(['draft', 'in_review', 'published', 'archived'])],
            'cursor' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['state', 'cursor', 'per_page']);
        });
    }

    public function state(): ?string
    {
        $value = $this->input('state');

        return is_string($value) ? $value : null;
    }
}
