<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SuspendUserRequest extends FormRequest
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
            'reason' => $this->reasonRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['reason']);
        });
    }
}
