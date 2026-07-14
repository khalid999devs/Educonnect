<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Exceptions\AcademicPersistenceFailure;
use App\Domains\Courses\Exceptions\AcademicVersionConflict;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Queries\FindOwnedAcademicTerm;
use App\Domains\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateAcademicTermAction
{
    public function __construct(private FindOwnedAcademicTerm $terms) {}

    /** @param array{label: string, starts_on: ?string, ends_on: ?string} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): AcademicTerm
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): AcademicTerm {
                $term = $this->terms->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $term);

                if ($this->canonical($term) === $data) {
                    return $term;
                }

                if ($term->version !== $expectedVersion) {
                    throw new AcademicVersionConflict;
                }

                $term->forceFill([
                    ...$data,
                    'version' => $term->version + 1,
                ])->save();

                return $term->refresh()->loadCount([
                    'courses as active_courses_count' => static fn ($query) => $query->whereNull('archived_at'),
                    'courses as archived_courses_count' => static fn ($query) => $query->whereNotNull('archived_at'),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            throw AcademicPersistenceFailure::fromQueryException($exception, 'term.update');
        }
    }

    /** @return array{label: string, starts_on: ?string, ends_on: ?string} */
    private function canonical(AcademicTerm $term): array
    {
        return [
            'label' => $term->label,
            'starts_on' => $this->date($term->starts_on),
            'ends_on' => $this->date($term->ends_on),
        ];
    }

    private function date(mixed $date): ?string
    {
        return $date instanceof CarbonInterface ? $date->format('Y-m-d') : null;
    }
}
