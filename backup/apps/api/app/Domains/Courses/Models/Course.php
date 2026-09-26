<?php

declare(strict_types=1);

namespace App\Domains\Courses\Models;

use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'user_id',
        'version',
        'onboarding_position',
        'archived_at',
    ];

    protected $hidden = ['id', 'user_id', 'academic_term_id', 'onboarding_position'];

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

    /** @return BelongsTo<AcademicTerm, $this> */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<FocusSession, $this> */
    public function focusSessions(): HasMany
    {
        return $this->hasMany(FocusSession::class);
    }

    /** @return HasMany<\App\Domains\Resources\Models\Resource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'onboarding_position' => 'integer',
            'archived_at' => 'datetime',
            'cursor_created_at_asc' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
            'cursor_updated_at_asc' => 'immutable_datetime',
            'cursor_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
