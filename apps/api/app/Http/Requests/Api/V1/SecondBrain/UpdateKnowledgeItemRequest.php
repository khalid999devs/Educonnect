<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateKnowledgeItemRequest extends FormRequest
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
            'authors' => ['nullable', 'string', 'max:500', $this->plainSingleLineText()],
            'published_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'venue' => ['nullable', 'string', 'max:200', $this->plainSingleLineText()],
            'doi' => ['nullable', 'string', 'max:255', $this->plainSingleLineText()],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, [
            'title', 'summary', 'authors', 'published_year', 'venue', 'doi', 'expected_version',
        ])];
    }

    /** @return array{title: string, summary: ?string, authors: ?string, published_year: ?int, venue: ?string, doi: ?string} */
    public function knowledgeItemData(): array
    {
        $publishedYear = $this->validated('published_year');

        return [
            'title' => (string) $this->validated('title'),
            'summary' => $this->validated('summary'),
            'authors' => $this->validated('authors'),
            'published_year' => $publishedYear === null ? null : (int) $publishedYear,
            'venue' => $this->validated('venue'),
            'doi' => $this->validated('doi'),
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'summary' => $this->nullableTrimmed($this->input('summary')),
            'authors' => $this->nullableTrimmed($this->input('authors')),
            'venue' => $this->nullableTrimmed($this->input('venue')),
            'doi' => $this->nullableTrimmed($this->input('doi')),
        ]);
    }
}
