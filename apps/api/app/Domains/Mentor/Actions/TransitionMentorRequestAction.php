<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Actions;

use App\Domains\Mentor\Enums\MentorRequestStatus;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Exceptions\MentorStateConflict;
use App\Domains\Mentor\Exceptions\MentorVersionConflict;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Mentor\Queries\FindMentorRequest;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class TransitionMentorRequestAction
{
    public function __construct(private FindMentorRequest $requests) {}

    public function execute(
        User $user,
        string $requestPublicId,
        string $action,
        ?string $responseNote,
        int $expectedVersion,
    ): MentorRequest {
        try {
            return DB::transaction(function () use ($user, $requestPublicId, $action, $responseNote, $expectedVersion): MentorRequest {
                $request = $this->requests->execute($requestPublicId, lockForUpdate: true);

                $target = $this->authorizeAndResolve($user, $request, $action);

                if ($request->version !== $expectedVersion) {
                    throw new MentorVersionConflict;
                }

                $attributes = ['status' => $target->value, 'version' => $request->version + 1];

                if ($action === 'withdraw') {
                    $attributes['responded_at'] = $request->responded_at ?? now();
                } else {
                    $attributes['responded_at'] = $request->responded_at ?? now();
                    if ($responseNote !== null) {
                        $attributes['response_note'] = $responseNote;
                    }
                }

                $request->forceFill($attributes)->save();

                return $request->refresh()->load(['mentorProfile.user', 'requester']);
            }, 3);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.request.update');
        }
    }

    private function authorizeAndResolve(User $user, MentorRequest $request, string $action): MentorRequestStatus
    {
        return match ($action) {
            'withdraw' => $this->transition(
                $user,
                $request,
                'withdraw',
                [MentorRequestStatus::Open],
                MentorRequestStatus::Withdrawn,
            ),
            'accept' => $this->transition(
                $user,
                $request,
                'respond',
                [MentorRequestStatus::Open],
                MentorRequestStatus::Accepted,
            ),
            'decline' => $this->transition(
                $user,
                $request,
                'respond',
                [MentorRequestStatus::Open],
                MentorRequestStatus::Declined,
            ),
            'complete' => $this->transition(
                $user,
                $request,
                'respond',
                [MentorRequestStatus::Accepted],
                MentorRequestStatus::Completed,
            ),
            default => throw new MentorStateConflict('Unsupported mentor request transition.'),
        };
    }

    /**
     * @param  list<MentorRequestStatus>  $allowedFrom
     */
    private function transition(
        User $user,
        MentorRequest $request,
        string $ability,
        array $allowedFrom,
        MentorRequestStatus $to,
    ): MentorRequestStatus {
        if (! Gate::forUser($user)->allows($ability, $request)) {
            throw new AuthorizationException('You cannot change this mentor request.');
        }

        if (! in_array($request->status, $allowedFrom, true)) {
            throw new MentorStateConflict('This mentor request cannot move to the requested state.');
        }

        return $to;
    }
}
