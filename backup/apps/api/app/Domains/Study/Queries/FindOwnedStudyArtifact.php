<?php

declare(strict_types=1);

namespace App\Domains\Study\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Study\Exceptions\StudyPersistenceFailure;
use App\Domains\Study\Models\StudyArtifact;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads one study artifact the caller owns. Another student's artifact is
 * concealed as a 404 rather than reported as a 403, so the endpoint never
 * confirms that a given id exists.
 */
final class FindOwnedStudyArtifact
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): StudyArtifact
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = StudyArtifact::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $artifact = $query->first();

            if (! $artifact instanceof StudyArtifact) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $artifact);

            return $artifact;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw StudyPersistenceFailure::fromQueryException($exception, 'study.artifact.read');
        }
    }
}
