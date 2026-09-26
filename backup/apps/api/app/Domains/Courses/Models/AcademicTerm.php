<?php

declare(strict_types=1);

namespace App\Domains\Courses\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Database\Factories\AcademicTermFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AcademicTerm extends Model
{
    /** @use HasFactory<AcademicTermFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = ['id', 'public_id', 'user_id', 'version'];

    protected $hidden = ['id', 'user_id'];

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

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    protected static function newFactory(): AcademicTermFactory
    {
        return AcademicTermFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'version' => 'integer',
            'cursor_created_at_asc' => 'immutable_datetime',
            'cursor_created_at_desc' => 'immutable_datetime',
            'cursor_updated_at_asc' => 'immutable_datetime',
            'cursor_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
