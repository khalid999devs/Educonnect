<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Data\CommunityListResult;
use App\Domains\Community\Enums\MembershipRole;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ListModerationReports
{
    /** @return CommunityListResult<ContentReport> */
    public function execute(User $user, ?string $status, int $perPage): CommunityListResult
    {
        try {
            $query = ContentReport::query()
                ->with(['community', 'post', 'comment', 'reporter'])
                ->select(['content_reports.*', 'content_reports.created_at as cursor_created_at_desc']);

            if (! $user->hasCapability(CapabilityKey::ModerationGlobal)) {
                $scopedCommunityIds = DB::table('community_memberships')
                    ->where('user_id', $user->getKey())
                    ->where('role', MembershipRole::Moderator->value)
                    ->pluck('community_id');

                $query->whereIn('community_id', $scopedCommunityIds);
            }

            if ($status !== null) {
                $query->where('status', $status);
            }

            $paginator = $query
                ->orderBy('cursor_created_at_desc', 'desc')
                ->orderBy('public_id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();

            return new CommunityListResult($paginator, ['returned' => $paginator->count()]);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'moderation.list');
        }
    }
}
