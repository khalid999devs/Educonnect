<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Mentor\Data\MentorListResult;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Models\MentorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ListMentorProfilesForAdmin
{
    /**
     * Every mentor profile for curation — unlike the student directory this
     * includes unverified profiles and those not accepting requests. Newest
     * first, filterable by verification state. Authorization is enforced at the
     * route (mentors.curate).
     *
     * @return MentorListResult<MentorProfile>
     */
    public function execute(?string $search, ?string $verificationState, int $perPage): MentorListResult
    {
        try {
            $query = MentorProfile::query()
                ->with('user')
                ->select(['mentor_profiles.*', 'mentor_profiles.created_at as cursor_created_at_desc']);

            if ($search !== null) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
                $query->where(static function (Builder $matches) use ($like): void {
                    $matches->whereRaw('LOWER(headline) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(bio) LIKE ?', [$like]);
                });
            }

            if ($verificationState !== null) {
                $query->where('verification_state', $verificationState);
            }

            $paginator = $query
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            $total = DB::table('mentor_profiles')->count();

            return new MentorListResult($paginator, ['total' => $total]);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.admin-list');
        }
    }
}
