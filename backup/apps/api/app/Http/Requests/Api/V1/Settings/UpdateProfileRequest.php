<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Settings;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Settings\Concerns\HandlesSettingsInput;
use App\Support\IsoCountryCodes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProfileRequest extends FormRequest
{
    use HandlesSettingsInput;

    /** @var list<string> */
    private const FIELDS = [
        'institution_name',
        'institution_country_code',
        'department',
        'degree',
        'major',
        'year_label',
        'term_label',
    ];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['present', 'nullable', 'string', 'max:200', $this->plainSingleLineText()],
            'institution_country_code' => ['present', 'nullable', 'string', 'size:2', Rule::in(IsoCountryCodes::ALPHA_2)],
            'department' => ['present', 'nullable', 'string', 'max:160', $this->plainSingleLineText()],
            'degree' => ['present', 'nullable', 'string', 'max:160', $this->plainSingleLineText()],
            'major' => ['present', 'nullable', 'string', 'max:160', $this->plainSingleLineText()],
            'year_label' => ['present', 'nullable', 'string', 'max:80', $this->plainSingleLineText()],
            'term_label' => ['present', 'nullable', 'string', 'max:80', $this->plainSingleLineText()],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnknownFields($validator, self::FIELDS);
                $this->validateInstitutionPair($validator);
            },
        ];
    }

    /**
     * @return array{institution_name: ?string, institution_country_code: ?string, department: ?string, degree: ?string, major: ?string, year_label: ?string, term_label: ?string}
     */
    public function profileData(): array
    {
        return [
            'institution_name' => $this->validatedNullableString('institution_name'),
            'institution_country_code' => $this->validatedNullableString('institution_country_code'),
            'department' => $this->validatedNullableString('department'),
            'degree' => $this->validatedNullableString('degree'),
            'major' => $this->validatedNullableString('major'),
            'year_label' => $this->validatedNullableString('year_label'),
            'term_label' => $this->validatedNullableString('term_label'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->nullableTrimmed($this->input($field));

            if ($field === 'institution_country_code' && is_string($value)) {
                $value = strtoupper($value);
            }

            $normalized[$field] = $value;
        }

        $this->merge($normalized);
    }

    private function validateInstitutionPair(Validator $validator): void
    {
        $hasName = $this->input('institution_name') !== null;
        $hasCode = $this->input('institution_country_code') !== null;

        if ($hasName === $hasCode) {
            return;
        }

        $validator->errors()->add(
            'institution_country_code',
            'The institution name and country code must be provided together.',
        );
    }

    private function validatedNullableString(string $field): ?string
    {
        $value = $this->validated($field);

        return is_string($value) ? $value : null;
    }
}
