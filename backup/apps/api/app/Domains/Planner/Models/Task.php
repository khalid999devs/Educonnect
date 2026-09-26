<?php

declare(strict_types=1);

namespace App\Domains\Planner\Models;

use App\Domains\Courses\Models\Course;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'user_id',
        'course_id',
        'version',
        'completed_at',
        'archived_at',
    ];

    protected $hidden = ['id', 'user_id', 'course_id'];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<FocusSession, $this> */
    public function focusSessions(): HasMany
    {
        return $this->hasMany(FocusSession::class);
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'version' => 'integer',
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'cursor_updated_at_asc' => 'immutable_datetime',
            'cursor_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
