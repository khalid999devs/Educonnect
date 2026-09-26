<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Mentor\Data\MentorListResult;
use App\Domains\Mentor\Enums\MentorRequestStatus;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ListIncomingRequests
{
    public function __construct(private readonly FindOwnMentorProfile $profiles) {}

    /**
     * @param  list<MentorRequestStatus>  $statuses  an empty list returns every status
     * @return MentorListResult<MentorRequest>
     */
    public function execute(User $user, int $perPage, array $statuses = []): MentorListResult
    {
        $profile = $this->profiles->execute($user);

        $values = array_map(static fn (MentorRequestStatus $status): string => $status->value, $statuses);

        try {
            $query = MentorRequest::query()
                ->with(['requester'])
                ->select(['mentor_requests.*', 'mentor_requests.created_at as cursor_created_at_desc'])
                ->where('mentor_profile_id', $profile->getKey());

            if ($values !== []) {
                $query->whereIn('status', $values);
            }

            $paginator = $query
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            // The open badge count is deliberately unfiltered: it reports pending
            // work waiting on this mentor regardless of how the list is filtered.
            $open = DB::table('mentor_requests')
                ->where('mentor_profile_id', $profile->getKey())
                ->where('status', 'open')
                ->count();

            return new MentorListResult($paginator, ['open' => $open]);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.request.incoming');
        }
    }
}
