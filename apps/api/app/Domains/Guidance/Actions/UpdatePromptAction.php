<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Content\Exceptions\ContentStateConflict;
use App\Domains\Content\Exceptions\ContentVersionConflict;
use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePromptAction
{
    /**
     * Edit a prompt template's content. Only a draft is editable — the database
     * enforces this too, but the app surfaces a clear conflict. Related tools are
     * re-synced from their public ids. Optimistic concurrency via expected_version.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, PromptTemplate $prompt, array $data, int $expectedVersion): PromptTemplate
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        return DB::transaction(function () use ($prompt, $data, $expectedVersion, $category): PromptTemplate {
            $locked = PromptTemplate::query()->lockForUpdate()->findOrFail($prompt->getKey());

            if ($locked->version !== $expectedVersion) {
                throw new ContentVersionConflict;
            }

            if ($locked->state !== GuidanceReviewState::Draft) {
                throw new ContentStateConflict('Only draft content can be edited; return it to draft first.');
            }

            $locked->forceFill([
                'title' => $data['title'],
                'purpose' => $data['purpose'],
                'template_body' => $data['template_body'],
                'placeholders' => array_values((array) $data['placeholders']),
                'expected_output' => $data['expected_output'],
                'integrity_note' => $data['integrity_note'],
                'provenance' => $data['provenance'],
                'tool_category_id' => $category->getKey(),
                'version' => $locked->version + 1,
            ])->save();

            $locked->relatedTools()->sync($this->relatedToolIds($data));

            return $locked->load(['category', 'relatedTools']);
        }, 3);
    }

    /**
     * Resolve the submitted tool public ids to their primary keys, preserving
     * only ids that exist.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function relatedToolIds(array $data): array
    {
        $publicIds = array_values(array_filter((array) ($data['related_tools'] ?? []), is_string(...)));

        if ($publicIds === []) {
            return [];
        }

        return Tool::query()->whereIn('public_id', $publicIds)->pluck('id')->all();
    }
}
