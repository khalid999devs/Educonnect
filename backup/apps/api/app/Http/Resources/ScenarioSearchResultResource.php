<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Tools\AI\ScenarioRankingSchemaV1;
use App\Domains\Tools\Models\Tool;
use App\Support\Ai\BoundedText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ranked tool: the exact ToolResource payload the catalog already returns,
 * plus the one-line reason this tool matched the scenario.
 *
 * Reusing ToolResource is deliberate - a scenario result and a catalog row must
 * never drift into two shapes of the same tool. The reason is re-bounded here
 * rather than trusted: it reaches this point from a schema-validated AI ranking
 * or a deterministic ranker, and re-bounding costs nothing while removing the
 * last path by which model-derived text could reach a renderer unchecked.
 *
 * @mixin Tool
 */
final class ScenarioSearchResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reason = $this->getAttribute('match_reason');

        return [
            ...ToolResource::make($this->resource)->toArray($request),
            'match_reason' => BoundedText::titleText(
                is_string($reason) ? $reason : '',
                ScenarioRankingSchemaV1::MAX_REASON_CHARACTERS,
                'Review whether this tool fits your scenario.',
            ),
        ];
    }
}
