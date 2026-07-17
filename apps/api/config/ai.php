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
];
