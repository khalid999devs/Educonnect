<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Models;

use App\Support\StoresUtcDateTimes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Capability extends Model
{
    use StoresUtcDateTimes;

    protected $guarded = ['id', 'key'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_capability');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function key(): Attribute
    {
        return Attribute::make(set: static fn (): never => throw new \LogicException('Capability keys are immutable.'));
    }
}
