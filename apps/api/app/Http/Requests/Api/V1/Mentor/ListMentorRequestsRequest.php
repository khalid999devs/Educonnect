<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Mentor;

use App\Domains\Mentor\Enums\MentorRequestStatus;
use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Mentor\Concerns\HandlesMentorInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ListMentorRequestsRequest extends FormRequest
{
    use HandlesMentorInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'min:1', 'max:120', $this->knownStatusList()],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['status', 'per_page', 'cursor']);
            $this->validateCursor($validator, '-created_at');
        }];
    }

    /**
     * The requested status filter as a de-duplicated list. An empty list means
     * "no filter" and every status is returned.
     *
     * @return list<MentorRequestStatus>
     */
    public function statuses(): array
    {
        $value = $this->validated('status');

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $statuses = [];

        foreach (explode(',', $value) as $candidate) {
            $status = MentorRequestStatus::tryFrom(trim($candidate));

            if ($status instanceof MentorRequestStatus && ! in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    protected function prepareForValidation(): void
    {
        foreach (['status', 'cursor'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }

    /**
     * Accepts a comma-separated list of mentor request statuses. Every entry must
     * be a known status; an unknown value fails validation rather than being
     * silently ignored, so a typo can never widen the result set.
     */
    private function knownStatusList(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $candidates = explode(',', $value);

            if (count($candidates) > count(MentorRequestStatus::cases())) {
                $fail("The {$attribute} field has too many values.");

                return;
            }

            foreach ($candidates as $candidate) {
                if (! MentorRequestStatus::tryFrom(trim($candidate)) instanceof MentorRequestStatus) {
                    $fail("The {$attribute} field must be a comma-separated list of known statuses.");

                    return;
                }
            }
        };
    }
}
