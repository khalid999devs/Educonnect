<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SendEmailVerificationRequest extends FormRequest
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
        return [];
    }

    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new \LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }
}
