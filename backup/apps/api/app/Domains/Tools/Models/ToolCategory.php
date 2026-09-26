<?php

declare(strict_types=1);

namespace App\Domains\Tools\Models;

use App\Support\StoresUtcDateTimes;
use Carbon\CarbonImmutable;
use Database\Factories\ToolCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, Tool> $tools
 */
final class ToolCategory extends Model
{
    /** @use HasFactory<ToolCategoryFactory> */
    use HasFactory, HasUlids, StoresUtcDateTimes;

    protected $guarded = [
        'id',
        'public_id',
        'slug',
    ];

    protected $hidden = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return HasMany<Tool, $this> */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    protected static function newFactory(): ToolCategoryFactory
    {
        return ToolCategoryFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
