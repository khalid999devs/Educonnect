<?php

declare(strict_types=1);

namespace App\Domains\Users\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Data\SettingsSnapshot;
use App\Domains\Users\Exceptions\UserPersistenceFailure;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class BuildOwnSettings
{
    public function execute(User $user): SettingsSnapshot
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use ($user, $ownsTransaction): SettingsSnapshot {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                return new SettingsSnapshot(
                    user: $user,
                    profile: $this->profile($user),
                    coursePreferences: $this->coursePreferences($user),
                    onboardingCompleted: DB::table('onboarding_progress')
                        ->where('user_id', $user->getKey())
                        ->whereNotNull('completed_at')
                        ->exists(),
                );
            }, 3);
        } catch (QueryException $exception) {
            throw UserPersistenceFailure::fromQueryException($exception, 'settings.read');
        }
    }

    /**
     * @return array{institution_name: ?string, institution_country_code: ?string, department: ?string, degree: ?string, major: ?string, year_label: ?string, term_label: ?string}
     */
    private function profile(User $user): array
    {
        $profile = DB::table('user_profiles')->where('user_id', $user->getKey())->first();

        return [
            'institution_name' => $this->nullableString($profile?->institution_name),
            'institution_country_code' => $this->nullableString($profile?->institution_country_code),
            'department' => $this->nullableString($profile?->department),
            'degree' => $this->nullableString($profile?->degree),
            'major' => $this->nullableString($profile?->major),
            'year_label' => $this->nullableString($profile?->year_label),
            'term_label' => $this->nullableString($profile?->term_label),
        ];
    }

    /**
     * @return array{total: int, active: int, archived: int, unfiled_resources: int}
     */
    private function coursePreferences(User $user): array
    {
        $counts = DB::table('courses')
            ->where('user_id', $user->getKey())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE archived_at IS NULL) AS active')
            ->selectRaw('COUNT(*) FILTER (WHERE archived_at IS NOT NULL) AS archived')
            ->first();
        $unfiled = DB::table('resources')
            ->where('user_id', $user->getKey())
            ->whereNull('course_id')
            ->count();

        return [
            'total' => (int) ($counts->total ?? 0),
            'active' => (int) ($counts->active ?? 0),
            'archived' => (int) ($counts->archived ?? 0),
            'unfiled_resources' => $unfiled,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
