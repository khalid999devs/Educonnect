<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SecondBrain;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\SecondBrain\Concerns\HandlesBrainInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncKnowledgeCollectionsRequest extends FormRequest
{
    use HandlesBrainInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'collection_ids' => ['present', 'array', 'max:20'],
            'collection_ids.*' => [
                'required',
                'string',
                'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D',
            ],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, ['collection_ids'])];
    }

    /** @return list<string> */
    public function collectionIds(): array
    {
        $ids = $this->validated('collection_ids');

        return is_array($ids) ? array_values(array_map(strval(...), $ids)) : [];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('collection_ids');

        if (is_array($ids)) {
            $this->merge([
                'collection_ids' => array_values(array_map(
                    fn (mixed $id): mixed => is_string($id) ? trim($id) : $id,
                    $ids,
                )),
            ]);
        }
    }
}
