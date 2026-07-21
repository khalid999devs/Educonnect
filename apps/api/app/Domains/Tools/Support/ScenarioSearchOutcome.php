<?php

declare(strict_types=1);

namespace App\Domains\Tools\Support;

use App\Domains\Tools\Models\Tool;

/**
 * The result of one scenario search: the tools in the order they should render,
 * each already carrying its `match_reason` attribute, plus the provenance the
 * response must report.
 *
 * `aiRanked` is false whenever the ordering came from the deterministic ranker -
 * because the feature is switched off, no provider is configured, the breaker is
 * open, or the provider failed. The client uses it to caption the result set
 * honestly instead of implying an AI ranking that never happened.
 */
final readonly class ScenarioSearchOutcome
{
    /** @param list<Tool> $tools */
    public function __construct(
        public array $tools,
        public string $provider,
        public string $model,
        public bool $aiRanked,
        public bool $cached,
    ) {}
}
