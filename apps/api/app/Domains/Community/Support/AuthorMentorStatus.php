<?php

declare(strict_types=1);

namespace App\Domains\Community\Support;

use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Mentor\Enums\MentorVerificationState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AuthorMentorStatus
{
    /**
     * Hydrate the transient `author_is_verified_mentor` attribute onto a set of
     * authored records, using a single lookup against the mentor profiles table.
     * The Community domain deliberately references the table by name rather than
     * importing the Mentor model, to keep the domains decoupled.
     *
     * @template TRecord of CommunityPost|CommunityComment
     *
     * @param  Collection<int, TRecord>  $records
     */
    public static function hydrate(Collection $records): void
    {
        $authorIds = $records
            ->map(static fn (CommunityPost|CommunityComment $record): int => (int) $record->author_id)
            ->unique()
            ->values()
            ->all();

        if ($authorIds === []) {
            return;
        }

        $verified = self::verifiedUserIds($authorIds);

        foreach ($records as $record) {
            $record->setAttribute(
                'author_is_verified_mentor',
                in_array((int) $record->author_id, $verified, true),
            );
        }
    }

    /**
     * Resolve which of the given user ids hold a verified mentor profile. The
     * mentor_profiles table is referenced by name on purpose so the Community
     * domain never imports a Mentor model.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public static function verifiedUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var list<int> $verified */
        $verified = DB::table('mentor_profiles')
            ->whereIn('user_id', $userIds)
            ->where('verification_state', MentorVerificationState::Verified->value)
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $verified;
    }
}
