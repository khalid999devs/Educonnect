<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authenticatedUser = $this->user();
        $routeUser = $this->route('user');
        $hash = $this->route('hash');

        return $authenticatedUser instanceof User
            && $routeUser instanceof User
            && $authenticatedUser->is($routeUser)
            && is_string($hash)
            && hash_equals(hash('sha1', $routeUser->getEmailForVerification()), $hash);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
