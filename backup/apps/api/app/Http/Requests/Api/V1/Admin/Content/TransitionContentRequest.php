<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Content;

use App\Domains\Content\Enums\ContentTransition;
use App\Http\Requests\Api\V1\Admin\Concerns\InteractsWithAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared by every catalog type's lifecycle endpoint - the transition, the
 * expected version for optimistic concurrency, and the audited reason are
 * identical across tools, prompts, and workflows.
 */
final class TransitionContentRequest extends FormRequest
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
            'transition' => ['required', 'string', Rule::enum(ContentTransition::class)],
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => $this->reasonRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['transition', 'expected_version', 'reason']);
        });
    }

    public function transition(): ContentTransition
    {
        return ContentTransition::from((string) $this->input('transition'));
    }

    public function expectedVersion(): int
    {
        return (int) $this->input('expected_version');
    }
}
