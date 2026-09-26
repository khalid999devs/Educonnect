<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content;

use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use App\Http\Requests\Api\V1\Admin\Content\Concerns\HandlesPromptContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdatePromptRequest extends FormRequest
{
    use HandlesPromptContent;
    use InteractsWithAdmin;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->promptContentRules(),
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields(
                $validator,
                [...array_keys($this->promptContentRules()), 'expected_version'],
            );
        });
    }

    public function expectedVersion(): int
    {
        return (int) $this->input('expected_version');
    }

    /** @return array<string, mixed> */
    public function contentData(): array
    {
        return $this->safe()->except('expected_version');
    }
}
