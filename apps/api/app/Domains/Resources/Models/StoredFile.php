<?php

declare(strict_types=1);

namespace App\Domains\Resources\Models;

use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $resource_id
 * @property string $original_name
 * @property string $declared_mime_type
 * @property int $expected_size
 * @property string $sha256
 * @property string|null $upload_key
 * @property string $object_key
 * @property string|null $verified_mime_type
 * @property int|null $verified_size
 * @property StoredFileStatus $status
 * @property CarbonImmutable $upload_expires_at
 * @property CarbonImmutable|null $cleanup_after
 * @property CarbonImmutable|null $cleanup_started_at
 * @property int $cleanup_failures
 * @property CarbonImmutable|null $purge_ready_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read \App\Domains\Resources\Models\Resource $resource
 */
final class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'user_id',
        'resource_id',
        'upload_key',
        'object_key',
        'verified_mime_type',
        'verified_size',
        'status',
        'upload_expires_at',
        'cleanup_after',
        'cleanup_started_at',
        'cleanup_failures',
        'purge_ready_at',
        'ready_at',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'resource_id',
        'upload_key',
        'object_key',
        'sha256',
    ];

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

    /** @return BelongsTo<\App\Domains\Resources\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    protected static function newFactory(): StoredFileFactory
    {
        return StoredFileFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_size' => 'integer',
            'verified_size' => 'integer',
            'status' => StoredFileStatus::class,
            'upload_expires_at' => 'immutable_datetime',
            'cleanup_after' => 'immutable_datetime',
            'cleanup_started_at' => 'immutable_datetime',
            'cleanup_failures' => 'integer',
            'purge_ready_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
        ];
    }
}
