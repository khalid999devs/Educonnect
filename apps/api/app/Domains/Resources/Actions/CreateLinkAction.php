<?php

declare(strict_types=1);

namespace App\Domains\Resources\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Exceptions\ResourcePersistenceFailure;
use App\Domains\Resources\Exceptions\ResourceStateConflict;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Support\ResourceUrlValidator;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateLinkAction
{
    public function __construct(
        private FindOwnedCourse $courses,
        private ResourceUrlValidator $urls,
    ) {}

    /** @param array{title: string, description: ?string, course_id: ?string, topic_label: ?string, url: string} $data */
    public function execute(User $user, array $data): Resource
    {
        Gate::forUser($user)->authorize('create', Resource::class);
        $url = $this->urls->validate($data['url']);

        try {
            return DB::transaction(function () use ($user, $data, $url): Resource {
                $course = $data['course_id'] === null
                    ? null
                    : $this->courses->execute($user, $data['course_id'], lockForUpdate: true);

                if ($course?->archived_at !== null) {
                    throw new ResourceStateConflict;
                }

                $resource = new Resource;
                $resource->forceFill([
                    'user_id' => $user->getKey(),
                    'course_id' => $course?->getKey(),
                    'kind' => ResourceKind::Link,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'topic_label' => $data['topic_label'],
                    'source_url' => $url,
                    'version' => 1,
                ])->save();

                return $resource->load(['course', 'storedFile']);
            }, 3);
        } catch (QueryException $exception) {
            throw ResourcePersistenceFailure::fromQueryException($exception, 'resource.link.create');
        }
    }
}
