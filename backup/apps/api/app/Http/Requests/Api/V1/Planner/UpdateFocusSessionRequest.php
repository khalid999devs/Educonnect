<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

final class UpdateFocusSessionRequest extends StoreFocusSessionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            ...parent::rules(),
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['expected_version', ...parent::allowedFields()];
    }
}
