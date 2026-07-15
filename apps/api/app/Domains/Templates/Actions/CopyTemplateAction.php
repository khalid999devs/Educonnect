<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Templates\Enums\TemplateCopyDestination;
use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Exceptions\TemplateStateConflict;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Templates\Queries\FindPublishedTemplate;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CopyTemplateAction
{
    public function __construct(
        private FindPublishedTemplate $templates,
        private FindOwnedCourse $courses,
    ) {}

    /**
     * Idempotently copies the latest published template version to the
     * requested destination; an existing active copy for the same target is
     * returned unchanged instead of duplicating it.
     */
    public function execute(
        User $user,
        string $publicId,
        TemplateCopyDestination $destination,
        ?string $coursePublicId,
    ): UserTemplateCopy {
        try {
            return DB::transaction(function () use ($user, $publicId, $destination, $coursePublicId): UserTemplateCopy {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $template = $this->templates->execute($user, $publicId, lockForUpdate: true);

                $course = null;

                if ($destination === TemplateCopyDestination::Course) {
                    $course = $this->courses->execute($user, (string) $coursePublicId, lockForUpdate: true);

                    if ($course->archived_at !== null) {
                        throw new TemplateStateConflict;
                    }
                }

                Gate::forUser($user)->authorize('create', UserTemplateCopy::class);

                $courseKey = $course?->getKey();
                $existing = UserTemplateCopy::query()
                    ->where('user_id', $user->getKey())
                    ->where('template_id', $template->getKey())
                    ->where('destination', $destination->value)
                    ->when(
                        $courseKey === null,
                        static fn ($copies) => $copies->whereNull('course_id'),
                        static fn ($copies) => $copies->where('course_id', $courseKey),
                    )
                    ->whereNull('archived_at')
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof UserTemplateCopy) {
                    Gate::forUser($user)->authorize('view', $existing);

                    return $existing->load(['template', 'templateVersion', 'course']);
                }

                $source = $template->getRelation('latestVersion');

                if (! $source instanceof TemplateVersion) {
                    throw new TemplateStateConflict;
                }

                $copy = new UserTemplateCopy;
                $copy->forceFill([
                    'user_id' => $user->getKey(),
                    'template_id' => $template->getKey(),
                    'template_version_id' => $source->getKey(),
                    'destination' => $destination->value,
                    'course_id' => $course?->getKey(),
                    'title' => $template->title,
                    'format' => $source->getAttribute('format'),
                    'body' => $source->getAttribute('body'),
                    'version' => 1,
                ])->save();

                return $copy->refresh()->load(['template', 'templateVersion', 'course']);
            }, 3);
        } catch (QueryException $exception) {
            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.copy.create');
        }
    }
}
