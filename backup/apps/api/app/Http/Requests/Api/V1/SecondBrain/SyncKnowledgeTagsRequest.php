<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncKnowledgeTagsRequest extends FormRequest
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
            'tags' => ['present', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'min:1', 'max:60', $this->plainSingleLineText()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['tags'])];
    }

    /** @return list<string> */
    public function tagNames(): array
    {
        $tags = $this->validated('tags');

        return is_array($tags) ? array_values(array_map(strval(...), $tags)) : [];
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (is_array($tags)) {
            $this->merge([
                'tags' => array_values(array_map(
                    fn (mixed $tag): mixed => is_string($tag) ? trim($tag) : $tag,
                    $tags,
                )),
            ]);
        }
    }
}
