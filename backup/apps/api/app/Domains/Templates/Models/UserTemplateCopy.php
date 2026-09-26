<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Courses\Models\Course;
use App\Domains\Templates\Enums\TemplateCopyDestination;
use App\Domains\Templates\Enums\TemplateFormat;
use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\UserTemplateCopyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $template_id
 * @property int $template_version_id
 * @property TemplateCopyDestination $destination
 * @property int|null $course_id
 * @property string $title
 * @property TemplateFormat $format
 * @property string $body
 * @property int $version
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read Template $template
 * @property-read TemplateVersion $templateVersion
 * @property-read Course|null $course
 */
final class UserTemplateCopy extends Model
{
    /** @use HasFactory<UserTemplateCopyFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'user_id',
        'template_id',
        'template_version_id',
        'destination',
        'course_id',
        'format',
        'version',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'template_id',
        'template_version_id',
        'course_id',
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

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** @return BelongsTo<TemplateVersion, $this> */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected static function newFactory(): UserTemplateCopyFactory
    {
        return UserTemplateCopyFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'destination' => TemplateCopyDestination::class,
            'format' => TemplateFormat::class,
            'version' => 'integer',
            'archived_at' => 'immutable_datetime',
            'copy_created_at_desc' => 'immutable_datetime',
            'copy_title_asc' => 'string',
        ];
    }
}
