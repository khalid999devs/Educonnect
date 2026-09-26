<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Enums\KnowledgePurpose;
use App\Domains\SecondBrain\Enums\KnowledgeSourceType;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreKnowledgeItemRequest extends FormRequest
{
    use HandlesBrainInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200', $this->plainSingleLineText()],
            'summary' => ['nullable', 'string', 'max:4000', $this->plainMultilineText()],
            'source_type' => ['required', 'string', Rule::in([
                KnowledgeSourceType::Resource->value,
                KnowledgeSourceType::Link->value,
                KnowledgeSourceType::None->value,
            ])],
            'resource_id' => [
                'exclude_unless:source_type,resource',
                'required_if:source_type,resource',
                'string',
                'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D',
            ],
            'source_url' => [
                'exclude_unless:source_type,link',
                'required_if:source_type,link',
                'string',
                'max:2048',
                'url:https',
            ],
            'authors' => ['nullable', 'string', 'max:500', $this->plainSingleLineText()],
            'published_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'venue' => ['nullable', 'string', 'max:200', $this->plainSingleLineText()],
            'doi' => ['nullable', 'string', 'max:255', $this->plainSingleLineText()],
            'purpose' => ['nullable', 'string', Rule::in(KnowledgePurpose::values())],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, [
            'title', 'summary', 'source_type', 'resource_id', 'source_url',
            'authors', 'published_year', 'venue', 'doi', 'purpose',
        ])];
    }

    /**
     * @return array{
     *     title: string,
     *     summary: ?string,
     *     source_type: string,
     *     resource_id: ?string,
     *     source_url: ?string,
     *     authors: ?string,
     *     published_year: ?int,
     *     venue: ?string,
     *     doi: ?string,
     *     purpose: ?string,
     * }
     */
    public function knowledgeItemData(): array
    {
        $publishedYear = $this->validated('published_year');
        $resourceId = $this->validated('resource_id');
        $sourceUrl = $this->validated('source_url');

        return [
            'title' => (string) $this->validated('title'),
            'summary' => $this->validated('summary'),
            'source_type' => (string) $this->validated('source_type'),
            'resource_id' => is_string($resourceId) ? $resourceId : null,
            'source_url' => is_string($sourceUrl) ? $sourceUrl : null,
            'authors' => $this->validated('authors'),
            'published_year' => $publishedYear === null ? null : (int) $publishedYear,
            'venue' => $this->validated('venue'),
            'doi' => $this->validated('doi'),
            'purpose' => $this->purpose(),
        ];
    }

    /** Null means "no purpose recorded", which is not the same as any purpose. */
    private function purpose(): ?string
    {
        $value = $this->validated('purpose');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'summary' => $this->nullableTrimmed($this->input('summary')),
            'source_type' => $this->nullableTrimmed($this->input('source_type')),
            'resource_id' => $this->nullableTrimmed($this->input('resource_id')),
            'source_url' => $this->nullableTrimmed($this->input('source_url')),
            'authors' => $this->nullableTrimmed($this->input('authors')),
            'venue' => $this->nullableTrimmed($this->input('venue')),
            'doi' => $this->nullableTrimmed($this->input('doi')),
            'purpose' => $this->nullableTrimmed($this->input('purpose')),
        ]);
    }
}
