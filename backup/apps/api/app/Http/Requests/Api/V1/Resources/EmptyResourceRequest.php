<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Resources;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Resources\Concerns\HandlesResourceInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class EmptyResourceRequest extends FormRequest
{
    use HandlesResourceInput;

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
