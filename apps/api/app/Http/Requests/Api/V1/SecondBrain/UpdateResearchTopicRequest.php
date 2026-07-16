<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateResearchTopicRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000', $this->plainMultilineText()],
            'keywords' => ['present', 'array', 'max:20'],
            'keywords.*' => ['required', 'string', 'min:1', 'max:60', $this->plainSingleLineText()],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields(
            $validator,
            ['title', 'description', 'keywords', 'expected_version'],
        )];
    }

    /** @return array{title: string, description: ?string, keywords: list<string>} */
    public function topicData(): array
    {
        $keywords = $this->validated('keywords');

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'keywords' => is_array($keywords) ? array_values(array_map(strval(...), $keywords)) : [],
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $keywords = $this->input('keywords');
        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'description' => $this->nullableTrimmed($this->input('description')),
            'keywords' => is_array($keywords)
                ? array_values(array_map(
                    fn (mixed $keyword): mixed => is_string($keyword) ? trim($keyword) : $keyword,
                    $keywords,
                ))
                : $keywords,
        ]);
    }
}
