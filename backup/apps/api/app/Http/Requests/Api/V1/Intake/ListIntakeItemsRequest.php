<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Intake;

use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Support\IntakeCursorSort;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Intake\Concerns\HandlesIntakeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListIntakeItemsRequest extends FormRequest
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
            'state' => ['nullable', 'string', Rule::in(array_column(IntakeState::cases(), 'value'))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['state', 'per_page', 'cursor']);
            $this->validateGuidanceCursor(
                $validator,
                '-created_at',
                static fn (string $sort): array => IntakeCursorSort::for($sort),
            );
        }];
    }

    public function state(): ?IntakeState
    {
        $value = $this->validated('state');

        return is_string($value) ? IntakeState::tryFrom($value) : null;
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    protected function prepareForValidation(): void
    {
        foreach (['state', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
