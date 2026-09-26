<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Models;

use App\Domains\Users\Models\User;
use App\Support\StoresUtcDateTimes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_name',
    'institution_country_code',
    'department',
    'degree',
    'major',
    'year_label',
    'term_label',
])]
final class UserProfile extends Model
{
    use StoresUtcDateTimes;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
