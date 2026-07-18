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
    ],

    /*
    |--------------------------------------------------------------------------
    | Model policy
    |--------------------------------------------------------------------------
    |
    | Approved model per feature. Application code must read these instead of
    | scattering model names (doc 12 AIModelPolicy rule).
    |
    */

    'models' => [
        'intake_classification' => (string) env('AI_INTAKE_MODEL', 'gpt-5-mini'),
        'copilot' => (string) env('AI_COPILOT_MODEL', 'gpt-5-mini'),
    ],

    'copilot' => [
        'max_message_characters' => 1000,
        'max_history_turns' => 6,
        'max_context_tasks' => 5,
        'max_context_courses' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider circuit breaker (Phase 28)
    |--------------------------------------------------------------------------
    |
    | After `failure_threshold` consecutive failures the breaker opens for
    | `cooldown_seconds`; while open, calls short-circuit immediately so a
    | failing provider is not hammered — intake degrades to its deterministic
    | classifier and the Copilot reports itself briefly unavailable.
    |
    */

    'circuit_breaker' => [
        'enabled' => (bool) env('AI_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => max(1, (int) env('AI_CIRCUIT_BREAKER_THRESHOLD', 5)),
        'cooldown_seconds' => max(1, (int) env('AI_CIRCUIT_BREAKER_COOLDOWN_SECONDS', 60)),
    ],
];
