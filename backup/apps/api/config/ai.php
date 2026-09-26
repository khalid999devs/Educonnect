<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Provider credentials
    |--------------------------------------------------------------------------
    |
    | AI features activate only when a key is configured; every feature keeps
    | a deterministic non-AI fallback, so a blank key degrades gracefully.
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'timeout_seconds' => (int) env('AI_REQUEST_TIMEOUT_SECONDS', 30),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 900),
        // Reasoning models (gpt-5 family) spend the completion budget on hidden
        // reasoning tokens before any visible output. Without a bound they can
        // consume the whole budget and return an empty completion. "minimal"
        // keeps these bounded intake/copilot calls fast and always-answering.
        // Set blank for non-reasoning models that reject the parameter.
        'reasoning_effort' => (string) env('AI_REASONING_EFFORT', 'minimal'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model policy
    |--------------------------------------------------------------------------
    |
    | Approved model per feature. Application code must read these instead of
    | scattering model names (doc 12 AIModelPolicy rule): `AiFeature::model()`
    | is the only reader, so `grep -rn "gpt-" app/` stays at zero hits.
    |
    | Every default is the model already proven in production here, so day one
    | cannot regress. Tiering each job onto a cheaper or stronger model is then
    | an env-only operation with zero code change: routing and scenario ranking
    | want the fastest tier, exam-question generation the strongest.
    |
    */

    'models' => [
        'intake_classification' => (string) env('AI_INTAKE_MODEL', 'gpt-5-mini'),
        'copilot' => (string) env('AI_COPILOT_MODEL', 'gpt-5-mini'),
        'purpose_routing' => (string) env('AI_PURPOSE_ROUTING_MODEL', 'gpt-5-mini'),
        'document_chat' => (string) env('AI_DOCUMENT_CHAT_MODEL', 'gpt-5-mini'),
        'study_summary' => (string) env('AI_STUDY_SUMMARY_MODEL', 'gpt-5-mini'),
        'topic_explanation' => (string) env('AI_TOPIC_EXPLANATION_MODEL', 'gpt-5-mini'),
        'quick_learn' => (string) env('AI_QUICK_LEARN_MODEL', 'gpt-5-mini'),
        'exam_questions' => (string) env('AI_EXAM_QUESTIONS_MODEL', 'gpt-5-mini'),
        'tool_scenario' => (string) env('AI_TOOL_SCENARIO_MODEL', 'gpt-5-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Copilot bounds (deliberately not under 'features')
    |--------------------------------------------------------------------------
    |
    | The Copilot predates the per-feature block below and its bounds stay at
    | `ai.copilot.*`. This divergence is intentional and documented rather than
    | accidental: moving a working, tested surface onto a new key shape buys
    | nothing and a half-done move would leave two conventions in play. Read
    | the rule as: the Copilot reads `ai.copilot.*`; every other capability
    | reads `ai.features.<name>.*` via AiFeature::limit().
    |
    */

    'copilot' => [
        'max_message_characters' => 1000,
        'max_history_turns' => 6,
        'max_context_tasks' => 5,
        'max_context_courses' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-feature kill switches and bounds
    |--------------------------------------------------------------------------
    |
    | Every capability can be switched off independently without a deploy, and
    | carries the bounds that keep a prompt cheap and a response small. Read
    | through AiFeature::enabled() and AiFeature::limit().
    |
    | `max_context_characters` is 24,000 by design. It is NOT
    | `intake.max_extracted_characters` (200,000), which bounds what may be
    | stored, not what may be sent to a provider. Reusing the intake bound
    | would put two orders of magnitude too much text in a single prompt.
    |
    */

    'features' => [
        'purpose_routing' => [
            'enabled' => (bool) env('AI_PURPOSE_ROUTING_ENABLED', true),
            'max_input_characters' => 8_000,
        ],
        'document_chat' => [
            'enabled' => (bool) env('AI_DOCUMENT_CHAT_ENABLED', true),
            'max_context_characters' => 24_000,
            'max_message_characters' => 1_000,
            'max_history_turns' => 8,
        ],
        'study_summary' => [
            'enabled' => (bool) env('AI_STUDY_SUMMARY_ENABLED', true),
            'max_context_characters' => 24_000,
        ],
        'topic_explanation' => [
            'enabled' => (bool) env('AI_TOPIC_EXPLANATION_ENABLED', true),
            'max_context_characters' => 24_000,
        ],
        'quick_learn' => [
            'enabled' => (bool) env('AI_QUICK_LEARN_ENABLED', true),
            'max_context_characters' => 24_000,
        ],
        'exam_questions' => [
            'enabled' => (bool) env('AI_EXAM_QUESTIONS_ENABLED', true),
            'max_context_characters' => 24_000,
            'max_questions' => 20,
        ],
        'tool_scenario' => [
            'enabled' => (bool) env('AI_TOOL_SCENARIO_ENABLED', true),
            'max_scenario_characters' => 600,
            'max_candidates' => 50,
            'cache_ttl_seconds' => 900,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider circuit breaker (Phase 28)
    |--------------------------------------------------------------------------
    |
    | After `failure_threshold` consecutive failures the breaker opens for
    | `cooldown_seconds`; while open, calls short-circuit immediately so a
    | failing provider is not hammered - intake degrades to its deterministic
    | classifier and the Copilot reports itself briefly unavailable.
    |
    | Thresholds are shared, but the breaker STATE is keyed per AiFeature, so a
    | flood of failures in one capability never short-circuits the others.
    |
    */

    'circuit_breaker' => [
        'enabled' => (bool) env('AI_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => max(1, (int) env('AI_CIRCUIT_BREAKER_THRESHOLD', 5)),
        'cooldown_seconds' => max(1, (int) env('AI_CIRCUIT_BREAKER_COOLDOWN_SECONDS', 60)),
    ],
];
