<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Guidance;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Guidance\Concerns\HandlesGuidanceInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class EmptyGuidanceRequest extends FormRequest
{
    use HandlesGuidanceInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, [])];
    }
}
