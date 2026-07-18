<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Templates\Enums\TemplateBadge;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateTemplateAction
{
    /**
     * Create a template as a draft with its first immutable version. Publication
     * later requires at least one version, which is satisfied on creation.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Template
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        return DB::transaction(function () use ($data, $category): Template {
            $template = new Template;
            $template->forceFill([
                'title' => $data['title'],
                'summary' => $data['summary'],
                'integrity_note' => $data['integrity_note'],
                'provenance' => $data['provenance'],
                'badge' => TemplateBadge::ApprovedFree->value,
                'tool_category_id' => $category->getKey(),
                'state' => GuidanceReviewState::Draft->value,
                'version' => 1,
            ])->save();

            $version = new TemplateVersion;
            $version->forceFill([
                'template_id' => $template->getKey(),
                'version_number' => 1,
                'format' => $data['format'],
                'body' => $data['body'],
                'change_note' => $data['change_note'] ?? null,
            ])->save();

            return $template->load(['category', 'latestVersion']);
        }, 3);
    }
}
