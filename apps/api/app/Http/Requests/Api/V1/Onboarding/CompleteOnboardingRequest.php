<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

final class CompleteOnboardingRequest extends FormRequest
{
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
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys($this->all()) as $key) {
                    if ($key !== 'expected_version') {
                        $validator->errors()->add((string) $key, 'This field is not allowed.');
                    }
                }
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

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }
}
