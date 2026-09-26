<?php

declare(strict_types=1);

namespace App\Domains\Intake\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Support\IntakeCursorSort;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ListOwnedIntakeItems
{
    /** @return CursorPaginator<int, IntakeItem> */
    public function execute(User $user, ?IntakeState $state, int $perPage): CursorPaginator
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Intake page size must be between 1 and 50.');
        }

        try {
            $sortDefinition = IntakeCursorSort::for('-created_at');
            $query = IntakeItem::query()
                ->select('intake_items.*')
                ->selectRaw($sortDefinition['expression'].' as '.$sortDefinition['cursor_column'])
                ->where('intake_items.user_id', $user->getKey())
                ->with(['resource']);

            if ($state instanceof IntakeState) {
                $query->where('intake_items.state', $state->value);
            }

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.list');
        }
    }
}
