<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Enums\KnowledgePurpose;
use App\Domains\SecondBrain\Enums\KnowledgeSourceType;
use App\Domains\SecondBrain\Queries\ListOwnedKnowledgeItems;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListKnowledgeItemsRequest extends FormRequest
{
    use HandlesBrainInput;

    private const SORTS = ['created_at', '-created_at', 'updated_at', '-updated_at'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:1', 'max:200', $this->plainSingleLineText()],
            'collection_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'tag' => ['nullable', 'string', 'min:1', 'max:60', $this->plainSingleLineText()],
            'source_type' => ['nullable', 'string', Rule::in([
                KnowledgeSourceType::Resource->value,
                KnowledgeSourceType::Link->value,
                KnowledgeSourceType::None->value,
            ])],
            'purpose' => ['nullable', 'string', Rule::in([
                ...KnowledgePurpose::values(),
                ListOwnedKnowledgeItems::UNSET_PURPOSE,
            ])],
            'saved' => ['nullable', 'boolean'],
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
                'search', 'collection_id', 'tag', 'source_type', 'purpose', 'saved', 'sort', 'per_page', 'cursor',
            ]);
            $this->validateCursor($validator, $this->sort());
        }];
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) ? $value : null;
    }

    public function collectionId(): ?string
    {
        $value = $this->validated('collection_id');

        return is_string($value) ? $value : null;
    }

    public function tag(): ?string
    {
        $value = $this->validated('tag');

        return is_string($value) ? $value : null;
    }

    public function sourceType(): ?string
    {
        $value = $this->validated('source_type');

        return is_string($value) ? $value : null;
    }

    /** Returns the `none` sentinel verbatim: the query owns its meaning. */
    public function purpose(): ?string
    {
        $value = $this->validated('purpose');

        return is_string($value) ? $value : null;
    }

    /** Null when the caller never mentions the filter; only `true` narrows. */
    public function saved(): ?bool
    {
        if ($this->validated('saved') === null) {
            return null;
        }

        return $this->boolean('saved');
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
        foreach (['search', 'collection_id', 'tag', 'source_type', 'purpose', 'sort', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }

        // A query string carries `saved` as the text "true"/"false", which the
        // boolean rule rejects. Normalise it the way ListTasksRequest does for
        // has_due so the same URL that a browser produces validates.
        if (is_string($this->input('saved'))) {
            $value = filter_var($this->input('saved'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if (is_bool($value)) {
                $this->merge(['saved' => $value]);
            }
        }
    }
}
