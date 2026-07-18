<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Mentor\Data\MentorListResult;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Models\MentorRequest;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ListIncomingRequests
{
    public function __construct(private readonly FindOwnMentorProfile $profiles) {}

    /** @return MentorListResult<MentorRequest> */
    public function execute(User $user, int $perPage): MentorListResult
    {
        $profile = $this->profiles->execute($user);

        try {
            $paginator = MentorRequest::query()
                ->with(['requester'])
                ->select(['mentor_requests.*', 'mentor_requests.created_at as cursor_created_at_desc'])
                ->where('mentor_profile_id', $profile->getKey())
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

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
