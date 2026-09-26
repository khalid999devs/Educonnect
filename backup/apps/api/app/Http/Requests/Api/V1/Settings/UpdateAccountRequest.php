<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Settings;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Settings\Concerns\HandlesSettingsInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateAccountRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:1', 'max:255', $this->plainSingleLineText()],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnknownFields($validator, ['name']);
            },
        ];
    }

    /**
     * @return array{name: string}
     */
    public function accountData(): array
    {
        return ['name' => (string) $this->validated('name')];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
