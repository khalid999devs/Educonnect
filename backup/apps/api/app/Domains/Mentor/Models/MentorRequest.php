<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Models;

use App\Domains\Courses\Models\Course;
use App\Domains\Mentor\Enums\MentorRequestStatus;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\MentorRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $requester_id
 * @property int $mentor_profile_id
 * @property string $subject
 * @property string $message
 * @property int|null $context_course_id
 * @property MentorRequestStatus $status
 * @property string|null $response_note
 * @property int $version
 */
final class MentorRequest extends Model
{
    /** @use HasFactory<MentorRequestFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'requester_id', 'mentor_profile_id', 'version'];

    protected $hidden = ['id', 'requester_id', 'mentor_profile_id', 'context_course_id'];

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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<MentorProfile, $this> */
    public function mentorProfile(): BelongsTo
    {
        return $this->belongsTo(MentorProfile::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function contextCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'context_course_id');
    }

    protected static function newFactory(): MentorRequestFactory
    {
        return MentorRequestFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MentorRequestStatus::class,
            'version' => 'integer',
            'responded_at' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
        ];
    }
}
