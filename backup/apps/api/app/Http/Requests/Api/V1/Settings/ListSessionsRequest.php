<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Settings;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Settings\Concerns\HandlesSettingsInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ListSessionsRequest extends FormRequest
{
    use HandlesSettingsInput;

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

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnknownFields($validator, []);
            },
        ];
    }

    public function currentSessionId(): ?string
    {
        if (! $this->hasSession()) {
            return null;
        }

        $id = $this->session()->getId();

        return $id === '' ? null : $id;
    }
}
