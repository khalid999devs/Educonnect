<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Copilot;

use App\Domains\Users\Models\User;
use Closure;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class CopilotMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxCharacters = max(1, (int) config('ai.copilot.max_message_characters'));
        $maxTurns = max(0, (int) config('ai.copilot.max_history_turns'));

        return [
            'message' => ['required', 'string', 'min:1', "max:{$maxCharacters}"],
            'timezone' => ['nullable', 'string', 'max:64', $this->ianaTimezone()],
            'history' => ['nullable', 'array', "max:{$maxTurns}", 'list'],
            'history.*' => ['array'],
            'history.*.role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'min:1', "max:{$maxCharacters}"],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['message', 'timezone', 'history'], true)) {
                    $validator->errors()->add((string) $key, 'This field is not allowed.');
                }
            }

            $history = $this->input('history');

            if (! is_array($history)) {
                return;
            }

            foreach ($history as $index => $turn) {
                if (! is_array($turn)) {
                    continue;
                }

                foreach (array_keys($turn) as $key) {
                    if (! in_array($key, ['role', 'content'], true)) {
                        $validator->errors()->add(
                            'history.'.(string) $index.'.'.(string) $key,
                            'This field is not allowed.',
                        );
                    }
                }
            }
        }];
    }

    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }

    public function message(): string
    {
        return (string) $this->validated('message');
    }

    public function timezone(): string
    {
        $timezone = $this->validated('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    /** @return list<array{role: string, content: string}> */
    public function history(): array
    {
        $history = $this->validated('history');

        if (! is_array($history)) {
            return [];
        }

        $turns = [];

        foreach ($history as $turn) {
            if (! is_array($turn)) {
                continue;
            }

            $role = $turn['role'] ?? null;
            $content = $turn['content'] ?? null;

            if (is_string($role) && is_string($content)) {
                $turns[] = ['role' => $role, 'content' => $content];
            }
        }

        return $turns;
    }

    private function ianaTimezone(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! in_array($value, DateTimeZone::listIdentifiers(), true)) {
                $fail("The {$attribute} field must be a supported IANA timezone.");
            }
        };
    }
}
