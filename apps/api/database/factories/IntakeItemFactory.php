<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Enums\IntakeSourceType;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntakeItem> */
final class IntakeItemFactory extends Factory
{
    protected $model = IntakeItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_type' => IntakeSourceType::Link->value,
            'resource_id' => null,
            'url' => 'https://intake.example.edu/reading-list',
            'context' => 'Week three reading list for my methods course.',
            'state' => IntakeState::UploadedOrLinked->value,
            'failure_code' => null,
            'attempts' => 0,
            'version' => 1,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'state' => IntakeState::Queued->value,
            'queued_at' => now(),
        ]);
    }

    public function extracted(): static
    {
        return $this->state(fn (): array => [
            'state' => IntakeState::Extracted->value,
            'queued_at' => now()->subMinute(),
            'started_at' => now()->subSeconds(30),
            'finished_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function extracting(): static
    {
        return $this->state(fn (): array => [
            'state' => IntakeState::Extracting->value,
            'queued_at' => now()->subMinute(),
            'started_at' => now()->subSeconds(30),
            'attempts' => 1,
        ]);
    }

    public function organizing(): static
    {
        return $this->state(fn (): array => [
            'state' => IntakeState::Organizing->value,
            'queued_at' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subSeconds(30),
            'attempts' => 1,
        ]);
    }

    public function awaitingReview(): static
    {
        return $this->state(fn (): array => [
            'state' => IntakeState::AwaitingReview->value,
            'queued_at' => now()->subMinute(),
            'started_at' => now()->subSeconds(40),
            'finished_at' => now()->subSeconds(20),
            'attempts' => 1,
            'classification_provider' => 'rule_based',
            'classification_model' => 'deterministic-rules-1',
            'classification_schema_version' => 'v1',
            'classification_latency_ms' => 4,
        ]);
    }

    public function failedRetryable(IntakeFailureCode $code = IntakeFailureCode::LinkFetchFailed): static
    {
        return $this->state(fn (): array => [
            'state' => IntakeState::FailedRetryable->value,
            'failure_code' => $code->value,
            'queued_at' => now()->subMinute(),
            'started_at' => now()->subSeconds(30),
            'finished_at' => now(),
            'attempts' => 1,
        ]);
    }
}
