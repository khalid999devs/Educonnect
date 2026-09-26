<?php

declare(strict_types=1);

namespace App\Domains\Tools\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Tools\Enums\ToolReviewState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CreateToolAction
{
    /**
     * Create a tool as a draft. Content is complete on creation (so a draft is
     * always publishable); the lifecycle is advanced separately.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Tool
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        $tool = new Tool;
        $tool->forceFill([
            ...$this->contentAttributes($data),
            'tool_category_id' => $category->getKey(),
            'state' => ToolReviewState::Draft->value,
            'version' => 1,
        ])->save();

        return $tool->load('category');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contentAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'purpose' => $data['purpose'],
            'selection_reason' => $data['selection_reason'],
            'use_cases' => array_values((array) $data['use_cases']),
            'usage_guidance' => $data['usage_guidance'],
            'limitations' => $data['limitations'],
            'cost_note' => $data['cost_note'],
            'privacy_note' => $data['privacy_note'],
            'external_url' => $data['url'],
            'provenance' => $data['provenance'],
        ];
    }
}
