<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreatePromptAction
{
    /**
     * Create a prompt template as a draft. Content is complete on creation (so a
     * draft is always publishable); the lifecycle is advanced separately. Related
     * tools are synced from their public ids — publication then requires at least
     * one, which the database enforces on the publish transition.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): PromptTemplate
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        return DB::transaction(function () use ($data, $category): PromptTemplate {
            $prompt = new PromptTemplate;
            $prompt->forceFill([
                ...$this->contentAttributes($data),
                'tool_category_id' => $category->getKey(),
                'state' => GuidanceReviewState::Draft->value,
                'version' => 1,
            ])->save();

            $prompt->relatedTools()->sync($this->relatedToolIds($data));

            return $prompt->load(['category', 'relatedTools']);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contentAttributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'purpose' => $data['purpose'],
            'template_body' => $data['template_body'],
            'placeholders' => array_values((array) $data['placeholders']),
            'expected_output' => $data['expected_output'],
            'integrity_note' => $data['integrity_note'],
            'provenance' => $data['provenance'],
        ];
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
