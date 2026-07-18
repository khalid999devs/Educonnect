<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Community;

use App\Domains\Community\Enums\CommunityVisibility;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SetCommunityVisibilityRequest extends FormRequest
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
            'visibility' => ['required', 'string', Rule::enum(CommunityVisibility::class)],
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => $this->reasonRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['visibility', 'expected_version', 'reason']);
        });
    }

    public function visibility(): CommunityVisibility
    {
        return CommunityVisibility::from((string) $this->input('visibility'));
    }

    public function expectedVersion(): int
    {
        return (int) $this->input('expected_version');
    }
}
