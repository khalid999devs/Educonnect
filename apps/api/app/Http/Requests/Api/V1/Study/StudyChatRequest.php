<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Study;

use App\Domains\Users\Models\User;
use App\Support\Ai\AiFeature;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class StudyChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxCharacters = AiFeature::DocumentChat->limit('max_message_characters', 1_000);
        $maxTurns = AiFeature::DocumentChat->limit('max_history_turns', 8);

        return [
            'message' => ['required', 'string', 'min:1', "max:{$maxCharacters}", $this->conversationalText()],
            'history' => ['nullable', 'array', "max:{$maxTurns}", 'list'],
            'history.*' => ['array'],
            /*
             * The role whitelist is the prompt-injection boundary of this
             * endpoint and is exactly ['user', 'assistant']. A client-supplied
             * 'system' turn would let a caller append arbitrary instructions to
             * the guardrail prompt the server assembled - the grounding rule,
             * the untrusted-data notice, and the MUST-NOT list - and thereby
             * overwrite them. Rejecting it here means the only system message
             * that can ever reach a provider is the one BuildDocumentChatMessages
             * wrote. Do not widen this list.
             */
            'history.*.role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'min:1', "max:{$maxCharacters}", $this->conversationalText()],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['message', 'history']);
            $this->rejectUnknownHistoryFields($validator);
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

    public function itemPublicId(): string
    {
        return (string) $this->route('item');
    }

    public function message(): string
    {
        return (string) $this->validated('message');
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

    /** @param list<string> $allowed */
    private function rejectUnknownFields(Validator $validator, array $allowed): void
    {
        foreach (array_keys($this->all()) as $key) {
            if (! in_array((string) $key, $allowed, true)) {
                $validator->errors()->add((string) $key, 'This field is not allowed.');
            }
        }
    }

    private function rejectUnknownHistoryFields(Validator $validator): void
    {
        $history = $this->input('history');

        if (! is_array($history)) {
            return;
        }

        foreach ($history as $index => $turn) {
            if (! is_array($turn)) {
                continue;
            }

            foreach (array_keys($turn) as $key) {
                if (! in_array((string) $key, ['role', 'content'], true)) {
                    $validator->errors()->add(
                        'history.'.(string) $index.'.'.(string) $key,
                        'This field is not allowed.',
                    );
                }
            }
        }
    }

    /**
     * A chat turn is deliberately not plainSingleLineText(): a student asking
     * about a document may legitimately paste a quotation containing "<" or
     * ">", and a 422 there would be a worse outcome than a safe answer. The
     * safety property is enforced downstream instead - the turn is declared
     * untrusted data in the prompt and is never echoed back into a renderer
     * unescaped. Newline, tab, and carriage return survive; every other control
     * character is refused, because no legitimate question carries one.
     */
    private function conversationalText(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
                $fail("The {$attribute} field must not contain control characters.");
            }
        };
    }
}
