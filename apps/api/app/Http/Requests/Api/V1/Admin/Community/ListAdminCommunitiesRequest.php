<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Community;

use App\Domains\Community\Enums\CommunityVisibility;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListAdminCommunitiesRequest extends FormRequest
{
    use InteractsWithAdmin;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'visibility' => ['sometimes', 'string', Rule::enum(CommunityVisibility::class)],
            'cursor' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['visibility', 'cursor', 'per_page']);
        });
    }

    public function visibility(): ?string
    {
        $value = $this->input('visibility');

        return is_string($value) ? $value : null;
    }
}
