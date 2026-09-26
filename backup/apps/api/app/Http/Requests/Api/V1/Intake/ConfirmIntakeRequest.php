<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Intake;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Intake\Concerns\HandlesIntakeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ConfirmIntakeRequest extends FormRequest
{
    use HandlesIntakeInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decisions' => ['present', 'array', 'max:10'],
            'decisions.*.id' => ['required', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D', 'distinct'],
            'decisions.*.action' => ['required', 'string', Rule::in(['apply', 'dismiss'])],
            'decisions.*.overrides' => ['nullable', 'array'],
            'decisions.*.overrides.title' => ['nullable', 'string', 'min:1', 'max:160', $this->plainSingleLineText()],
            'decisions.*.overrides.description' => ['nullable', 'string', 'min:1', 'max:2000', $this->plainSingleLineText()],
            'decisions.*.overrides.due_at' => ['nullable', 'string', 'date_format:Y-m-d'],
            'decisions.*.overrides.course_id' => ['nullable', 'string', 'regex:/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D'],
            'decisions.*.overrides.url' => ['nullable', 'string', 'max:2048', 'regex:/^https:\/\/[^\s]+$/D'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['decisions']);

            $decisions = $this->input('decisions');

            if (! is_array($decisions)) {
                return;
            }

            foreach ($decisions as $index => $decision) {
                if (! is_array($decision)) {
                    continue;
                }

                foreach (array_keys($decision) as $key) {
                    if (! in_array($key, ['id', 'action', 'overrides'], true)) {
                        $validator->errors()->add("decisions.{$index}.{$key}", 'This field is not allowed.');
                    }
                }

                $overrides = $decision['overrides'] ?? null;

                if (! is_array($overrides)) {
                    continue;
                }

                foreach (array_keys($overrides) as $key) {
                    if (! in_array($key, ['title', 'description', 'due_at', 'course_id', 'url'], true)) {
                        $validator->errors()->add("decisions.{$index}.overrides.{$key}", 'This field is not allowed.');
                    }
                }
            }
        }];
    }

    /** @return list<array{id: string, action: 'apply'|'dismiss', overrides: array<string, mixed>}> */
    public function decisions(): array
    {
        $validated = $this->validated('decisions');
        $decisions = [];

        if (! is_array($validated)) {
            return [];
        }

        foreach ($validated as $decision) {
            if (! is_array($decision)) {
                continue;
            }

            $overrides = $decision['overrides'] ?? [];
            $decisions[] = [
                'id' => (string) $decision['id'],
                'action' => $decision['action'] === 'apply' ? 'apply' : 'dismiss',
                'overrides' => is_array($overrides) ? $overrides : [],
            ];
        }

        return $decisions;
    }
}
