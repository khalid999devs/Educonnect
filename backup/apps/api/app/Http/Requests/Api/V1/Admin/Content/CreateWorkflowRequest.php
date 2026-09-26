<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content;

use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use App\Http\Requests\Api\V1\Admin\Content\Concerns\HandlesWorkflowContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateWorkflowRequest extends FormRequest
{
    use HandlesWorkflowContent;
    use InteractsWithAdmin;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->workflowContentRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields(
                $validator,
                ['category_slug', 'title', 'goal', 'expected_outcome', 'integrity_note', 'provenance', 'steps'],
            );
        });
    }

    /** @return array<string, mixed> */
    public function contentData(): array
    {
        return $this->validated();
    }
}
