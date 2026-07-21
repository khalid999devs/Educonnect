<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Mentor\Data\MentorListResult;
use App\Domains\Mentor\Enums\MentorRequestStatus;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListSentRequests
{
    /**
     * @param  list<MentorRequestStatus>  $statuses  an empty list returns every status
     * @return MentorListResult<MentorRequest>
     */
    public function execute(User $user, int $perPage, array $statuses = []): MentorListResult
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        $values = array_map(static fn (MentorRequestStatus $status): string => $status->value, $statuses);

        try {
            $query = MentorRequest::query()
                ->with(['mentorProfile.user'])
                ->select(['mentor_requests.*', 'mentor_requests.created_at as cursor_created_at_desc'])
                ->where('requester_id', $user->getKey());

            $total = DB::table('mentor_requests')->where('requester_id', $user->getKey());

            if ($values !== []) {
                $query->whereIn('status', $values);
                // The summary count mirrors the filter, so a caller can read
                // "does this student have a mentor connection?" from the meta
                // alone without paging through any rows.
                $total->whereIn('status', $values);
            }

            $paginator = $query
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            return new MentorListResult($paginator, ['total' => $total->count()]);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.request.list');
        }
    }
}
