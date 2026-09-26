<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Exceptions\AcademicStateConflict;
use App\Domains\Courses\Exceptions\AcademicVersionConflict;
use App\Domains\Courses\Queries\FindOwnedAcademicTerm;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteAcademicTermAction
{
    public function __construct(private FindOwnedAcademicTerm $terms) {}

    public function execute(User $user, string $publicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $expectedVersion): void {
                $term = $this->terms->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $term);

                if ($term->version !== $expectedVersion) {
                    throw new AcademicVersionConflict;
                }

                if ($term->courses()->exists()) {
                    throw new AcademicStateConflict;
                }

                $term->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'term.delete');
        }
    }
}
