<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Domains\Users\Models\User;
use Closure;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

final class ShowDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'max:64', $this->ianaTimezone()],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! is_string($key) || $key !== 'timezone') {
                    $validator->errors()->add((string) $key, 'This field is not allowed.');
                }
            }
        }];
    }

    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }

    public function timezone(): string
    {
        return (string) $this->validated('timezone');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('timezone'))) {
            $this->merge(['timezone' => trim((string) $this->input('timezone'))]);
        }
    }

    private function ianaTimezone(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! in_array($value, DateTimeZone::listIdentifiers(), true)) {
                $fail("The {$attribute} field must be a supported IANA timezone.");
            }
        };
    }
}
