<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Domains\Intake\AI\ClassificationRequest;
use App\Domains\Intake\AI\RuleBasedClassificationProvider;
use App\Domains\Intake\Contracts\AIProvider;
use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Jobs\ClassifyIntakeItem;
use App\Domains\Intake\Models\IntakeArtifact;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Telemetry\Models\TelemetryEvent;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class IntakeAiTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telemetry.enabled', true);
    }

    public function test_a_failing_ai_provider_records_a_failure_and_a_fallback(): void
    {
        $user = User::factory()->create();
        $item = IntakeItem::factory()->extracted()->create(['user_id' => $user->getKey()]);
        IntakeArtifact::factory()->create([
            'intake_item_id' => $item->getKey(),
            'kind' => IntakeArtifactKind::ExtractedText->value,
            'text_content' => 'Assignment due 2026-08-15.',
        ]);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function name(): string
            {
                return 'openai';
            }

            public function model(): string
            {
                return 'gpt-5-mini';
            }

            /** @return array<string, mixed> */
            public function classify(ClassificationRequest $request): array
            {
                throw new RuntimeException('provider unavailable');
            }
        });

        $this->app->call([new ClassifyIntakeItem((int) $item->getKey()), 'handle']);

        self::assertSame('awaiting_review', $item->refresh()->state->value);
        self::assertSame('rule_based', $item->classification_provider);

        $aiEvents = TelemetryEvent::query()->where('type', 'ai_call')->get();
        self::assertCount(2, $aiEvents);

        $failure = $aiEvents->firstWhere('outcome', 'failure');
        self::assertNotNull($failure);
        self::assertSame('intake.classification', $failure->getAttribute('name'));
        self::assertSame('openai', $failure->getAttribute('metadata')['provider']);

        $fallback = $aiEvents->firstWhere('outcome', 'fallback');
        self::assertNotNull($fallback);
        self::assertSame('rule_based', $fallback->getAttribute('metadata')['provider']);
    }

    public function test_the_deterministic_path_records_no_ai_call(): void
    {
        // Pin the deterministic classifier so no external provider is attempted
        // (a local .env may configure a real key); no paid AI call happens, so
        // nothing is recorded as an AI call.
        $this->app->instance(
            AIProvider::class,
            $this->app->make(RuleBasedClassificationProvider::class),
        );
        $user = User::factory()->create();
        $item = IntakeItem::factory()->extracted()->create(['user_id' => $user->getKey()]);
        IntakeArtifact::factory()->create([
            'intake_item_id' => $item->getKey(),
            'kind' => IntakeArtifactKind::ExtractedText->value,
            'text_content' => 'Read chapter four. Quiz due 2026-08-18.',
        ]);

        $this->app->call([new ClassifyIntakeItem((int) $item->getKey()), 'handle']);

        self::assertSame('awaiting_review', $item->refresh()->state->value);
        self::assertSame(0, TelemetryEvent::query()->where('type', 'ai_call')->count());
    }
}
