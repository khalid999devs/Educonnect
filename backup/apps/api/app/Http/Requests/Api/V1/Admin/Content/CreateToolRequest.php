<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content;

use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use App\Http\Requests\Api\V1\Admin\Content\Concerns\HandlesToolContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateToolRequest extends FormRequest
{
    use HandlesToolContent;
    use InteractsWithAdmin;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->toolContentRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, array_keys($this->toolContentRules()));
        });
    }

    /** @return array<string, mixed> */
    public function contentData(): array
    {
        return $this->validated();
    }
}
