<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Mentor\Data\MentorListResult;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ListMentorProfiles
{
    /** @return MentorListResult<MentorProfile> */
    public function execute(User $user, ?string $search, ?string $expertise, int $perPage): MentorListResult
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = MentorProfile::query()
                ->with('user')
                ->select(['mentor_profiles.*', 'mentor_profiles.created_at as cursor_created_at_desc'])
                ->where('is_accepting_requests', true);

            if ($search !== null) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
                $query->where(static function ($matches) use ($like): void {
                    $matches->whereRaw('LOWER(headline) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(bio) LIKE ?', [$like]);
                });
            }

            if ($expertise !== null) {
                $query->whereRaw(
                    'EXISTS (SELECT 1 FROM jsonb_array_elements_text(expertise) AS tag WHERE LOWER(tag) = ?)',
                    [mb_strtolower($expertise)],
                );
            }

            $paginator = $query
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            $total = DB::table('mentor_profiles')->where('is_accepting_requests', true)->count();

            return new MentorListResult($paginator, ['total' => $total]);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.list');
        }
    }
}
