<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Intake;

use App\Domains\Intake\Queries\ReadIntakeExtraction;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Intake\Concerns\HandlesIntakeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ShowIntakeExtractionRequest extends FormRequest
{
    use HandlesIntakeInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'offset' => ['nullable', 'integer', 'min:0', 'max:'.$this->maxOffset()],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ReadIntakeExtraction::MAX_WINDOW_CHARACTERS],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['offset', 'limit'])];
    }

    public function offset(): int
    {
        $value = $this->validated('offset');

        return is_numeric($value) ? (int) $value : 0;
    }

    public function limit(): int
    {
        $value = $this->validated('limit');

        return is_numeric($value) ? (int) $value : ReadIntakeExtraction::MAX_WINDOW_CHARACTERS;
    }

    private function maxOffset(): int
    {
        return max(0, (int) config('intake.max_extracted_characters'));
    }
}
