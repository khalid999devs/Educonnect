<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Study;

use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Study\Concerns\HandlesStudyInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListStudyArtifactsRequest extends FormRequest
{
    use HandlesStudyInput;

    private const SORTS = ['created_at', '-created_at', 'updated_at', '-updated_at'];

    private const ALLOWED = ['item_id', 'kind', 'status', 'sort', 'per_page', 'cursor'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'kind' => ['nullable', 'string', Rule::in(StudyArtifactKind::values())],
            'status' => ['nullable', 'string', Rule::in(StudyArtifactStatus::values())],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, self::ALLOWED);
            $this->validateCursor($validator, $this->sort());
        }];
    }

    public function itemId(): ?string
    {
        $value = $this->validated('item_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function kind(): ?StudyArtifactKind
    {
        $value = $this->validated('kind');

        return is_string($value) ? StudyArtifactKind::tryFrom($value) : null;
    }

    public function status(): ?StudyArtifactStatus
    {
        $value = $this->validated('status');

        return is_string($value) ? StudyArtifactStatus::tryFrom($value) : null;
    }

    public function sort(): string
    {
        $value = $this->input('sort', '-created_at');

        return is_string($value) ? $value : '-created_at';
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    protected function prepareForValidation(): void
    {
        foreach (['item_id', 'kind', 'status', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
