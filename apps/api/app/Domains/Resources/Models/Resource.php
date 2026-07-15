<?php

declare(strict_types=1);

namespace App\Domains\Resources\Models;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int|null $course_id
 * @property ResourceKind $kind
 * @property string $title
 * @property string|null $description
 * @property string|null $topic_label
 * @property string|null $source_url
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read Course|null $course
 * @property-read StoredFile|null $storedFile
 */
final class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'user_id',
        'course_id',
        'kind',
        'version',
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

    /** @return HasOne<StoredFile, $this> */
    public function storedFile(): HasOne
    {
        return $this->hasOne(StoredFile::class);
    }

    protected static function newFactory(): ResourceFactory
    {
        return ResourceFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ResourceKind::class,
            'version' => 'integer',
            'resource_updated_at_asc' => 'immutable_datetime',
            'resource_updated_at_desc' => 'immutable_datetime',
        ];
    }
}
