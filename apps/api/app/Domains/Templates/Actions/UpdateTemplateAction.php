<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Content\Exceptions\ContentStateConflict;
use App\Domains\Content\Exceptions\ContentVersionConflict;
use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTemplateAction
{
    /**
     * Edit a draft template's metadata and, when a new body is supplied, append a
     * new immutable version. Versions are never edited or removed — a body change
     * is a fresh version. Only a draft is editable.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Template $template, array $data, int $expectedVersion): Template
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Content curation is not allowed.');
        }

        $category = ToolCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

        return DB::transaction(function () use ($template, $data, $expectedVersion, $category): Template {
            $locked = Template::query()->lockForUpdate()->findOrFail($template->getKey());

            if ($locked->version !== $expectedVersion) {
                throw new ContentVersionConflict;
            }

            if ($locked->state !== GuidanceReviewState::Draft) {
                throw new ContentStateConflict('Only draft content can be edited; return it to draft first.');
            }

            $locked->forceFill([
                'title' => $data['title'],
                'summary' => $data['summary'],
                'integrity_note' => $data['integrity_note'],
                'provenance' => $data['provenance'],
                'tool_category_id' => $category->getKey(),
                'version' => $locked->version + 1,
            ])->save();

            if (isset($data['body'], $data['format'])) {
                $nextNumber = (int) $locked->versions()->max('version_number') + 1;
                $version = new TemplateVersion;
                $version->forceFill([
                    'template_id' => $locked->getKey(),
                    'version_number' => $nextNumber,
                    'format' => $data['format'],
                    'body' => $data['body'],
                    'change_note' => $data['change_note'] ?? null,
                ])->save();
            }

            return $locked->load(['category', 'latestVersion']);
        }, 3);
    }
}
