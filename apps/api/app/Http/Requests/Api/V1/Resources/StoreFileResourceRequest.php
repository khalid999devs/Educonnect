<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Resources;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Resources\Concerns\HandlesResourceInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreFileResourceRequest extends FormRequest
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
            'original_name' => ['required', 'string', 'max:255', $this->safeOriginalName()],
            'mime_type' => ['required', 'string', Rule::in($this->allowedMimeTypes())],
            'size' => ['required', 'integer', 'min:1', 'max:26214400'],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D'],
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
            'original_name',
            'mime_type',
            'size',
            'sha256',
        ])];
    }

    /** @return array{title: string, description: ?string, topic_label: ?string, course_id: ?string, original_name: string, mime_type: string, size: int, sha256: string} */
    public function resourceData(): array
    {
        $courseId = $this->validated('course_id');

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'topic_label' => $this->validated('topic'),
            'course_id' => is_string($courseId) ? $courseId : null,
            'original_name' => (string) $this->validated('original_name'),
            'mime_type' => (string) $this->validated('mime_type'),
            'size' => (int) $this->validated('size'),
            'sha256' => (string) $this->validated('sha256'),
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'original_name', 'mime_type', 'sha256'] as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $this->merge([$key => trim($value)]);
            }
        }

        $this->merge([
            'description' => $this->nullableTrimmed($this->input('description')),
            'topic' => $this->nullableTrimmed($this->input('topic')),
            'course_id' => $this->nullableTrimmed($this->input('course_id')),
        ]);
    }

    /** @return list<string> */
    private function allowedMimeTypes(): array
    {
        $configured = config('resources.allowed_mime_types');

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_keys($configured), 'is_string'));
    }
}
