<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Courses;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Courses\Concerns\HandlesAcademicInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAcademicTermRequest extends FormRequest
{
    use HandlesAcademicInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80', $this->plainSingleLineText()],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, $this->allowedFields());
            $startsOn = $this->input('starts_on');
            $endsOn = $this->input('ends_on');

            if (is_string($startsOn) && is_string($endsOn) && $endsOn < $startsOn) {
                $validator->errors()->add('ends_on', 'The ends on date must be on or after the starts on date.');
            }
        }];
    }

    /** @return array{label: string, starts_on: ?string, ends_on: ?string} */
    public function termData(): array
    {
        return [
            'label' => (string) $this->validated('label'),
            'starts_on' => $this->validated('starts_on'),
            'ends_on' => $this->validated('ends_on'),
        ];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['label', 'starts_on', 'ends_on'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => is_string($this->input('label')) ? trim((string) $this->input('label')) : $this->input('label'),
            'starts_on' => $this->nullableTrimmed($this->input('starts_on')),
            'ends_on' => $this->nullableTrimmed($this->input('ends_on')),
        ]);
    }
}
