<?php

declare(strict_types=1);

namespace Tests\Feature\Resilience;

use App\Support\Ai\AiFeature;
use App\Support\Ai\OpenAiClient;
use App\Support\Exceptions\CircuitBreakerOpen;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The breaker is keyed per AiFeature, and that is now the only mechanism:
 * the feature is a required argument, so no caller can fall back to a shared
 * global key. Before this, one global key meant a flood of failing
 * exam-question generations also silenced the Copilot, document chat, and
 * intake classification. These tests pin the isolation.
 */
final class PerFeatureCircuitBreakerTest extends TestCase
{
    /** @var list<array{role: string, content: string}> */
    private const MESSAGES = [['role' => 'user', 'content' => 'hi']];

    private const MODEL = 'test-model';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai.openai.api_key' => 'sk-test-key',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.circuit_breaker.enabled' => true,
            'ai.circuit_breaker.failure_threshold' => 3,
            'ai.circuit_breaker.cooldown_seconds' => 60,
        ]);
    }

    public function test_failures_on_one_feature_leave_every_other_feature_available(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $client = app(OpenAiClient::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->assertUpstreamFailure($client, AiFeature::ExamQuestions);
        }

        // Exam questions are now short-circuited with no further upstream call.
        try {
            $client->chat(self::MODEL, self::MESSAGES, AiFeature::ExamQuestions);
            self::fail('Expected the open exam-questions breaker to short-circuit.');
        } catch (CircuitBreakerOpen) {
            // expected
        }

        // The Copilot and document chat are untouched and still reach the provider.
        $this->assertUpstreamFailure($client, AiFeature::Copilot);
        $this->assertUpstreamFailure($client, AiFeature::DocumentChat);

        // 3 exam-question attempts + 1 copilot + 1 document chat. The
        // short-circuited fifth call sent nothing.
        Http::assertSentCount(5);
    }

    public function test_copilot_failures_do_not_open_the_intake_classification_breaker(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $client = app(OpenAiClient::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->assertUpstreamFailure($client, AiFeature::Copilot);
        }

        // Intake classification owns its own key and still reaches the provider.
        $this->assertUpstreamFailure($client, AiFeature::IntakeClassification);

        Http::assertSentCount(4);
    }

    public function test_intake_classification_failures_do_not_open_the_copilot_breaker(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $client = app(OpenAiClient::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->assertUpstreamFailure($client, AiFeature::IntakeClassification);
        }

        $this->assertUpstreamFailure($client, AiFeature::Copilot);

        Http::assertSentCount(4);
    }

    public function test_a_feature_breaker_recovers_independently_on_success(): void
    {
        $client = app(OpenAiClient::class);

        // A single sequence, because a later Http::fake() call merges its stub
        // behind the ones already registered instead of replacing them, so the
        // first 500 stub would keep answering every request.
        Http::fake(['*' => Http::sequence()
            ->pushStatus(500)
            ->pushStatus(500)
            ->push(['choices' => [['message' => ['content' => 'resource']]]])
            ->pushStatus(500)
            ->pushStatus(500)]);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->assertUpstreamFailure($client, AiFeature::PurposeRouting);
        }

        self::assertSame(
            'resource',
            $client->chat(self::MODEL, self::MESSAGES, AiFeature::PurposeRouting),
        );

        // The success cleared the accrued failures, so the next two failures
        // alone do not reach the threshold of three and open the breaker: both
        // still reach the provider and surface as upstream failures.
        $this->assertUpstreamFailure($client, AiFeature::PurposeRouting);
        $this->assertUpstreamFailure($client, AiFeature::PurposeRouting);

        Http::assertSentCount(5);
    }

    public function test_chat_with_usage_reports_token_counts_for_cost_accounting(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'a summary']]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 45],
        ])]);

        $completion = app(OpenAiClient::class)->chatWithUsage(
            self::MODEL,
            self::MESSAGES,
            AiFeature::StudySummary,
        );

        self::assertSame('a summary', $completion->content);
        self::assertSame(120, $completion->promptTokens);
        self::assertSame(45, $completion->completionTokens);
        self::assertSame(165, $completion->totalTokens());
    }

    public function test_missing_usage_reports_null_rather_than_a_fabricated_zero(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'a summary']]]])]);

        $completion = app(OpenAiClient::class)->chatWithUsage(self::MODEL, self::MESSAGES, AiFeature::StudySummary);

        self::assertNull($completion->promptTokens);
        self::assertNull($completion->completionTokens);
        self::assertNull($completion->totalTokens());
    }

    private function assertUpstreamFailure(OpenAiClient $client, AiFeature $feature): void
    {
        try {
            $client->chat(self::MODEL, self::MESSAGES, $feature);
            self::fail('Expected the upstream failure to throw.');
        } catch (RuntimeException $exception) {
            self::assertNotInstanceOf(CircuitBreakerOpen::class, $exception);
        }
    }
}
