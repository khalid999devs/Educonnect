<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Users\Data\SettingsSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin SettingsSnapshot */
final class SettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->snapshot();
        $user = $snapshot->user;

        return [
            'account' => [
                'id' => (string) $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'primary_role' => $user->primaryRoleKey()?->value,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'profile' => [
                'institution_name' => $snapshot->profile['institution_name'],
                'institution_country_code' => $snapshot->profile['institution_country_code'],
                'department' => $snapshot->profile['department'],
                'degree' => $snapshot->profile['degree'],
                'major' => $snapshot->profile['major'],
                'year_label' => $snapshot->profile['year_label'],
                'term_label' => $snapshot->profile['term_label'],
            ],
            'course_preferences' => [
                'total' => $snapshot->coursePreferences['total'],
                'active' => $snapshot->coursePreferences['active'],
                'archived' => $snapshot->coursePreferences['archived'],
                'unfiled_resources' => $snapshot->coursePreferences['unfiled_resources'],
            ],
            'onboarding_completed' => $snapshot->onboardingCompleted,
        ];
    }

    private function snapshot(): SettingsSnapshot
    {
        if (! $this->resource instanceof SettingsSnapshot) {
            throw new LogicException('The settings resource requires a settings snapshot.');
        }

        return $this->resource;
    }
}
