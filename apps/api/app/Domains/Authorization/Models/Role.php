<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Role extends Model
{
    use StoresUtcDateTimes;

    protected $guarded = ['id', 'key', 'is_system', 'is_protected'];

    /**
     * @return BelongsToMany<Capability, $this>
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Capability::class, 'role_capability');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withPivot('assigned_at');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function key(): Attribute
    {
        return Attribute::make(set: static fn (): never => throw new \LogicException('Role keys are immutable.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_priority' => 'integer',
            'is_system' => 'boolean',
            'is_protected' => 'boolean',
        ];
    }
}
