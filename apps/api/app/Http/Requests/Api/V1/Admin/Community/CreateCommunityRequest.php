<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Community;

use App\Http\Requests\Api\V1\Admin\Community\Concerns\HandlesCommunityAdminContent;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateCommunityRequest extends FormRequest
{
    use HandlesCommunityAdminContent;
    use InteractsWithAdmin;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->communityContentRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, $this->communityContentFields());
        });
    }

    /** @return array<string, mixed> */
    public function contentData(): array
    {
        return $this->validated();
    }
}
