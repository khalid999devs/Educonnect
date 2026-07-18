<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Authorization\Contracts\ModerationTarget;
use App\Domains\Authorization\Policies\ModerationPolicy;
use App\Domains\Community\Enums\ModerationState;
use App\Domains\Community\Enums\ReportStatus;
use App\Domains\Community\Exceptions\CommunityPersistenceFailure;
use App\Domains\Community\Exceptions\CommunityStateConflict;
use App\Domains\Community\Exceptions\CommunityVersionConflict;
use App\Domains\Community\Models\CommunityComment;
use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Models\ContentReport;
use App\Domains\Community\Queries\FindReport;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class ResolveReportAction
{
    public function __construct(
        private FindReport $reports,
        private ModerationPolicy $moderation,
    ) {}

    public function execute(
        User $user,
        string $reportPublicId,
        string $resolution,
        bool $hideContent,
        ?string $note,
        int $expectedVersion,
    ): ContentReport {
        try {
            return DB::transaction(function () use ($user, $reportPublicId, $resolution, $hideContent, $note, $expectedVersion): ContentReport {
                $report = $this->reports->execute($reportPublicId, lockForUpdate: true);
                $target = $this->lockTarget($report);

                if (! $this->moderation->moderate($user, $target)) {
                    throw new AuthorizationException('You are not assigned to moderate this content.');
                }

                if (in_array($report->status, [ReportStatus::Actioned, ReportStatus::Dismissed], true)) {
                    throw new CommunityStateConflict('This report has already been resolved.');
                }

                if ($report->version !== $expectedVersion) {
                    throw new CommunityVersionConflict;
                }

                if ($resolution === ReportStatus::Actioned->value
                    && $hideContent
                    && $target instanceof Model
                    && $target->getAttribute('moderation_state') !== ModerationState::HiddenByModerator) {
                    $target->forceFill([
                        'moderation_state' => ModerationState::HiddenByModerator->value,
                        'version' => (int) $target->getAttribute('version') + 1,
                    ])->save();
                }

                $report->forceFill([
                    'status' => $resolution,
                    'resolution_note' => $note,
                    'handled_by_id' => $user->getKey(),
                    'handled_at' => now(),
                    'version' => $report->version + 1,
                ])->save();

                return $report->load(['community', 'post', 'comment', 'reporter']);
            }, 3);
        } catch (QueryException $exception) {
            throw CommunityPersistenceFailure::fromQueryException($exception, 'moderation.resolve');
        }
    }

    private function lockTarget(ContentReport $report): ModerationTarget
    {
        if ($report->post_id !== null) {
            $post = CommunityPost::query()->whereKey($report->post_id)->lockForUpdate()->first();

            if ($post instanceof CommunityPost) {
                return $post;
            }
        }

        $comment = CommunityComment::query()->whereKey($report->comment_id)->lockForUpdate()->first();

        if (! $comment instanceof CommunityComment) {
            throw new CommunityStateConflict('The reported content no longer exists.');
        }

        return $comment;
    }
}
