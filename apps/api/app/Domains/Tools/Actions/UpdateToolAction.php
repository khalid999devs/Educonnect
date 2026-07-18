<?php

declare(strict_types=1);

namespace App\Domains\Tools\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Content\Exceptions\ContentStateConflict;
use App\Domains\Content\Exceptions\ContentVersionConflict;
use App\Domains\Tools\Enums\ToolReviewState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateToolAction
{
    /**
     * Edit a tool's content. Only a draft is editable — the database enforces
     * this too, but the app surfaces a clear conflict. Optimistic concurrency via
     * expected_version.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Tool $tool, array $data, int $expectedVersion): Tool
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        return DB::transaction(function () use ($tool, $data, $expectedVersion, $category): Tool {
            $locked = Tool::query()->lockForUpdate()->findOrFail($tool->getKey());

            if ($locked->version !== $expectedVersion) {
                throw new ContentVersionConflict;
            }

            if ($locked->state !== ToolReviewState::Draft) {
                throw new ContentStateConflict('Only draft content can be edited; return it to draft first.');
            }

            $locked->forceFill([
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
                'tool_category_id' => $category->getKey(),
                'version' => $locked->version + 1,
            ])->save();

            return $locked->load('category');
        }, 3);
    }
}
