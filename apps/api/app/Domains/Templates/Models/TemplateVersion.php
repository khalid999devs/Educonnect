<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Templates\Enums\TemplateFormat;
use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\TemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property int $version_number
 * @property TemplateFormat $format
 * @property string $body
 * @property string|null $change_note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Template $template
 */
final class TemplateVersion extends Model
{
    /** @use HasFactory<TemplateVersionFactory> */
    use HasFactory, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'template_id',
    ];

    protected $hidden = [
        'id',
        'template_id',
    ];

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    protected static function newFactory(): TemplateVersionFactory
    {
        return TemplateVersionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'format' => TemplateFormat::class,
        ];
    }
}
