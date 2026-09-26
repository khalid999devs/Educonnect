<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

use App\Domains\Intake\Contracts\PurposeRouter;
use App\Support\Ai\AiFeature;
use Illuminate\Contracts\Foundation\Application;

/**
 * Selects the approved router chain for purpose routing, mirroring
 * ClassificationPolicy: the configured primary first, then the deterministic
 * rule-based router, so a failing, disabled, or invalid provider never leaves a
 * captured item without a pre-selected purpose.
 *
 * When no provider key is configured the container binds the rule-based router
 * as the primary too; the duplicate is collapsed here so the chain is never
 * walked twice.
 */
final readonly class PurposeRouterPolicy
{
    public function __construct(private Application $app) {}

    /** @return non-empty-list<PurposeRouter> */
    public function routers(): array
    {
        $primary = $this->app->make(PurposeRouter::class);
        $fallback = $this->fallback();

        if ($primary->name() === $fallback->name()) {
            return [$fallback];
        }

        return [$primary, $fallback];
    }

    public function fallback(): RulePurposeRouter
    {
        return $this->app->make(RulePurposeRouter::class);
    }

    /**
     * Attempts beyond the first are only worth making for a schema rejection;
     * a provider fault abandons the router immediately.
     */
    public function maxOutputRetries(): int
    {
        return max(0, AiFeature::PurposeRouting->limit('max_output_retries', 1));
    }

    public function maxInputCharacters(): int
    {
        return AiFeature::PurposeRouting->limit('max_input_characters', 8_000);
    }
}
