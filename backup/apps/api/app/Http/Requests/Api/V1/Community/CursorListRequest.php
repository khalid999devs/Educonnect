<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Community;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Community\Concerns\HandlesCommunityInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Shared bounded cursor list request for feed, per-community posts, and comments.
 * Subclasses declare the fixed sort backing the cursor.
 */
abstract class CursorListRequest extends FormRequest
{
    use HandlesCommunityInput;

    abstract protected function cursorSort(): string;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['per_page', 'cursor']);
            $this->validateCursor($validator, $this->cursorSort());
        }];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cursor'))) {
            $this->merge(['cursor' => trim((string) $this->input('cursor'))]);
        }
    }
}
