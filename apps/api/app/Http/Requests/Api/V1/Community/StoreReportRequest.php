<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Community;

use App\Domains\Community\Enums\ReportReason;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Community\Concerns\HandlesCommunityInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreReportRequest extends FormRequest
{
    use HandlesCommunityInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_map(
                static fn (ReportReason $reason): string => $reason->value,
                ReportReason::cases(),
            ))],
            'detail' => ['nullable', 'string', 'min:1', 'max:1000', $this->plainMultilineText()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['reason', 'detail'])];
    }

    public function reason(): string
    {
        return (string) $this->validated('reason');
    }

    public function detail(): ?string
    {
        $value = $this->validated('detail');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['detail' => $this->nullableTrimmed($this->input('detail'))]);
    }
}
