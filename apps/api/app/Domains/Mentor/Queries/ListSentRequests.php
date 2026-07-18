<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Mentor\Data\MentorListResult;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListSentRequests
{
    /** @return MentorListResult<MentorRequest> */
    public function execute(User $user, int $perPage): MentorListResult
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $paginator = MentorRequest::query()
                ->with(['mentorProfile.user'])
                ->select(['mentor_requests.*', 'mentor_requests.created_at as cursor_created_at_desc'])
                ->where('requester_id', $user->getKey())
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            $total = DB::table('mentor_requests')->where('requester_id', $user->getKey())->count();

            return new MentorListResult($paginator, ['total' => $total]);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.request.list');
        }
    }
}
