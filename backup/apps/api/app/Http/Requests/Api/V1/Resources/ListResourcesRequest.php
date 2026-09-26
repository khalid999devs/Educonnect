<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Resources;

use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Resources\Concerns\HandlesResourceInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListResourcesRequest extends FormRequest
{
    use HandlesResourceInput;

    private const SORTS = ['updated_at', '-updated_at', 'title', '-title'];

    /** Sentinel meaning "resources filed under no course at all". */
    public const UNFILED = 'none';

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:1', 'max:100', $this->plainSingleLineText()],
            'kind' => ['nullable', 'string', Rule::in(['all', ...array_column(ResourceKind::cases(), 'value')])],
            'course_id' => ['nullable', 'string', 'regex:/^(none|[01234567][0-9abcdefghjkmnpqrstvwxyz]{25})$/D'],
            'topic' => ['nullable', 'string', 'min:1', 'max:120', $this->plainSingleLineText()],
            'file_status' => ['nullable', 'string', Rule::in(['all', ...array_column(StoredFileStatus::cases(), 'value')])],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, [
                'search',
                'kind',
                'course_id',
                'topic',
                'file_status',
                'sort',
                'per_page',
                'cursor',
            ]);
            $this->validateResourceCursor($validator, $this->sort());
        }];
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) ? $value : null;
    }

    public function kind(): ?ResourceKind
    {
        $value = $this->input('kind', 'all');

        return is_string($value) ? ResourceKind::tryFrom($value) : null;
    }

    /** Null for both "no course filter" and the unfiled sentinel; pair with unfiledOnly(). */
    public function courseId(): ?string
    {
        $value = $this->validated('course_id');

        return is_string($value) && $value !== self::UNFILED ? $value : null;
    }

    public function unfiledOnly(): bool
    {
        return $this->validated('course_id') === self::UNFILED;
    }

    public function topic(): ?string
    {
        $value = $this->validated('topic');

        return is_string($value) ? $value : null;
    }

    public function fileStatus(): ?StoredFileStatus
    {
        $value = $this->input('file_status', 'all');

        return is_string($value) ? StoredFileStatus::tryFrom($value) : null;
    }

    public function sort(): string
    {
        $value = $this->input('sort', '-updated_at');

        return is_string($value) ? $value : '-updated_at';
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    protected function prepareForValidation(): void
    {
        foreach (['search', 'kind', 'course_id', 'topic', 'file_status', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }
}
