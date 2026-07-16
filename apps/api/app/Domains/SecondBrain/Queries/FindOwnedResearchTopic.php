<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedResearchTopic
{
    public function execute(
        User $user,
        string $publicId,
        bool $lockForUpdate = false,
        bool $withSources = false,
    ): ResearchTopic {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = ResearchTopic::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId);

            if ($withSources) {
                $query->with([
                    'sources' => static fn ($sources) => $sources
                        ->orderBy('research_topic_sources.created_at')
                        ->orderBy('research_topic_sources.id'),
                ]);
            }

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $topic = $query->first();

            if (! $topic instanceof ResearchTopic) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $topic);

            return $topic;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw BrainPersistenceFailure::fromQueryException($exception, 'research.read');
        }
    }
}
