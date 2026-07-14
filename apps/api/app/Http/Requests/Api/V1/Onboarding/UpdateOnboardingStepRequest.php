<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Domains\Onboarding\Enums\OnboardingStep;
use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class UpdateOnboardingStepRequest extends FormRequest
{
    /** @var list<string> */
    private const ISO_3166_ALPHA_2 = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS',
        'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
        'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
        'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM',
        'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM',
        'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC',
        'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
        'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
        'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG',
        'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
        'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO',
        'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
        'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
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
        $common = [
            'expected_version' => ['required', 'integer', 'min:0'],
            'state' => [
                'required',
                'string',
                Rule::in($this->step() === OnboardingStep::Institution
                    ? ['completed']
                    : ['completed', 'skipped']),
            ],
            'data' => ['required_if:state,completed', 'prohibited_if:state,skipped', 'array'],
        ];

        return [
            ...$common,
            ...match ($this->step()) {
                OnboardingStep::Institution => $this->institutionRules(),
                OnboardingStep::Program => $this->programRules(),
                OnboardingStep::StudyStage => $this->studyStageRules(),
                OnboardingStep::Courses => $this->courseRules(),
                OnboardingStep::Goals => $this->goalRules(),
                OnboardingStep::FirstSource => $this->firstSourceRules(),
            },
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnknownFields($validator);
                $this->validateStepCompleteness($validator);
                $this->validateCourseUniqueness($validator);
                $this->validateSourceUrl($validator);
            },
        ];
    }

    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }

    public function step(): OnboardingStep
    {
        $step = $this->route('step');

        if ($step instanceof OnboardingStep) {
            return $step;
        }

        $resolved = is_string($step) ? OnboardingStep::tryFrom($step) : null;

        if (! $resolved instanceof OnboardingStep) {
            throw new LogicException('A supported onboarding step is required.');
        }

        return $resolved;
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    /**
     * @return array<string, mixed>
     */
    public function stepPayload(): array
    {
        $state = (string) $this->validated('state');

        if ($state === 'skipped') {
            return ['skip' => true, 'data' => []];
        }

        $data = $this->validated('data');

        if (! is_array($data)) {
            throw new LogicException('Validated onboarding step data must be an array.');
        }

        return [
            'skip' => false,
            'data' => match ($this->step()) {
                OnboardingStep::Institution => [
                    'institution_name' => (string) $data['institution_name'],
                    'institution_country_code' => (string) $data['institution_country_code'],
                ],
                OnboardingStep::Program => [
                    'department' => $data['department'] ?? null,
                    'degree' => $data['degree'] ?? null,
                    'major' => $data['major'] ?? null,
                ],
                OnboardingStep::StudyStage => [
                    'year_label' => $data['year_label'] ?? null,
                    'term_label' => $data['term_label'] ?? null,
                ],
                OnboardingStep::Courses => [
                    'courses' => array_map(
                        static fn (array $course): array => [
                            'title' => (string) $course['title'],
                            'code' => $course['code'] ?? null,
                        ],
                        $data['courses'],
                    ),
                ],
                OnboardingStep::Goals => [
                    'goals' => $data['goals'],
                    'problems' => $data['problems'],
                ],
                OnboardingStep::FirstSource => [
                    'url' => (string) $data['url'],
                    'title' => $data['title'] ?? null,
                ],
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->input('data');

        if (! is_array($data)) {
            return;
        }

        $normalized = match ($this->step()) {
            OnboardingStep::Institution => $this->normalizeInstitution($data),
            OnboardingStep::Program => $this->normalizeNullableStrings($data, ['department', 'degree', 'major']),
            OnboardingStep::StudyStage => $this->normalizeNullableStrings($data, ['year_label', 'term_label']),
            OnboardingStep::Courses => $this->normalizeCourses($data),
            OnboardingStep::Goals => $this->normalizeStringLists($data, ['goals', 'problems']),
            OnboardingStep::FirstSource => $this->normalizeFirstSource($data),
        };

        $this->merge(['data' => $normalized]);
    }

    /** @return array<string, mixed> */
    private function institutionRules(): array
    {
        return [
            'data.institution_country_code' => [
                'required_if:state,completed',
                'string',
                'size:2',
                Rule::in(self::ISO_3166_ALPHA_2),
            ],
            'data.institution_name' => [
                'required_if:state,completed',
                'string',
                'max:200',
                $this->plainSingleLineText(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function programRules(): array
    {
        return [
            'data.department' => ['nullable', 'string', 'max:160', $this->plainSingleLineText()],
            'data.degree' => ['nullable', 'string', 'max:160', $this->plainSingleLineText()],
            'data.major' => ['nullable', 'string', 'max:160', $this->plainSingleLineText()],
        ];
    }

    /** @return array<string, mixed> */
    private function studyStageRules(): array
    {
        return [
            'data.year_label' => ['nullable', 'string', 'max:80', $this->plainSingleLineText()],
            'data.term_label' => ['nullable', 'string', 'max:80', $this->plainSingleLineText()],
        ];
    }

    /** @return array<string, mixed> */
    private function courseRules(): array
    {
        return [
            'data.courses' => ['required_if:state,completed', 'array', 'list', 'min:1', 'max:12'],
            'data.courses.*' => ['array'],
            'data.courses.*.title' => ['required', 'string', 'max:160', $this->plainSingleLineText()],
            'data.courses.*.code' => ['nullable', 'string', 'max:32', $this->plainSingleLineText()],
        ];
    }

    /** @return array<string, mixed> */
    private function goalRules(): array
    {
        return [
            'data.goals' => ['present_if:state,completed', 'array', 'list', 'max:8'],
            'data.goals.*' => ['string', 'min:1', 'max:200', $this->plainSingleLineText()],
            'data.problems' => ['present_if:state,completed', 'array', 'list', 'max:8'],
            'data.problems.*' => ['string', 'min:1', 'max:200', $this->plainSingleLineText()],
        ];
    }

    /** @return array<string, mixed> */
    private function firstSourceRules(): array
    {
        return [
            'data.url' => ['required_if:state,completed', 'string', 'max:2048', 'url:http,https'],
            'data.title' => ['nullable', 'string', 'max:255', $this->plainSingleLineText()],
        ];
    }

    private function plainSingleLineText(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
                || str_contains($value, '<')
                || str_contains($value, '>')) {
                $fail("The {$attribute} field must be plain single-line text.");
            }
        };
    }

    private function rejectUnknownFields(Validator $validator): void
    {
        $this->rejectKeysOutside($validator, $this->all(), ['expected_version', 'state', 'data'], '');
        $data = $this->input('data');

        if (! is_array($data)) {
            return;
        }

        $allowedDataKeys = match ($this->step()) {
            OnboardingStep::Institution => ['institution_name', 'institution_country_code'],
            OnboardingStep::Program => ['department', 'degree', 'major'],
            OnboardingStep::StudyStage => ['year_label', 'term_label'],
            OnboardingStep::Courses => ['courses'],
            OnboardingStep::Goals => ['goals', 'problems'],
            OnboardingStep::FirstSource => ['url', 'title'],
        };
        $this->rejectKeysOutside($validator, $data, $allowedDataKeys, 'data.');

        if ($this->step() !== OnboardingStep::Courses || ! is_array($data['courses'] ?? null)) {
            return;
        }

        foreach ($data['courses'] as $index => $course) {
            if (is_array($course)) {
                $this->rejectKeysOutside($validator, $course, ['title', 'code'], "data.courses.{$index}.");
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $allowed
     */
    private function rejectKeysOutside(Validator $validator, array $values, array $allowed, string $prefix): void
    {
        foreach (array_keys($values) as $key) {
            if (is_string($key) && in_array($key, $allowed, true)) {
                continue;
            }

            $validator->errors()->add($prefix.(string) $key, 'This field is not allowed.');
        }
    }

    private function validateStepCompleteness(Validator $validator): void
    {
        if ($this->input('state') === 'skipped' && array_key_exists('data', $this->all())) {
            $validator->errors()->add('data', 'The data field must be omitted when a step is skipped.');
        }

        if ($this->input('state') !== 'completed') {
            return;
        }

        $data = $this->input('data');

        if (! is_array($data)) {
            return;
        }

        if ($this->step() === OnboardingStep::Program
            && $this->allNull($data, ['department', 'degree', 'major'])) {
            $validator->errors()->add('data', 'Provide at least one program detail or skip this step.');
        }

        if ($this->step() === OnboardingStep::StudyStage
            && $this->allNull($data, ['year_label', 'term_label'])) {
            $validator->errors()->add('data', 'Provide a year or term label, or skip this step.');
        }

        if ($this->step() === OnboardingStep::Goals
            && count(is_array($data['goals'] ?? null) ? $data['goals'] : []) === 0
            && count(is_array($data['problems'] ?? null) ? $data['problems'] : []) === 0) {
            $validator->errors()->add('data', 'Provide at least one goal or problem, or skip this step.');
        }
    }

    private function validateCourseUniqueness(Validator $validator): void
    {
        if ($this->step() !== OnboardingStep::Courses || $this->input('state') !== 'completed') {
            return;
        }

        $courses = $this->input('data.courses');

        if (! is_array($courses)) {
            return;
        }

        $seen = [];

        foreach ($courses as $index => $course) {
            if (! is_array($course) || ! is_string($course['title'] ?? null)) {
                continue;
            }

            $code = is_string($course['code'] ?? null) ? $course['code'] : '';
            $key = mb_strtolower($course['title'])."\0".mb_strtolower($code);

            if (isset($seen[$key])) {
                $validator->errors()->add("data.courses.{$index}", 'Duplicate course drafts are not allowed.');
            }

            $seen[$key] = true;
        }
    }

    private function validateSourceUrl(Validator $validator): void
    {
        if ($this->step() !== OnboardingStep::FirstSource || $this->input('state') !== 'completed') {
            return;
        }

        $url = $this->input('data.url');

        if (! is_string($url)) {
            return;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            $validator->errors()->add('data.url', 'The source URL must be an HTTP(S) URL without credentials.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function normalizeInstitution(array $data): array
    {
        if (is_string($data['institution_country_code'] ?? null)) {
            $data['institution_country_code'] = strtoupper(trim($data['institution_country_code']));
        }

        if (is_string($data['institution_name'] ?? null)) {
            $data['institution_name'] = trim($data['institution_name']);
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $keys
     * @return array<array-key, mixed>
     */
    private function normalizeNullableStrings(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (! is_string($data[$key] ?? null)) {
                continue;
            }

            $value = trim($data[$key]);
            $data[$key] = $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function normalizeCourses(array $data): array
    {
        $courses = $data['courses'] ?? null;

        if (! is_array($courses)) {
            return $data;
        }

        foreach ($courses as $index => $course) {
            if (! is_array($course)) {
                continue;
            }

            if (is_string($course['title'] ?? null)) {
                $course['title'] = trim($course['title']);
            }

            if (is_string($course['code'] ?? null)) {
                $code = trim($course['code']);
                $course['code'] = $code === '' ? null : $code;
            }

            $courses[$index] = $course;
        }

        $data['courses'] = $courses;

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $keys
     * @return array<array-key, mixed>
     */
    private function normalizeStringLists(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            $values = $data[$key] ?? null;

            if (! is_array($values)) {
                continue;
            }

            $data[$key] = array_map(
                static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
                $values,
            );
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function normalizeFirstSource(array $data): array
    {
        foreach (['url', 'title'] as $key) {
            if (! is_string($data[$key] ?? null)) {
                continue;
            }

            $value = trim($data[$key]);
            $data[$key] = $key === 'title' && $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $keys
     */
    private function allNull(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (($data[$key] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }
}
