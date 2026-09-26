<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tools;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Tools\Concerns\HandlesToolInput;
use App\Support\Ai\AiFeature;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ScenarioSearchRequest extends FormRequest
{
    use HandlesToolInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scenario' => [
                'required',
                'string',
                'min:3',
                'max:'.AiFeature::ToolScenario->limit('max_scenario_characters', 600),
                $this->scenarioText(),
            ],
            'category' => [
                'nullable',
                'string',
                'min:1',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
            ],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['scenario', 'category']);
        }];
    }

    public function scenario(): string
    {
        return (string) $this->validated('scenario');
    }

    public function category(): ?string
    {
        $value = $this->validated('category');

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['scenario', 'category'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }

    /**
     * The scenario is the one free-text field in this domain that deliberately
     * does NOT use plainSingleLineText(): a student describing a real situation
     * may legitimately type "<" or ">", and rejecting the request would turn a
     * prompt-injection attempt into a 422 rather than a safe answer.
     *
     * The safety property is enforced downstream instead, and more strongly:
     * the scenario is declared untrusted data in the prompt, it is never echoed
     * back in the response, and every rendered string is either a curated
     * catalog field or a match reason bounded by ScenarioRankingSchemaV1.
     * Control characters are still refused - no legitimate scenario carries one.
     */
    private function scenarioText(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
                $fail("The {$attribute} field must not contain control characters.");
            }
        };
    }
}
