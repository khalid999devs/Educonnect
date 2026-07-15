<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Resources;

use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Resources\Concerns\HandlesResourceInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateResourceRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'string', Rule::enum(ResourceKind::class)],
            'title' => ['required', 'string', 'max:160', $this->plainSingleLineText()],
            'description' => ['nullable', 'string', 'max:2000', $this->plainMultilineText()],
            'topic' => ['nullable', 'string', 'max:120', $this->plainSingleLineText()],
            'course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'url' => ['sometimes', 'required', 'string', 'max:2048', $this->httpsUrl()],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, [
            'expected_version',
            'kind',
            'title',
            'description',
            'topic',
            'course_id',
            'url',
        ])];
    }

    /** @return array{kind: ResourceKind, title: string, description: ?string, topic_label: ?string, course_id: ?string, url?: string} */
    public function resourceData(): array
    {
        $courseId = $this->validated('course_id');
        $data = [
            'kind' => ResourceKind::from((string) $this->validated('kind')),
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'topic_label' => $this->validated('topic'),
            'course_id' => is_string($courseId) ? $courseId : null,
        ];

        if ($this->has('url')) {
            $data['url'] = (string) $this->validated('url');
        }

        return $data;
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $kind = $this->input('kind');
        $this->merge([
            'kind' => is_string($kind) ? trim($kind) : $kind,
            'title' => is_string($title) ? trim($title) : $title,
            'description' => $this->nullableTrimmed($this->input('description')),
            'topic' => $this->nullableTrimmed($this->input('topic')),
            'course_id' => $this->nullableTrimmed($this->input('course_id')),
        ]);

        if (is_string($this->input('url'))) {
            $this->merge(['url' => trim((string) $this->input('url'))]);
        }
    }
}
