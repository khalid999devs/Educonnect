<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Auth\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => PasswordRules::newPassword(),
        ];
    }

    /**
     * @return array{email: string, password: string, password_confirmation: string, token: string}
     */
    public function credentials(): array
    {
        return [
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
            'password_confirmation' => (string) $this->validated('password_confirmation'),
            'token' => (string) $this->validated('token'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);
    }
}
