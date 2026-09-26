<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * The single source of truth for every AI capability's identity: which model
 * policy key it reads, which circuit breaker protects it, what it is called in
 * telemetry, whether it is switched on, and its per-feature bounds.
 *
 * Application code must never hardcode a model name; it asks the feature.
 * Each feature owns its own breaker so a flood of failures in one capability
 * cannot short-circuit the others.
 */
enum AiFeature: string
{
    case IntakeClassification = 'intake_classification';
    case Copilot = 'copilot';
    case PurposeRouting = 'purpose_routing';
    case DocumentChat = 'document_chat';
    case StudySummary = 'study_summary';
    case TopicExplanation = 'topic_explanation';
    case QuickLearn = 'quick_learn';
    case ExamQuestions = 'exam_questions';
    case ToolScenario = 'tool_scenario';

    /** The approved model id from `config('ai.models.*')`. */
    public function model(): string
    {
        return (string) config("ai.models.{$this->value}");
    }

    /** The per-feature circuit-breaker key, isolated from every other feature. */
    public function breakerKey(): string
    {
        return "ai.openai.{$this->value}";
    }

    /**
     * The telemetry event name. Intake and Copilot keep the names they have
     * already emitted in production so historical rows stay queryable.
     */
    public function telemetryName(): string
    {
        return match ($this) {
            self::IntakeClassification => 'intake.classification',
            self::Copilot => 'copilot',
            default => "ai.{$this->value}",
        };
    }

    /** The per-feature kill switch; features are on unless explicitly disabled. */
    public function enabled(): bool
    {
        return (bool) config("ai.features.{$this->value}.enabled", true);
    }

    /**
     * A per-feature numeric bound from `config('ai.features.*')`, floored at 1
     * so a misconfigured zero can never mean "unbounded".
     */
    public function limit(string $key, int $default): int
    {
        return max(1, (int) config("ai.features.{$this->value}.{$key}", $default));
    }
}
