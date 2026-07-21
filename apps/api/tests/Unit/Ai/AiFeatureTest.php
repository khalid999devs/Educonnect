<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Support\Ai\AiFeature;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AiFeatureTest extends TestCase
{
    public function test_every_feature_has_a_configured_model(): void
    {
        foreach (AiFeature::cases() as $feature) {
            self::assertNotSame(
                '',
                $feature->model(),
                "AiFeature::{$feature->name} has no entry in config('ai.models').",
            );
        }
    }

    public function test_no_model_name_is_hardcoded_in_application_code(): void
    {
        $feature = AiFeature::ExamQuestions;
        config()->set('ai.models.exam_questions', 'model-from-config');

        self::assertSame('model-from-config', $feature->model());
    }

    #[DataProvider('breakerKeyProvider')]
    public function test_breaker_keys_are_namespaced_per_feature(AiFeature $feature, string $expected): void
    {
        self::assertSame($expected, $feature->breakerKey());
    }

    /** @return array<string, array{AiFeature, string}> */
    public static function breakerKeyProvider(): array
    {
        return [
            'purpose routing' => [AiFeature::PurposeRouting, 'ai.openai.purpose_routing'],
            'intake' => [AiFeature::IntakeClassification, 'ai.openai.intake_classification'],
            'copilot' => [AiFeature::Copilot, 'ai.openai.copilot'],
            'document chat' => [AiFeature::DocumentChat, 'ai.openai.document_chat'],
            'exam questions' => [AiFeature::ExamQuestions, 'ai.openai.exam_questions'],
            'tool scenario' => [AiFeature::ToolScenario, 'ai.openai.tool_scenario'],
        ];
    }

    public function test_every_breaker_key_is_unique(): void
    {
        $keys = array_map(
            static fn (AiFeature $feature): string => $feature->breakerKey(),
            AiFeature::cases(),
        );

        self::assertSame($keys, array_unique($keys), 'Two features share a circuit-breaker key.');
    }

    public function test_telemetry_names_preserve_the_names_already_emitted_in_production(): void
    {
        self::assertSame('intake.classification', AiFeature::IntakeClassification->telemetryName());
        self::assertSame('copilot', AiFeature::Copilot->telemetryName());
    }

    #[DataProvider('telemetryNameProvider')]
    public function test_new_features_are_namespaced_under_ai(AiFeature $feature, string $expected): void
    {
        self::assertSame($expected, $feature->telemetryName());
    }

    /** @return array<string, array{AiFeature, string}> */
    public static function telemetryNameProvider(): array
    {
        return [
            'purpose routing' => [AiFeature::PurposeRouting, 'ai.purpose_routing'],
            'document chat' => [AiFeature::DocumentChat, 'ai.document_chat'],
            'study summary' => [AiFeature::StudySummary, 'ai.study_summary'],
            'topic explanation' => [AiFeature::TopicExplanation, 'ai.topic_explanation'],
            'quick learn' => [AiFeature::QuickLearn, 'ai.quick_learn'],
            'exam questions' => [AiFeature::ExamQuestions, 'ai.exam_questions'],
            'tool scenario' => [AiFeature::ToolScenario, 'ai.tool_scenario'],
        ];
    }

    public function test_features_are_enabled_by_default_and_can_be_switched_off(): void
    {
        self::assertTrue(AiFeature::DocumentChat->enabled());

        config()->set('ai.features.document_chat.enabled', false);

        self::assertFalse(AiFeature::DocumentChat->enabled());
    }

    public function test_a_feature_without_a_features_entry_is_enabled(): void
    {
        // The Copilot deliberately keeps its bounds at ai.copilot.* and has no
        // ai.features entry; it must not read as switched off.
        self::assertTrue(AiFeature::Copilot->enabled());
    }

    public function test_limits_read_config_and_fall_back_to_the_supplied_default(): void
    {
        self::assertSame(24_000, AiFeature::DocumentChat->limit('max_context_characters', 1_000));
        self::assertSame(1_000, AiFeature::DocumentChat->limit('not_configured', 1_000));
    }

    public function test_limits_are_floored_at_one_so_zero_never_means_unbounded(): void
    {
        config()->set('ai.features.exam_questions.max_questions', 0);

        self::assertSame(1, AiFeature::ExamQuestions->limit('max_questions', 20));

        config()->set('ai.features.exam_questions.max_questions', -50);

        self::assertSame(1, AiFeature::ExamQuestions->limit('max_questions', 20));
    }

    public function test_the_document_context_bound_is_not_the_intake_storage_bound(): void
    {
        // Two orders of magnitude apart, deliberately: intake bounds what may
        // be stored, the feature bounds what may be sent to a provider.
        self::assertLessThan(
            (int) config('intake.max_extracted_characters'),
            AiFeature::DocumentChat->limit('max_context_characters', 24_000),
        );
    }
}
