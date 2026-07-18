<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Domains\Audit\Enums\AuditAction;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListAuditEventsRequest extends FormRequest
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
            'action' => ['sometimes', 'string', Rule::enum(AuditAction::class)],
            'actor_id' => ['sometimes', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/'],
            'subject_type' => ['sometimes', 'string', 'max:100'],
            'cursor' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['action', 'actor_id', 'subject_type', 'cursor', 'per_page']);
            $this->validateCreatedAtCursor($validator);
        });
    }

    public function action(): ?string
    {
        $value = $this->input('action');

        return is_string($value) ? $value : null;
    }

    public function actorPublicId(): ?string
    {
        $value = $this->input('actor_id');

        return is_string($value) ? $value : null;
    }

    public function subjectType(): ?string
    {
        $value = $this->input('subject_type');

        return is_string($value) ? $value : null;
    }
}
