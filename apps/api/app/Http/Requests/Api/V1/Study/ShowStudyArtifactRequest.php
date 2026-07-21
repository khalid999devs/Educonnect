<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Study;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Study\Concerns\HandlesStudyInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ShowStudyArtifactRequest extends FormRequest
{
    use HandlesStudyInput;

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
