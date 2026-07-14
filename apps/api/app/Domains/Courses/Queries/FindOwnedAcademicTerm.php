<?php

declare(strict_types=1);

namespace App\Domains\Courses\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedAcademicTerm
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): AcademicTerm
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = AcademicTerm::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId)
                ->withCount([
                    'courses as active_courses_count' => static fn (Builder $query): Builder => $query
                        ->whereNull('archived_at'),
                    'courses as archived_courses_count' => static fn (Builder $query): Builder => $query
                        ->whereNotNull('archived_at'),
                ]);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $term = $query->first();

            if (! $term instanceof AcademicTerm) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $term);

            return $term;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw AcademicPersistenceFailure::fromQueryException($exception, 'term.read');
        }
    }
}
