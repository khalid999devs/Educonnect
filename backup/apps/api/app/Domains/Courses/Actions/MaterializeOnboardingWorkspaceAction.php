<?php

declare(strict_types=1);

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Onboarding\Models\OnboardingCourseDraft;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Onboarding\Models\UserProfile;
use App\Domains\Users\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class MaterializeOnboardingWorkspaceAction
{
    public function execute(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $progress = OnboardingProgress::query()->lockForUpdate()->find($user->getKey());

            if (! $progress instanceof OnboardingProgress || $progress->completed_at === null) {
                throw new DomainException('Only completed onboarding can be materialized.');
            }

            if ($progress->getAttribute('academic_materialized_at') !== null) {
                return false;
            }

            $now = now();
            $profile = UserProfile::query()->find($user->getKey());
            $termLabel = is_string($profile?->term_label) ? trim($profile->term_label) : '';
            $termId = null;

            if ($termLabel !== '') {
                $termId = AcademicTerm::query()->forceCreate([
                    'user_id' => $user->getKey(),
                    'label' => $termLabel,
                    'starts_on' => null,
                    'ends_on' => null,
                    'version' => 1,
                ])->getKey();
            }

            $drafts = OnboardingCourseDraft::query()
                ->where('user_id', $user->getKey())
                ->orderBy('position')
                ->get(['position', 'title', 'code']);

            foreach ($drafts as $draft) {
                Course::query()->forceCreate([
                    'user_id' => $user->getKey(),
                    'academic_term_id' => $termId,
                    'title' => $draft->title,
                    'code' => $draft->code,
                    'description' => null,
                    'version' => 1,
                    'onboarding_position' => $draft->position,
                    'archived_at' => null,
                ]);
            }

            DB::table('onboarding_progress')
                ->where('user_id', $user->getKey())
                ->update(['academic_materialized_at' => $now]);

            return true;
        }, 3);
    }
}
