<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Progress;

use App\Domains\Progress\Enums\ProgressWindow;
use App\Domains\Users\Models\User;
use Closure;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class ShowProgressRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_FIELDS = ['timezone', 'window'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'max:64', $this->ianaTimezone()],
            'window' => ['sometimes', 'string', Rule::enum(ProgressWindow::class)],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, self::ALLOWED_FIELDS);
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

    public function window(): ProgressWindow
    {
        $window = $this->validated('window');

        return is_string($window)
            ? (ProgressWindow::tryFrom($window) ?? ProgressWindow::Week)
            : ProgressWindow::Week;
    }

    protected function prepareForValidation(): void
    {
        foreach (self::ALLOWED_FIELDS as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    /** @param list<string> $allowed */
    private function rejectUnknownFields(Validator $validator, array $allowed): void
    {
        foreach (array_keys($this->all()) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                $validator->errors()->add((string) $key, 'This field is not allowed.');
            }
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
