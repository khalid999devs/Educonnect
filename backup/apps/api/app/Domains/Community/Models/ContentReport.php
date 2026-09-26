<?php

declare(strict_types=1);

namespace App\Domains\Community\Models;

use App\Domains\Community\Enums\ReportReason;
use App\Domains\Community\Enums\ReportStatus;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\ContentReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $reporter_id
 * @property int $community_id
 * @property int|null $post_id
 * @property int|null $comment_id
 * @property ReportReason $reason
 * @property string|null $detail
 * @property ReportStatus $status
 * @property string|null $resolution_note
 * @property int|null $handled_by_id
 * @property int $version
 */
final class ContentReport extends Model
{
    /** @use HasFactory<ContentReportFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $table = 'content_reports';

    protected $guarded = ['id', 'public_id', 'reporter_id', 'community_id', 'version'];

    protected $hidden = ['id', 'reporter_id', 'community_id', 'post_id', 'comment_id', 'handled_by_id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<CommunityPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    /** @return BelongsTo<CommunityComment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'comment_id');
    }

    protected static function newFactory(): ContentReportFactory
    {
        return ContentReportFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'version' => 'integer',
            'handled_at' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
        ];
    }
}
