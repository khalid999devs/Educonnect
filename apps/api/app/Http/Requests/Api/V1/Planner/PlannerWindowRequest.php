<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

use App\Domains\Users\Models\User;
use App\Http\Requests\Api\V1\Planner\Concerns\HandlesPlannerInput;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

abstract class PlannerWindowRequest extends FormRequest
{
    use HandlesPlannerInput;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'max:64', $this->ianaTimezone()],
            $this->anchorKey() => ['required', 'string', 'max:10', $this->localDate()],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['timezone', $this->anchorKey(), 'limit']);
            $this->validateLocalWindow($validator);
        }];
    }

    public function timezone(): string
    {
        return (string) $this->validated('timezone');
    }

    public function anchor(): string
    {
        return (string) $this->validated($this->anchorKey());
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->localBoundary()->utc();
    }

    public function endsAt(): CarbonImmutable
    {
        $boundary = $this->localBoundary()->modify('+'.$this->windowDays().' days');

        return CarbonImmutable::instance($boundary)->utc();
    }

    public function limit(): int
    {
        return (int) $this->input('limit', 50);
    }

    protected function prepareForValidation(): void
    {
        foreach (['timezone', $this->anchorKey()] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim((string) $this->input($key))]);
            }
        }
    }

    abstract protected function anchorKey(): string;

    abstract protected function windowDays(): int;

    private function localBoundary(): CarbonImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $this->anchor(),
            new DateTimeZone($this->timezone()),
        );

        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $this->anchor()) {
            throw new LogicException('Validated planner date must be available.');
        }

        return CarbonImmutable::instance($date);
    }

    private function validateLocalWindow(Validator $validator): void
    {
        $timezone = $this->input('timezone');
        $anchor = $this->input($this->anchorKey());

        if (! is_string($timezone)
            || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)
            || ! is_string($anchor)) {
            return;
        }

        $boundary = DateTimeImmutable::createFromFormat('!Y-m-d', $anchor, new DateTimeZone($timezone));

        if (! $boundary instanceof DateTimeImmutable
            || $boundary->format('Y-m-d') !== $anchor
            || (int) $boundary->format('Y') < 1) {
            $validator->errors()->add(
                $this->anchorKey(),
                'The selected date does not exist in the requested timezone.',
            );

            return;
        }

        $endYear = (int) $boundary->modify('+'.$this->windowDays().' days')->format('Y');

        if ($endYear > 9999) {
            $validator->errors()->add(
                $this->anchorKey(),
                'The planner window must end within year 9999.',
            );
        }
    }
}
