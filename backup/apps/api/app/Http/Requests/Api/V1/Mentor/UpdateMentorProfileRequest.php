<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Mentor;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Mentor\Concerns\HandlesMentorInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateMentorProfileRequest extends FormRequest
{
    use HandlesMentorInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'bio' => ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()],
            'expertise' => ['nullable', 'array', 'max:12'],
            'expertise.*' => ['string', 'min:1', 'max:40', $this->plainSingleLineText()],
            'availability_note' => ['nullable', 'string', 'min:1', 'max:280', $this->plainSingleLineText()],
            'is_accepting_requests' => ['nullable', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields(
            $validator,
            ['headline', 'bio', 'expertise', 'availability_note', 'is_accepting_requests', 'expected_version'],
        )];
    }

    /** @return array{headline: string, bio: string, expertise: list<string>, availability_note: ?string, is_accepting_requests: bool} */
    public function profileData(): array
    {
        return [
            'headline' => (string) $this->validated('headline'),
            'bio' => (string) $this->validated('bio'),
            'expertise' => $this->expertise(),
            'availability_note' => is_string($this->validated('availability_note')) ? (string) $this->validated('availability_note') : null,
            'is_accepting_requests' => $this->has('is_accepting_requests') ? $this->boolean('is_accepting_requests') : true,
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    /** @return list<string> */
    private function expertise(): array
    {
        $value = $this->validated('expertise');

        if (! is_array($value)) {
            return [];
        }

        $tags = [];
        foreach ($value as $tag) {
            if (is_string($tag)) {
                $trimmed = trim($tag);
                if ($trimmed !== '' && ! in_array($trimmed, $tags, true)) {
                    $tags[] = $trimmed;
                }
            }
        }

        return $tags;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'headline' => $this->nullableTrimmed($this->input('headline')),
            'availability_note' => $this->nullableTrimmed($this->input('availability_note')),
        ]);
    }
}
