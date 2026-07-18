<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Community\Enums\ReportStatus;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Exceptions\CommunityStateConflict;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CreateReportAction
{
    /**
     * @param  'post'|'comment'  $subjectType
     */
    public function execute(
        User $user,
        string $subjectType,
        string $subjectPublicId,
        string $reason,
        ?string $detail,
    ): ContentReport {
        try {
            return DB::transaction(function () use ($user, $subjectType, $subjectPublicId, $reason, $detail): ContentReport {
                [$communityId, $postId, $commentId] = $this->resolveSubject($subjectType, $subjectPublicId);

                $existing = ContentReport::query()
                    ->where('reporter_id', $user->getKey())
                    ->when($postId !== null, static fn ($query) => $query->where('post_id', $postId))
                    ->when($commentId !== null, static fn ($query) => $query->where('comment_id', $commentId))
                    ->exists();

                if ($existing) {
                    throw new CommunityStateConflict('You have already reported this content.');
                }

                $report = new ContentReport;
                $report->forceFill([
                    'reporter_id' => $user->getKey(),
                    'community_id' => $communityId,
                    'post_id' => $postId,
                    'comment_id' => $commentId,
                    'reason' => $reason,
                    'detail' => $detail,
                    'status' => ReportStatus::Open->value,
                    'version' => 1,
                ])->save();

                return $report;
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'report.create');
        }
    }

    /**
     * @param  'post'|'comment'  $subjectType
     * @return array{0: int, 1: int|null, 2: int|null}
     */
    private function resolveSubject(string $subjectType, string $subjectPublicId): array
    {
        if ($subjectType === 'post') {
            $post = CommunityPost::query()->where('public_id', $subjectPublicId)->first();

            if (! $post instanceof CommunityPost) {
                throw new NotFoundHttpException;
            }

            return [(int) $post->community_id, (int) $post->getKey(), null];
        }

        $comment = CommunityComment::query()->where('public_id', $subjectPublicId)->first();

        if (! $comment instanceof CommunityComment) {
            throw new NotFoundHttpException;
        }

        $communityId = CommunityPost::query()->whereKey($comment->post_id)->value('community_id');

        return [(int) $communityId, null, (int) $comment->getKey()];
    }
}
