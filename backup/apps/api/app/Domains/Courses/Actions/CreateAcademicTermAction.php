<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateAcademicTermAction
{
    /** @param array{label: string, starts_on: ?string, ends_on: ?string} $data */
    public function execute(User $user, array $data): AcademicTerm
    {
        Gate::forUser($user)->authorize('create', AcademicTerm::class);

        try {
            return DB::transaction(function () use ($user, $data): AcademicTerm {
                $term = new AcademicTerm;
                $term->forceFill([
                    'user_id' => $user->getKey(),
                    'label' => $data['label'],
                    'starts_on' => $data['starts_on'],
                    'ends_on' => $data['ends_on'],
                    'version' => 1,
                ])->save();

                return $term->loadCount([
                    'courses as active_courses_count' => static fn ($query) => $query->whereNull('archived_at'),
                    'courses as archived_courses_count' => static fn ($query) => $query->whereNotNull('archived_at'),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'term.create');
        }
    }
}
