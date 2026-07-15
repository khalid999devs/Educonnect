<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Resources;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Resources\Concerns\HandlesResourceInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreLinkResourceRequest extends FormRequest
{
    use HandlesResourceInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160', $this->plainSingleLineText()],
            'description' => ['nullable', 'string', 'max:2000', $this->plainMultilineText()],
            'topic' => ['nullable', 'string', 'max:120', $this->plainSingleLineText()],
            'course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'url' => ['required', 'string', 'max:2048', $this->httpsUrl()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, [
            'title',
            'description',
            'topic',
            'course_id',
            'url',
        ])];
    }

    /** @return array{title: string, description: ?string, topic_label: ?string, course_id: ?string, url: string} */
    public function resourceData(): array
    {
        $courseId = $this->validated('course_id');

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'topic_label' => $this->validated('topic'),
            'course_id' => is_string($courseId) ? $courseId : null,
            'url' => (string) $this->validated('url'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $url = $this->input('url');
        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'description' => $this->nullableTrimmed($this->input('description')),
            'topic' => $this->nullableTrimmed($this->input('topic')),
            'course_id' => $this->nullableTrimmed($this->input('course_id')),
            'url' => is_string($url) ? trim($url) : $url,
        ]);
    }
}
