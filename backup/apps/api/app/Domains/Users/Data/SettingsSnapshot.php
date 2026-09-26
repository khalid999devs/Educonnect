<?php

declare(strict_types=1);

namespace App\Domains\Users\Data;

use App\Domains\Users\Models\User;

final readonly class SettingsSnapshot
{
    /**
     * @param  array{institution_name: ?string, institution_country_code: ?string, department: ?string, degree: ?string, major: ?string, year_label: ?string, term_label: ?string}  $profile
     * @param  array{total: int, active: int, archived: int, unfiled_resources: int}  $coursePreferences
     */
    public function __construct(
        public User $user,
        public array $profile,
        public array $coursePreferences,
        public bool $onboardingCompleted,
    ) {}
}
